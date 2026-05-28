<?php
// app/Services/WebhookPayloadBuilder.php

declare(strict_types=1);

namespace App\Services;

use App\Models\Creneau;
use App\Models\Tache;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Construit le payload JSON à envoyer vers Make.com après génération du planning.
 *
 * Calcul des horaires relatifs à HEURE_COURS (depuis .env) :
 *   entree              : cours - 30min  → cours + 30min
 *   mektaba             : cours - 20min  → cours + 100min
 *   salle               : cours          → cours + 90min
 *   amana_food          : cours + 30min  → cours + 90min
 *   assistance_amana_food : cours + 30min → cours + 90min
 *   cours               : cours          → cours + 60min
 *   rappel_sandwich     : 08:00          → 08:15
 */
class WebhookPayloadBuilder
{
    /** Lieu fixe de tous les événements */
    private const LIEU = '319 Rte de Vannes, 44800 Saint-Herblain, France';

    /**
     * Décalages en minutes par rapport à HEURE_COURS : [debut, fin]
     */
    private const OFFSETS = [
        'entree' => [-30, 30],
        'mektaba' => [-20, 100],
        'salle' => [0, 90],
        'amana_food' => [30, 90],
        'assistance_amana_food' => [30, 90],
        'cours' => [0, 60],
    ];

    /**
     * Construit et retourne le payload complet pour Make.com.
     *
     * @param string $dateDebut Date de début au format Y-m-d
     * @param int    $semaines  Nombre de semaines générées
     * @return array
     */
    public function build(string $dateDebut, int $semaines): array
    {
        // Récupération de HEURE_COURS depuis .env
        $heureCours = env('HEURE_COURS', '20:00');

        // Calcul de la plage de dates (premier vendredi → fin)
        $premier = $this->trouverPremierVendredi($dateDebut);
        $fin = $premier->copy()->addWeeks($semaines)->addDay();

        // Chargement de tous les créneaux dans la plage
        $creneaux = Creneau::with(['taches.tache', 'taches.personne', 'evenements'])
            ->whereBetween('date', [$premier->toDateString(), $fin->toDateString()])
            ->orderBy('date')
            ->get();

        // Chargement de TOUTES les tâches (actif=0 inclus) indexées par code
        $toutesLesTaches = Tache::all()->keyBy('code');

        // Construction des créneaux
        $creneauxPayload = $creneaux->map(
            fn(Creneau $c) => $this->buildCreneau($c, $toutesLesTaches, $heureCours)
        )->values()->all();

        return [
            'genere_le' => now()->toIso8601String(),
            'heure_cours' => $heureCours,
            'lieu' => self::LIEU,
            'creneaux' => $creneauxPayload,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────

    /**
     * Construit les données d'un créneau.
     */
    private function buildCreneau(Creneau $creneau, Collection $taches, string $heureCours): array
    {
        $tachesMap = $creneau->taches->keyBy(fn($ct) => $ct->tache?->code);

        // Tâches principales (actif = 1)
        $tachesPayload = [];
        foreach (['entree', 'mektaba', 'salle', 'amana_food'] as $code) {
            $tachesPayload[$code] = $this->buildTacheAssignee(
                $tachesMap->get($code),
                $taches->get($code),
                $code,
                $creneau->date->toDateString(),
                $heureCours
            );
        }

        // Événements spéciaux (actif = 0)
        $personneAmanaFood = $tachesMap->get('amana_food')?->personne;
        $personneEntree = $tachesMap->get('entree')?->personne;

        $eventsSpeciaux = [
            'cours' => $this->buildEvenementSpecial(
                'cours',
                $taches->get('cours'),
                null,
                $creneau->date->toDateString(),
                $heureCours
            ),
            'rappel_sandwich' => $this->buildRappelSandwich(
                $taches->get('rappel_sandwich'),
                $personneAmanaFood
            ),
            'assistance_amana_food' => $this->buildEvenementSpecial(
                'assistance_amana_food',
                $taches->get('assistance_amana_food'),
                $personneEntree,
                $creneau->date->toDateString(),
                $heureCours
            ),
        ];

        // Noms des événements organisationnels liés
        $nomsEvenements = $creneau->evenements->pluck('nom')->implode(', ');

        return [
            'date' => $creneau->date->toDateString(),
            'jour' => $creneau->jour,
            'semaine' => $creneau->semaine,
            'evenements' => $nomsEvenements ?: null,
            'taches' => $tachesPayload,
            'evenements_speciaux' => $eventsSpeciaux,
        ];
    }

    /**
     * Construit les données d'une tâche assignée (entree, mektaba, salle, amana_food).
     * Retourne null si non assignée.
     */
    private function buildTacheAssignee(
        mixed $creneauTache,
        mixed $tacheRef,
        string $code,
        string $date,
        string $heureCours
    ): ?array {
        $personne = $creneauTache?->personne;

        // Calcul des horaires
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
     * Construit un événement spécial avec personne optionnelle.
     */
    private function buildEvenementSpecial(
        string $code,
        mixed $tacheRef,
        mixed $personne,
        string $date,
        string $heureCours
    ): array {
        [$debut, $fin] = $this->calculerHoraires($code, $date, $heureCours);

        $data = [
            'heure_debut' => $debut,
            'heure_fin' => $fin,
            'description' => $tacheRef?->description,
        ];

        if ($personne !== null) {
            $data['nom_complet'] = trim($personne->prenom . ' ' . $personne->nom);
            $data['email'] = $personne->email;
        }

        return $data;
    }

    /**
     * Construit le rappel sandwich (horaire fixe 08:00-08:15).
     * Lié à la personne assignée à amana_food.
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
     * Calcule les horaires de début/fin pour un code de tâche donné.
     * Retourne ['HH:MM', 'HH:MM'].
     */
    private function calculerHoraires(string $code, string $date, string $heureCours): array
    {
        // Rappel sandwich : horaire fixe
        if ($code === 'rappel_sandwich') {
            return ['08:00', '08:15'];
        }

        $offsets = self::OFFSETS[$code] ?? [0, 60];

        // Base : HEURE_COURS à la date du créneau
        $base = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $heureCours);

        $debut = $base->copy()->addMinutes($offsets[0])->format('H:i');
        $fin = $base->copy()->addMinutes($offsets[1])->format('H:i');

        return [$debut, $fin];
    }

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