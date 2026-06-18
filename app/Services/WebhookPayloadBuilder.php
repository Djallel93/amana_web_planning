<?php
// app/Services/WebhookPayloadBuilder.php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\DateHelper;
use App\Models\Creneau;
use App\Models\Evenement;
use App\Models\Setting;
use App\Models\Tache;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Construit le payload JSON à envoyer vers Make.com après génération du planning
 * ou après une modification manuelle d'une assignation.
 *
 * Les tâches bloquées par un événement actif sont exclues du payload.
 */
class WebhookPayloadBuilder
{
    // ── Public entry points ───────────────────────────────────────────────

    /**
     * Construit le payload pour une plage de semaines (appelé après génération).
     */
    public function build(string $dateDebut, int $semaines): array
    {
        $heureCours = Setting::get('heure_cours', 'planning') ?? '20:00';
        $lieu = Setting::get('lieu', 'planning') ?? '';

        $premier = DateHelper::premierVendredi($dateDebut);
        $fin = $premier->clone()->addWeeks($semaines)->addDay();

        $creneaux = Creneau::with([
            'taches.tache',
            'taches.personne',
            'evenements.tachesBloquees',
        ])
            ->whereBetween('date', [$premier->toDateString(), $fin->toDateString()])
            ->orderBy('date')
            ->get();

        $toutesLesTaches = Tache::all()->keyBy('code');

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

    /**
     * Construit le payload pour un seul créneau (appelé après modification manuelle).
     * La structure racine est identique à build() — un seul élément dans "creneaux".
     */
    public function buildForCreneau(Creneau $creneau): array
    {
        // Recharger le créneau avec toutes les relations nécessaires
        $creneau->load([
            'taches.tache',
            'taches.personne',
            'evenements.tachesBloquees',
        ]);

        $heureCours = Setting::get('heure_cours', 'planning') ?? '20:00';
        $lieu = Setting::get('lieu', 'planning') ?? '';
        $toutesLesTaches = Tache::all()->keyBy('code');

        return [
            'genere_le' => now()->toIso8601String(),
            'heure_cours' => $heureCours,
            'lieu' => $lieu,
            'creneaux' => [
                $this->buildCreneau($creneau, $toutesLesTaches, $heureCours),
            ],
        ];
    }

    // ── Private builders ──────────────────────────────────────────────────

    private function buildCreneau(Creneau $creneau, Collection $taches, string $heureCours): array
    {
        $tachesMap = $creneau->taches->keyBy(fn($ct) => $ct->tache?->code);
        $date = Carbon::parse($creneau->date)->toDateString();

        // Codes de tâches bloquées par les événements actifs sur ce créneau
        $tachesBloquees = collect();
        foreach ($creneau->evenements as $evenement) {
            foreach ($evenement->tachesBloquees as $tache) {
                $tachesBloquees->push($tache->code);
            }
        }
        $tachesBloquees = $tachesBloquees->unique();

        // ── Tâches principales ─────────────────────────────────────────────
        $tachesPayload = [];
        foreach (['entree', 'mektaba', 'salle', 'amana_food', 'cours'] as $code) {
            if ($tachesBloquees->contains($code)) {
                continue; // Tâche bloquée → exclue du payload
            }
            $tachesPayload[$code] = $this->buildTacheAssignee(
                $tachesMap->get($code),
                $taches->get($code),
                $code,
                $date,
                $heureCours
            );
        }

        // ── Événements spéciaux ────────────────────────────────────────────
        $personneAmanaFood = $tachesBloquees->contains('amana_food')
            ? null
            : $tachesMap->get('amana_food')?->personne;

        $personneEntree = $tachesBloquees->contains('entree')
            ? null
            : $tachesMap->get('entree')?->personne;

        $eventsSpeciaux = [];

        if (!$tachesBloquees->contains('amana_food')) {
            $eventsSpeciaux['rappel_sandwich'] = $this->buildRappelSandwich(
                $taches->get('rappel_sandwich'),
                $personneAmanaFood
            );
        }

        if (!$tachesBloquees->contains('entree')) {
            $eventsSpeciaux['assistance_amana_food'] = $this->buildEvenementSpecial(
                'assistance_amana_food',
                $taches->get('assistance_amana_food'),
                $personneEntree,
                $date,
                $heureCours
            );
        }

        // ── Événements sociaux ─────────────────────────────────────────────
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
            'calendar_name' => $this->getCalendarName($code),
            'description' => $tacheRef?->description,
        ];
    }

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
            'calendar_name' => $this->getCalendarName($code),
            'description' => $tacheRef?->description,
        ];
    }

    private function buildRappelSandwich(mixed $tacheRef, mixed $personne): array
    {
        return [
            'nom_complet' => $personne ? trim($personne->prenom . ' ' . $personne->nom) : null,
            'email' => $personne?->email,
            'heure_debut' => '08:00',
            'heure_fin' => '08:15',
            'calendar_name' => $this->getCalendarName('rappel_sandwich'),
            'description' => $tacheRef?->description,
        ];
    }

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
            'calendar_name' => $this->getCalendarName($codeOffset),
            'description' => $tacheRef?->description ?? '',
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Récupère le nom de calendrier configuré pour un code de tâche/événement.
     * Clé en base : calendar_{code}  (ex: calendar_entree, calendar_amana_food)
     * Retourne null si non configuré — Make.com utilisera son calendrier par défaut.
     */
    private function getCalendarName(string $code): ?string
    {
        return Setting::get("calendar_{$code}", 'planning') ?: null;
    }

    private function calculerHoraires(string $code, string $date, string $heureCours): array
    {
        if ($code === 'rappel_sandwich') {
            return ['08:00', '08:15'];
        }

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