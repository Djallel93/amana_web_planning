<?php
// app/Services/DataLoader.php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\DateHelper;
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
 */
class DataLoader
{
    public function initializeContext(string $dateDebut): array
    {
        $personnes = $this->loadActivePersonnes();
        $taches = $this->loadTachesActives();
        $absences = $this->loadAbsences();
        $evenements = $this->loadEvenements();
        $premierVendredi = $this->findPremierVendredi($dateDebut);
        $counters = $this->initializeCountersFromHistory($personnes, $taches, $premierVendredi);
        $personOptions = $this->calculatePersonOptions($personnes, $taches);

        return array_merge([
            'personnes' => $personnes,
            'taches' => $taches,
            'absences' => $absences,
            'evenements' => $evenements,
            'premierVendredi' => $premierVendredi,
            'personOptions' => $personOptions,
        ], $counters);
    }

    public function loadActivePersonnes(): Collection
    {
        return Personne::actifAuPlanning()
            ->with(['restrictions.tache'])
            ->orderBy('nom')
            ->get();
    }

    public function loadTachesActives(): Collection
    {
        return Tache::actif()->orderBy('id')->get();
    }

    public function loadAbsences(): Collection
    {
        // Inclut aussi les absences déjà terminées (jusqu'à 1 an en arrière) :
        // nécessaire pour exclure les jours d'absence passés du calcul des
        // "jours de repos" (RotationEngine::calculerJoursRepos) quand une
        // personne reprend après un congé — pas seulement les absences
        // encore actives ou futures.
        return Absence::with('personne')
            ->where('date_fin', '>=', now()->subYear()->toDateString())
            ->get();
    }

    /**
     * Charge les événements avec leurs tâches bloquées (eager-loaded).
     * Le SchedulerMain utilise evenement->tachesBloquees directement.
     */
    public function loadEvenements(): Collection
    {
        return Evenement::with('tachesBloquees')
            ->futursOuEnCours()
            ->get();
    }

    /**
     * Délègue à DateHelper pour rester compatible avec les appelants existants
     * (notamment SchedulerMain qui lit $context['premierVendredi']).
     */
    public function findPremierVendredi(string $dateDebut): Carbon
    {
        return DateHelper::premierVendredi($dateDebut);
    }

    /**
     * @param Carbon|null $avant Si fourni, seuls les créneaux STRICTEMENT
     *                           antérieurs à cette date alimentent les
     *                           compteurs (historique réel). Sans cette
     *                           borne, les créneaux déjà générés à partir de
     *                           $dateDebut — sur le point d'être supprimés
     *                           par cleanExistingCreneaux() puis régénérés —
     *                           seraient comptés une première fois ici comme
     *                           "historique", puis une seconde fois lors de
     *                           leur réassignation par RotationEngine,
     *                           faussant l'équilibrage pour toute la fenêtre
     *                           régénérée.
     */
    public function initializeCountersFromHistory(Collection $personnes, Collection $taches, ?Carbon $avant = null): array
    {
        $amanaFood = $taches->firstWhere('code', 'amana_food');

        $amanaFoodCycles = [];
        $lastWorkDate = [];
        $totalTasks = [];
        $taskHistory = [];

        foreach ($taches as $tache) {
            $taskHistory[$tache->code] = [];
        }

        foreach ($personnes as $personne) {
            $nom = $personne->nom . ' ' . $personne->prenom;

            if ($amanaFood) {
                $peutVendredi = $personne->peutFaireTache($amanaFood->id, 'Vendredi');
                $peutSamedi = $personne->peutFaireTache($amanaFood->id, 'Samedi');
                if ($peutVendredi || $peutSamedi) {
                    $amanaFoodCycles[$nom] = 0;
                }
            }

            $lastWorkDate[$nom] = null;
            $totalTasks[$nom] = 0;

            foreach ($taches as $tache) {
                $taskHistory[$tache->code][$nom] = 0;
            }
        }

        $historique = CreneauTache::with(['creneau', 'tache', 'personne'])
            ->whereNotNull('id_personne')
            ->when($avant !== null, function ($query) use ($avant) {
                $query->whereHas('creneau', function ($q) use ($avant) {
                    $q->where('date', '<', $avant->toDateString());
                });
            })
            ->get();

        foreach ($historique as $ligne) {
            if (!$ligne->personne || !$ligne->tache || !$ligne->creneau) {
                continue;
            }

            $nom = $ligne->personne->nom . ' ' . $ligne->personne->prenom;
            $code = $ligne->tache->code;
            $date = $ligne->creneau->date;

            if (!isset($lastWorkDate[$nom]) || $date > $lastWorkDate[$nom]) {
                $lastWorkDate[$nom] = $date;
            }

            $totalTasks[$nom] = ($totalTasks[$nom] ?? 0) + 1;

            if (isset($taskHistory[$code][$nom])) {
                $taskHistory[$code][$nom]++;
            }

            if ($code === 'amana_food' && isset($amanaFoodCycles[$nom])) {
                $amanaFoodCycles[$nom]++;
            }
        }

        return compact('amanaFoodCycles', 'lastWorkDate', 'totalTasks', 'taskHistory');
    }

    public function calculatePersonOptions(Collection $personnes, Collection $taches): array
    {
        $personOptions = [];
        $jours = ['Vendredi', 'Samedi'];

        foreach ($personnes as $personne) {
            $nom = $personne->nom . ' ' . $personne->prenom;
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

    public function isPersonAbsent(string $nomPersonne, Carbon $date, Collection $absences): bool
    {
        $dateStr = $date->toDateString();

        return $absences->contains(function ($absence) use ($nomPersonne, $dateStr) {
            if (!$absence->personne) {
                return false;
            }
            $nom = $absence->personne->nom . ' ' . $absence->personne->prenom;
            return $nom === $nomPersonne
                && $absence->date_debut->toDateString() <= $dateStr
                && $absence->date_fin->toDateString() >= $dateStr;
        });
    }

    public function getEvenementsForDate(Carbon $date, Collection $evenements): Collection
    {
        $dateStr = $date->toDateString();

        return $evenements->filter(function ($evenement) use ($dateStr) {
            return $evenement->date_debut->toDateString() <= $dateStr
                && $evenement->date_fin->toDateString() >= $dateStr;
        });
    }
}