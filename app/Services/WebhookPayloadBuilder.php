<?php
// app/Services/WebhookPayloadBuilder.php

declare(strict_types=1);

namespace App\Services;

use App\Models\Creneau;
use App\Models\Setting;
use App\Models\Tache;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Construit le payload JSON à envoyer vers Make.com après génération du planning.
 *
 * Toutes les valeurs de configuration (lieu, heure_cours, offsets) sont
 * chargées depuis ref_settings via Setting::get() — plus aucune constante
 * hardcodée sauf le cas spécial du rappel_sandwich (horaire fixe 08:00–08:15).
 *
 * Structure du payload par créneau :
 * {
 *   "taches": {
 *     "entree": { nom_complet, email, heure_debut, heure_fin, description },
 *     "mektaba": { ... },
 *     "salle": { ... },
 *     "amana_food": { ... },
 *     "cours": { nom_complet, email, heure_debut, heure_fin, description }
 *   },
 *   "evenements_speciaux": {
 *     "rappel_sandwich": { nom_complet (= amana_food), email, heure_debut, heure_fin, description },
 *     "assistance_amana_food": { nom_complet (= entree), email, heure_debut, heure_fin, description }
 *   },
 *   "evenements_sociaux": {
 *     "annonce_cours": { nom_complet: null, email: null, heure_debut, heure_fin, description },
 *     "message_general": { nom_complet: null, email: null, heure_debut, heure_fin, description }
 *   }
 * }
 */
class WebhookPayloadBuilder
{
    /**
     * Construit et retourne le payload complet pour Make.com.
     *
     * @param string $dateDebut Date de début au format Y-m-d
     * @param int    $semaines  Nombre de semaines générées
     * @return array
     */
    public function build(string $dateDebut, int $semaines): array
    {
        // ── Paramètres depuis ref_settings ────────────────────────────────
        $heureCours = Setting::get('heure_cours', 'planning') ?? '20:00';
        $lieu = Setting::get('lieu', 'planning') ?? '';

        // ── Plage de dates ─────────────────────────────────────────────────
        $premier = $this->trouverPremierVendredi($dateDebut);
        $fin = $premier->copy()->addWeeks($semaines)->addDay();

        // ── Chargement des créneaux ────────────────────────────────────────
        $creneaux = Creneau::with(['taches.tache', 'taches.personne', 'evenements'])
            ->whereBetween('date', [$premier->toDateString(), $fin->toDateString()])
            ->orderBy('date')
            ->get();

        // ── Toutes les tâches indexées par code (actif=0 inclus) ───────────
        $toutesLesTaches = Tache::all()->keyBy('code');

        // ── Construction des créneaux ──────────────────────────────────────
        $creneauxPayload = $creneaux->map(
            fn(Creneau $c) => $this->buildCreneau($c, $toutesLesTaches, $heureCours)
        )->values()->all();

        return [
            'genere_le' => now()->toIso8601String(),
            'heure_cours' => $heureCours,
            'lieu' => $lieu,
            'creneaux' => $creneauxPayload,
        ];
    }

    // ── Construction d'un créneau ──────────────────────────────────────────

    /**
     * Construit les données complètes d'un créneau.
     */
    private function buildCreneau(Creneau $creneau, Collection $taches, string $heureCours): array
    {
        $tachesMap = $creneau->taches->keyBy(fn($ct) => $ct->tache?->code);
        $date = Carbon::parse($creneau->date)->toDateString();

        // ── Tâches principales avec assignee ──────────────────────────────
        // cours rejoint entree, mektaba, salle, amana_food
        $tachesPayload = [];
        foreach (['entree', 'mektaba', 'salle', 'amana_food', 'cours'] as $code) {
            $tachesPayload[$code] = $this->buildTacheAssignee(
                $tachesMap->get($code),
                $taches->get($code),
                $code,
                $date,
                $heureCours
            );
        }

        // ── Événements spéciaux (personne héritée d'une autre tâche) ──────
        $personneAmanaFood = $tachesMap->get('amana_food')?->personne;
        $personneEntree = $tachesMap->get('entree')?->personne;

        $eventsSpeciaux = [
            'rappel_sandwich' => $this->buildRappelSandwich(
                $taches->get('rappel_sandwich'),
                $personneAmanaFood
            ),
            'assistance_amana_food' => $this->buildEvenementSpecial(
                'assistance_amana_food',
                $taches->get('assistance_amana_food'),
                $personneEntree,
                $date,
                $heureCours
            ),
        ];

        // ── Événements sociaux (pas d'assignee, computed uniquement) ──────
        $eventsSociaux = [
            'annonce_cours' => $this->buildEvenementSocial(
                'annonce_cours',
                $taches->get('annonce_cours'),
                $date,
                $heureCours
            ),
            'message_general' => $this->buildEvenementSocial(
                'message_bot',
                $taches->get('message_general'),
                $date,
                $heureCours
            ),
        ];

        // ── Noms des événements organisationnels liés ──────────────────────
        $nomsEvenements = $creneau->evenements->pluck('nom')->implode(', ');

        return [
            'date' => $date,
            'jour' => $creneau->jour,
            'semaine' => $creneau->semaine,
            'evenements' => $nomsEvenements ?: null,
            'taches' => $tachesPayload,
            'evenements_speciaux' => $eventsSpeciaux,
            'evenements_sociaux' => $eventsSociaux,
        ];
    }

    // ── Builders par type d'événement ─────────────────────────────────────

    /**
     * Construit une tâche avec personne assignée (entree, mektaba, salle,
     * amana_food, cours). Retourne toujours un tableau — nom_complet et email
     * sont null si la tâche n'est pas assignée.
     */
    private function buildTacheAssignee(
        mixed $creneauTache,
        mixed $tacheRef,
        string $code,
        string $date,
        string $heureCours
    ): array {
        $personne = $creneauTache?->personne;
        [$debut, $fin] = $this->calculerHoraires($code, $date, $heureCours);

        return [
            'nom_complet' => $personne ? trim($personne->prenom . ' ' . $personne->nom) : null,
            'email' => $personne?->email,
            'heure_debut' => $debut,
            'heure_fin' => $fin,
            'description' => $tacheRef?->description,
        ];
    }

    /**
     * Construit un événement spécial dont la personne est héritée d'une
     * autre tâche (rappel_sandwich → amana_food, assistance → entree).
     */
    private function buildEvenementSpecial(
        string $code,
        mixed $tacheRef,
        mixed $personne,
        string $date,
        string $heureCours
    ): array {
        [$debut, $fin] = $this->calculerHoraires($code, $date, $heureCours);

        return [
            'nom_complet' => $personne ? trim($personne->prenom . ' ' . $personne->nom) : null,
            'email' => $personne?->email,
            'heure_debut' => $debut,
            'heure_fin' => $fin,
            'description' => $tacheRef?->description,
        ];
    }

    /**
     * Construit le rappel sandwich : horaire fixe 08:00–08:15,
     * personne = celle assignée à amana_food ce jour.
     */
    private function buildRappelSandwich(mixed $tacheRef, mixed $personne): array
    {
        return [
            'nom_complet' => $personne ? trim($personne->prenom . ' ' . $personne->nom) : null,
            'email' => $personne?->email,
            'heure_debut' => '08:00',
            'heure_fin' => '08:15',
            'description' => $tacheRef?->description,
        ];
    }

    /**
     * Construit un événement social : pas d'assignee, timing relatif
     * à heure_cours via les offsets en ref_settings.
     * nom_complet et email sont toujours null.
     */
    private function buildEvenementSocial(
        string $codeOffset,
        mixed $tacheRef,
        string $date,
        string $heureCours
    ): array {
        [$debut, $fin] = $this->calculerHoraires($codeOffset, $date, $heureCours);

        return [
            'nom_complet' => null,
            'email' => null,
            'heure_debut' => $debut,
            'heure_fin' => $fin,
            'description' => $tacheRef?->description ?? '',
        ];
    }

    // ── Calcul des horaires ────────────────────────────────────────────────

    /**
     * Calcule les horaires début/fin pour un code de tâche donné.
     *
     * Les offsets sont chargés depuis ref_settings :
     *   offset_{code}_debut et offset_{code}_fin
     *
     * Cas spécial : rappel_sandwich → horaire fixe 08:00–08:15.
     *
     * @return array{0: string, 1: string} ['HH:MM', 'HH:MM']
     */
    private function calculerHoraires(string $code, string $date, string $heureCours): array
    {
        // Cas spécial : horaire fixe, indépendant de heure_cours
        if ($code === 'rappel_sandwich') {
            return ['08:00', '08:15'];
        }

        // Chargement des offsets depuis ref_settings
        $offsetDebut = Setting::get("offset_{$code}_debut", 'planning');
        $offsetFin = Setting::get("offset_{$code}_fin", 'planning');

        // Fallback si la clé n'existe pas en base
        if ($offsetDebut === null || $offsetFin === null) {
            $offsetDebut = 0;
            $offsetFin = 60;
        }

        // Base : heure_cours à la date du créneau
        $base = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $heureCours);
        $debut = $base->copy()->addMinutes((int) $offsetDebut)->format('H:i');
        $fin = $base->copy()->addMinutes((int) $offsetFin)->format('H:i');

        return [$debut, $fin];
    }

    // ── Utilitaire ────────────────────────────────────────────────────────

    /**
     * Trouve le premier vendredi à partir d'une date donnée.
     */
    private function trouverPremierVendredi(string $dateDebut): Carbon
    {
        $date = Carbon::parse($dateDebut)->startOfDay();
        while ($date->dayOfWeek !== Carbon::FRIDAY) {
            $date->addDay();
        }
        return $date;
    }
}