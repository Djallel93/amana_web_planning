<?php
// app/Services/DataLoader.php

declare(strict_types=1);

namespace App\Services;

use App\Models\Personne;
use App\Models\Absence;
use App\Models\Evenement;
use App\Models\Creneau;
use App\Models\CreneauTache;
use App\Models\Tache;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Service de chargement des données nécessaires à la génération du planning.
 * Équivalent PHP/Eloquent de DataLoader.js (Google Apps Script).
 */
class DataLoader
{
    /**
     * Charge toutes les données et construit le contexte de génération.
     * Ce contexte est ensuite passé au RotationEngine.
     *
     * @param string $dateDebut Date de début au format Y-m-d
     * @return array Contexte complet pour la génération
     */
    public function initializeContext(string $dateDebut): array
    {
        $personnes     = $this->loadActivePersonnes();
        $taches        = $this->loadTachesActives();
        $absences      = $this->loadAbsences();
        $evenements    = $this->loadEvenements();
        $premierVendredi = $this->findPremierVendredi($dateDebut);
        $counters      = $this->initializeCountersFromHistory($personnes, $taches);
        $personOptions = $this->calculatePersonOptions($personnes, $taches);

        return array_merge([
            'personnes'       => $personnes,
            'taches'          => $taches,
            'absences'        => $absences,
            'evenements'      => $evenements,
            'premierVendredi' => $premierVendredi,
            'personOptions'   => $personOptions,
        ], $counters);
    }

    /**
     * Charge les personnes actives dans le planning.
     * Équivalent de loadActivePeople() dans DataLoader.js.
     */
    public function loadActivePersonnes(): Collection
    {
        return Personne::actifAuPlanning()
            ->with(['restrictions.tache'])
            ->orderBy('nom')
            ->get();
    }

    /**
     * Charge les tâches actives (entree, mektaba, salle, amana_food).
     */
    public function loadTachesActives(): Collection
    {
        return Tache::actif()->orderBy('id')->get();
    }

    /**
     * Charge toutes les absences (pour vérification rapide).
     */
    public function loadAbsences(): Collection
    {
        return Absence::with('personne')
            ->where('date_fin', '>=', now()->toDateString())
            ->get();
    }

    /**
     * Charge les événements organisationnels.
     */
    public function loadEvenements(): Collection
    {
        return Evenement::futursOuEnCours()->get();
    }

    /**
     * Trouve le premier vendredi à partir d'une date donnée.
     * Équivalent de findFirstFriday() dans DataLoader.js.
     */
    public function findPremierVendredi(string $dateDebut): Carbon
    {
        $date = Carbon::parse($dateDebut)->startOfDay();

        // dayOfWeek : 0=Dimanche, 1=Lundi, ..., 5=Vendredi, 6=Samedi
        while ($date->dayOfWeek !== 5) {
            $date->addDay();
        }

        return $date;
    }

    /**
     * Initialise les compteurs depuis l'historique du planning existant.
     * Équivalent de initializeCountersFromHistory() dans DataLoader.js.
     *
     * @param Collection $personnes
     * @param Collection $taches
     * @return array ['amanaFoodCycles', 'lastWorkDate', 'totalTasks', 'taskHistory']
     */
    public function initializeCountersFromHistory(Collection $personnes, Collection $taches): array
    {
        // Récupérer la tâche amana_food
        $amanaFood = $taches->firstWhere('code', 'amana_food');

        // Initialiser les structures
        $amanaFoodCycles = [];
        $lastWorkDate    = [];
        $totalTasks      = [];
        $taskHistory     = [];

        foreach ($taches as $tache) {
            $taskHistory[$tache->code] = [];
        }

        foreach ($personnes as $personne) {
            $nom = $personne->nom . ' ' . $personne->prenom;

            // Cycle amana_food : inclure si la personne peut faire amana_food
            if ($amanaFood) {
                $peutVendredi = $personne->peutFaireTache($amanaFood->id, 'Vendredi');
                $peutSamedi   = $personne->peutFaireTache($amanaFood->id, 'Samedi');
                if ($peutVendredi || $peutSamedi) {
                    $amanaFoodCycles[$nom] = 0;
                }
            }

            $lastWorkDate[$nom] = null;
            $totalTasks[$nom]   = 0;

            foreach ($taches as $tache) {
                $taskHistory[$tache->code][$nom] = 0;
            }
        }

        // Lire l'historique existant depuis la base de données
        $historique = CreneauTache::with(['creneau', 'tache', 'personne'])
            ->whereNotNull('id_personne')
            ->get();

        $autreCount = 0; // Postes "Autre" comptés globalement

        foreach ($historique as $ligne) {
            if (! $ligne->personne || ! $ligne->tache || ! $ligne->creneau) {
                continue;
            }

            $nom   = $ligne->personne->nom . ' ' . $ligne->personne->prenom;
            $code  = $ligne->tache->code;
            $date  = $ligne->creneau->date;

            // Mise à jour lastWorkDate
            if (! isset($lastWorkDate[$nom]) || $date > $lastWorkDate[$nom]) {
                $lastWorkDate[$nom] = $date;
            }

            // Mise à jour totalTasks
            $totalTasks[$nom] = ($totalTasks[$nom] ?? 0) + 1;

            // Mise à jour taskHistory
            if (isset($taskHistory[$code][$nom])) {
                $taskHistory[$code][$nom]++;
            }

            // Mise à jour cycle amana_food
            if ($code === 'amana_food' && isset($amanaFoodCycles[$nom])) {
                $amanaFoodCycles[$nom]++;
            }
        }

        return compact('amanaFoodCycles', 'lastWorkDate', 'totalTasks', 'taskHistory');
    }

    /**
     * Calcule le nombre d'options (postes possibles) pour chaque personne.
     * Équivalent de calculatePersonOptions() dans DataLoader.js.
     *
     * @param Collection $personnes
     * @param Collection $taches
     * @return array ['nom prenom' => int]
     */
    public function calculatePersonOptions(Collection $personnes, Collection $taches): array
    {
        $personOptions = [];
        $jours = ['Vendredi', 'Samedi'];

        foreach ($personnes as $personne) {
            $nom   = $personne->nom . ' ' . $personne->prenom;
            $total = 0;

            foreach ($jours as $jour) {
                foreach ($taches as $tache) {
                    if ($personne->peutFaireTache($tache->id, $jour)) {
                        $total++;
                    }
                }
            }

            $personOptions[$nom] = $total;
        }

        return $personOptions;
    }

    /**
     * Vérifie si une personne est absente à une date donnée.
     * Équivalent de isPersonAbsent() dans DataLoader.js.
     */
    public function isPersonAbsent(string $nomPersonne, Carbon $date, Collection $absences): bool
    {
        $dateStr = $date->toDateString();

        return $absences->contains(function ($absence) use ($nomPersonne, $dateStr) {
            if (! $absence->personne) {
                return false;
            }
            $nom = $absence->personne->nom . ' ' . $absence->personne->prenom;
            return $nom === $nomPersonne
                && $absence->date_debut->toDateString() <= $dateStr
                && $absence->date_fin->toDateString() >= $dateStr;
        });
    }

    /**
     * Retourne les événements actifs à une date donnée.
     * Équivalent de getEventForDate() dans DataLoader.js.
     */
    public function getEvenementsForDate(Carbon $date, Collection $evenements): Collection
    {
        $dateStr = $date->toDateString();

        return $evenements->filter(function ($evenement) use ($dateStr) {
            return $evenement->date_debut->toDateString() <= $dateStr
                && $evenement->date_fin->toDateString() >= $dateStr;
        });
    }
}
