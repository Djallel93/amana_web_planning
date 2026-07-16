<?php
// app/Services/WebhookPayloadBuilder.php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\DateHelper;
use App\Models\Creneau;
use App\Models\Setting;
use App\Models\Tache;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Construit les payloads JSON envoyés vers Make.com pour le planning.
 *
 * Format (juil. 2026) : la racine se limite strictement à `lieu` + `creneaux`.
 * `taches`, `evenements_speciaux` et `evenements_sociaux` sont des TABLEAUX
 * (et non plus des objets indexés par code). Les événements organisationnels
 * (type Ramadan/Vacances) ne figurent PAS dans ces payloads de créneaux : ils
 * sont déjà envoyés individuellement à leur création/modification via
 * WebhookEvenementPayloadBuilder::buildUpsert(), les retraiter ici serait
 * redondant.
 *
 * Chaque méthode correspond à un verbe HTTP précis envoyé par EnvoyerWebhookMake :
 *   - build()                 → POST   génération complète
 *   - buildForCreation()      → POST   créneau créé manuellement (vide)
 *   - buildForReassignation() → PATCH  réassignation d'une tâche
 *   - buildForEchange()       → PATCH  exécution d'un échange (2 créneaux affectés)
 *   - buildForUnassignation() → DELETE désassignation explicite d'une tâche
 *   - buildForDeleteCreneau() → DELETE suppression d'un créneau entier
 *   - buildForAnnulationCours() → POST annonce d'annulation d'un cours
 *                                  (bouton "Annulation cours" du planning ;
 *                                  précédée d'un buildForDeleteCreneau() pour
 *                                  nettoyer les événements calendrier existants)
 *
 * Règle métier conservée : `rappel_sandwich` suit la personne assignée à
 * `amana_food`, et `assistance_amana_food` suit la personne assignée à
 * `entree`. Une réassignation/désassignation/échange impliquant l'une de
 * ces deux tâches principales propage donc l'événement spécial dépendant
 * dans le payload.
 */
class WebhookPayloadBuilder
{
    /** Codes des tâches principales, dans l'ordre d'affichage. */
    private const TACHES_PRINCIPALES = ['entree', 'mektaba', 'salle', 'amana_food', 'cours'];

    // ── POST : génération complète ───────────────────────────────────────

    public function build(string $dateDebut, int $semaines): array
    {
        $heureCours = Setting::get('heure_cours', 'planning') ?? '20:00';
        $premier = DateHelper::premierVendredi($dateDebut);
        $fin = $premier->clone()->addWeeks($semaines)->addDay();

        $creneaux = Creneau::with(['taches.tache', 'taches.personne', 'evenements.tachesBloquees'])
            ->whereBetween('date', [$premier->toDateString(), $fin->toDateString()])
            ->orderBy('date')
            ->get();

        $toutesLesTaches = Tache::all()->keyBy('code');

        return [
            'lieu' => $this->lieu(),
            'creneaux' => $creneaux
                ->map(fn(Creneau $c) => $this->buildCreneauComplet($c, $toutesLesTaches, $heureCours))
                ->values()
                ->all(),
        ];
    }

    // ── POST : création manuelle d'un créneau vide ───────────────────────

    /**
     * Payload pour un créneau fraîchement créé manuellement (toutes les
     * tâches sont vides à ce stade — aucune assignation).
     */
    public function buildForCreation(Creneau $creneau): array
    {
        $heureCours = Setting::get('heure_cours', 'planning') ?? '20:00';
        $toutesLesTaches = Tache::all()->keyBy('code');

        $creneau->loadMissing(['taches.tache', 'taches.personne', 'evenements.tachesBloquees']);

        return [
            'lieu' => $this->lieu(),
            'creneaux' => [$this->buildCreneauComplet($creneau, $toutesLesTaches, $heureCours)],
        ];
    }

    // ── POST : annonce de l'annulation d'un cours ────────────────────────

    /**
     * Payload pour l'annonce de l'annulation d'un cours (bouton "Annulation
     * cours" du planning).
     *
     * À appeler APRÈS que le créneau ait été rechargé avec ses tâches
     * désassignées et son événement bloquant lié — le payload reflète donc
     * un créneau entièrement bloqué (`taches` vide, comme n'importe quelle
     * autre date bloquée par un événement), avec en plus une entrée
     * `evenements_sociaux` dédiée pour le code `annulation_cours`, envoyée
     * exactement comme n'importe quel autre événement social (POST, même
     * structure que build()/buildForCreation()) — pas de type de payload
     * distinct côté Make.com.
     */
    public function buildForAnnulationCours(Creneau $creneau): array
    {
        $heureCours = Setting::get('heure_cours', 'planning') ?? '20:00';
        $toutesLesTaches = Tache::all()->keyBy('code');
        $date = Carbon::parse($creneau->date)->toDateString();

        $creneau->load(['taches.tache', 'taches.personne', 'evenements.tachesBloquees']);

        $entry = $this->buildCreneauComplet($creneau, $toutesLesTaches, $heureCours);
        $entry['evenements_sociaux'][] = $this->ligneAvecAssignation(
            'annulation_cours',
            null,
            $toutesLesTaches->get('annulation_cours'),
            $date,
            $heureCours
        );

        return [
            'lieu' => $this->lieu(),
            'creneaux' => [$entry],
        ];
    }

    // ── PATCH : réassignation d'une tâche ────────────────────────────────

    /**
     * Payload pour la réassignation d'une seule tâche (modale "Enregistrer",
     * y compris vers id_personne = null). Ne contient QUE la tâche modifiée,
     * et l'événement spécial dépendant le cas échéant.
     */
    public function buildForReassignation(Creneau $creneau, Tache $tache): array
    {
        $heureCours = Setting::get('heure_cours', 'planning') ?? '20:00';
        $toutesLesTaches = Tache::all()->keyBy('code');

        return [
            'lieu' => $this->lieu(),
            'creneaux' => [$this->buildCreneauEchange($creneau, $tache, $toutesLesTaches, $heureCours)],
        ];
    }

    // ── PATCH : exécution d'un échange (swap entre deux créneaux) ────────

    /**
     * Payload pour l'exécution d'un échange validé. Contient TOUJOURS les
     * deux créneaux affectés (date A + date B), même si l'un des deux est
     * désormais dans le passé — l'échange étant validé et réellement
     * exécuté en base, Make.com doit être tenu à jour pour garder
     * l'historique cohérent (pas de filtre sur la date).
     *
     * $creneauA/$tacheA et $creneauB/$tacheB représentent les deux slots
     * (créneau, tâche) dont la personne assignée vient de changer — peu
     * importe qui de A ou B "a demandé" l'échange, seul l'état final compte.
     */
    public function buildForEchange(Creneau $creneauA, Tache $tacheA, Creneau $creneauB, Tache $tacheB): array
    {
        $heureCours = Setting::get('heure_cours', 'planning') ?? '20:00';
        $toutesLesTaches = Tache::all()->keyBy('code');

        return [
            'lieu' => $this->lieu(),
            'creneaux' => [
                $this->buildCreneauEchange($creneauA, $tacheA, $toutesLesTaches, $heureCours),
                $this->buildCreneauEchange($creneauB, $tacheB, $toutesLesTaches, $heureCours),
            ],
        ];
    }

    // ── DELETE : désassignation explicite d'une tâche ────────────────────

    /**
     * Payload de suppression pour une tâche désassignée (bouton "✕ Désassigner").
     * Pas de nom_complet/email — uniquement de quoi localiser l'événement
     * calendrier côté Make.com (horaires + calendrier cible).
     */
    public function buildForUnassignation(Creneau $creneau, Tache $tache): array
    {
        $heureCours = Setting::get('heure_cours', 'planning') ?? '20:00';
        $toutesLesTaches = Tache::all()->keyBy('code');
        $date = Carbon::parse($creneau->date)->toDateString();

        $creneauPayload = [
            'date' => $date,
            'taches' => [
                $this->ligneSuppression($tache->code, $toutesLesTaches->get($tache->code), $date, $heureCours),
            ],
        ];

        $special = $this->evenementSpecialDependantSuppression($tache->code, $toutesLesTaches, $date, $heureCours);
        if ($special) {
            $creneauPayload['evenements_speciaux'] = [$special];
        }

        return ['lieu' => $this->lieu(), 'creneaux' => [$creneauPayload]];
    }

    // ── DELETE : suppression d'un créneau entier ─────────────────────────

    /**
     * Payload de suppression pour un créneau supprimé en intégralité.
     * Liste toutes les tâches + événements spéciaux/sociaux susceptibles
     * d'avoir un événement calendrier créé, pour que Make.com nettoie tout
     * en une fois.
     *
     * ⚠️ À appeler AVANT la suppression effective en base — le créneau doit
     * encore exister pour connaître ses tâches bloquées par événement.
     */
    public function buildForDeleteCreneau(Creneau $creneau): array
    {
        $heureCours = Setting::get('heure_cours', 'planning') ?? '20:00';
        $toutesLesTaches = Tache::all()->keyBy('code');
        $date = Carbon::parse($creneau->date)->toDateString();

        $creneau->loadMissing('evenements.tachesBloquees');
        $tachesBloquees = $this->tachesBloqueesCodes($creneau);

        $taches = [];
        foreach (self::TACHES_PRINCIPALES as $code) {
            if ($tachesBloquees->contains($code)) {
                continue;
            }
            $taches[] = $this->ligneSuppression($code, $toutesLesTaches->get($code), $date, $heureCours);
        }

        $eventsSpeciaux = [];
        if (!$tachesBloquees->contains('amana_food')) {
            $eventsSpeciaux[] = $this->ligneSuppression(
                'rappel_sandwich',
                $toutesLesTaches->get('rappel_sandwich'),
                $date,
                $heureCours,
                fixe: ['08:00', '08:15']
            );
        }
        if (!$tachesBloquees->contains('entree')) {
            $eventsSpeciaux[] = $this->ligneSuppression(
                'assistance_amana_food',
                $toutesLesTaches->get('assistance_amana_food'),
                $date,
                $heureCours
            );
        }

        $eventsSociaux = [
            $this->ligneSuppression('annonce_cours', $toutesLesTaches->get('annonce_cours'), $date, $heureCours),
            $this->ligneSuppression('message_bot', $toutesLesTaches->get('message_bot'), $date, $heureCours),
        ];

        return [
            'lieu' => $this->lieu(),
            'creneaux' => [
                [
                    'date' => $date,
                    'taches' => $taches,
                    'evenements_speciaux' => $eventsSpeciaux,
                    'evenements_sociaux' => $eventsSociaux,
                ]
            ],
        ];
    }

    // ── Private : construction d'un créneau complet (POST) ───────────────

    private function buildCreneauComplet(Creneau $creneau, Collection $taches, string $heureCours): array
    {
        $date = Carbon::parse($creneau->date)->toDateString();
        $tachesMap = $creneau->taches->keyBy(fn($ct) => $ct->tache?->code);
        $tachesBloquees = $this->tachesBloqueesCodes($creneau);

        $tachesPayload = [];
        foreach (self::TACHES_PRINCIPALES as $code) {
            if ($tachesBloquees->contains($code)) {
                continue;
            }
            $tachesPayload[] = $this->ligneAvecAssignation(
                $code,
                $tachesMap->get($code)?->personne,
                $taches->get($code),
                $date,
                $heureCours
            );
        }

        $personneAmanaFood = $tachesBloquees->contains('amana_food') ? null : $tachesMap->get('amana_food')?->personne;
        $personneEntree = $tachesBloquees->contains('entree') ? null : $tachesMap->get('entree')?->personne;

        $eventsSpeciaux = [];
        if (!$tachesBloquees->contains('amana_food')) {
            $eventsSpeciaux[] = $this->ligneAvecAssignation(
                'rappel_sandwich',
                $personneAmanaFood,
                $taches->get('rappel_sandwich'),
                $date,
                $heureCours,
                fixe: ['08:00', '08:15']
            );
        }
        if (!$tachesBloquees->contains('entree')) {
            $eventsSpeciaux[] = $this->ligneAvecAssignation(
                'assistance_amana_food',
                $personneEntree,
                $taches->get('assistance_amana_food'),
                $date,
                $heureCours
            );
        }

        $eventsSociaux = [
            $this->ligneAvecAssignation('annonce_cours', null, $taches->get('annonce_cours'), $date, $heureCours),
            $this->ligneAvecAssignation('message_bot', null, $taches->get('message_bot'), $date, $heureCours),
        ];

        // NB : les événements organisationnels (Ramadan, vacances…) ne sont
        // volontairement PAS inclus ici — ils sont déjà envoyés à Make.com
        // individuellement (POST/PATCH) au moment de leur création/modification
        // via WebhookEvenementPayloadBuilder::buildUpsert(), donc les
        // retraiter à chaque génération de planning serait redondant.
        // $creneau->evenements reste chargé/utilisé par tachesBloqueesCodes()
        // ci-dessus pour savoir quelles tâches exclure de $tachesPayload.

        return [
            'date' => $date,
            'taches' => $tachesPayload,
            'evenements_speciaux' => $eventsSpeciaux,
            'evenements_sociaux' => $eventsSociaux,
        ];
    }

    /**
     * Construit l'entrée "creneaux[]" pour un seul (créneau, tâche) modifié —
     * partagée par buildForReassignation() et buildForEchange().
     */
    private function buildCreneauEchange(Creneau $creneau, Tache $tache, Collection $taches, string $heureCours): array
    {
        $date = Carbon::parse($creneau->date)->toDateString();

        $creneau->loadMissing(['taches.tache', 'taches.personne']);
        $ct = $creneau->taches->first(fn($t) => $t->id_tache === $tache->id);
        $personne = $ct?->personne;

        $entry = [
            'date' => $date,
            'taches' => [
                $this->ligneAvecAssignation($tache->code, $personne, $taches->get($tache->code), $date, $heureCours),
            ],
        ];

        $special = $this->evenementSpecialDependant($tache->code, $personne, $taches, $date, $heureCours);
        if ($special) {
            $entry['evenements_speciaux'] = [$special];
        }

        return $entry;
    }

    // ── Private : lignes individuelles ────────────────────────────────────

    /** Ligne complète (avec assignation) — utilisée par POST et PATCH. */
    private function ligneAvecAssignation(
        string $code,
        mixed $personne,
        mixed $tacheRef,
        string $date,
        string $heureCours,
        ?array $fixe = null,
        ?string $codeOffset = null,
    ): array {
        $cleHoraire = $codeOffset ?? $code;
        [$debut, $fin] = $fixe ?? $this->calculerHoraires($cleHoraire, $date, $heureCours);

        return [
            'nom' => $tacheRef?->libelle ?? ucfirst(str_replace('_', ' ', $code)),
            'assigne' => $personne ? trim($personne->prenom . ' ' . $personne->nom) : null,
            'email' => $personne?->email,
            'heure_debut' => $debut,
            'heure_fin' => $fin,
            'calendar_names' => $this->getCalendarNames($cleHoraire),
            'description' => $tacheRef?->description_calendrier ?? '',
        ];
    }

    /** Ligne minimale (sans assignation) — utilisée par DELETE. */
    private function ligneSuppression(
        string $code,
        mixed $tacheRef,
        string $date,
        string $heureCours,
        ?array $fixe = null,
        ?string $codeOffset = null,
    ): array {
        $cleHoraire = $codeOffset ?? $code;
        [$debut, $fin] = $fixe ?? $this->calculerHoraires($cleHoraire, $date, $heureCours);

        return [
            'nom' => $tacheRef?->libelle ?? ucfirst(str_replace('_', ' ', $code)),
            'heure_debut' => $debut,
            'heure_fin' => $fin,
            'calendar_names' => $this->getCalendarNames($cleHoraire),
        ];
    }

    /** Événement spécial dépendant — utilisé par PATCH (réassignation ET échange). */
    private function evenementSpecialDependant(
        string $codeTache,
        mixed $personne,
        Collection $taches,
        string $date,
        string $heureCours
    ): ?array {
        return match ($codeTache) {
            'amana_food' => $this->ligneAvecAssignation(
                'rappel_sandwich',
                $personne,
                $taches->get('rappel_sandwich'),
                $date,
                $heureCours,
                fixe: ['08:00', '08:15']
            ),
            'entree' => $this->ligneAvecAssignation(
                'assistance_amana_food',
                $personne,
                $taches->get('assistance_amana_food'),
                $date,
                $heureCours
            ),
            default => null,
        };
    }

    /** Événement spécial dépendant pour une désassignation (DELETE). */
    private function evenementSpecialDependantSuppression(
        string $codeTache,
        Collection $taches,
        string $date,
        string $heureCours
    ): ?array {
        return match ($codeTache) {
            'amana_food' => $this->ligneSuppression(
                'rappel_sandwich',
                $taches->get('rappel_sandwich'),
                $date,
                $heureCours,
                fixe: ['08:00', '08:15']
            ),
            'entree' => $this->ligneSuppression(
                'assistance_amana_food',
                $taches->get('assistance_amana_food'),
                $date,
                $heureCours
            ),
            default => null,
        };
    }

    // ── Private : helpers ──────────────────────────────────────────────────

    private function tachesBloqueesCodes(Creneau $creneau): Collection
    {
        $codes = collect();
        foreach ($creneau->evenements as $evenement) {
            foreach ($evenement->tachesBloquees as $tache) {
                $codes->push($tache->code);
            }
        }
        return $codes->unique();
    }

    private function lieu(): string
    {
        return Setting::get('lieu', 'planning') ?? '';
    }

    /**
     * Retourne les calendriers Google Calendar cibles pour un code donné.
     *
     * Aujourd'hui un seul calendrier est configurable par code dans les
     * Paramètres (une valeur ref_settings par calendar_<code>), mais le
     * payload expose déjà un tableau — si demain plusieurs calendriers
     * doivent être configurables pour un même code, seul ce point change.
     *
     * @return array<int, string>
     */
    private function getCalendarNames(string $code): array
    {
        $valeur = Setting::get("calendar_{$code}", 'planning');
        return $valeur ? [$valeur] : [];
    }

    private function calculerHoraires(string $code, string $date, string $heureCours): array
    {
        $offsetDebut = Setting::get("offset_{$code}_debut", 'planning');
        $offsetFin = Setting::get("offset_{$code}_fin", 'planning');

        if ($offsetDebut === null || $offsetFin === null) {
            $offsetDebut = 0;
            $offsetFin = 60;
        }

        $base = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $heureCours);
        $debut = $base->clone()->addMinutes((int) $offsetDebut)->format('H:i');
        $fin = $base->clone()->addMinutes((int) $offsetFin)->format('H:i');

        return [$debut, $fin];
    }
}