<?php
// app/Services/Statistics.php

declare(strict_types=1);

namespace App\Services;

use App\Models\Creneau;
use App\Models\CreneauTache;
use App\Models\Absence;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Service de calcul des statistiques du planning.
 * Équivalent PHP de Statistics.js (Google Apps Script).
 */
class Statistics
{
    /**
     * Calcule toutes les statistiques du planning.
     *
     * @return array Tableau complet des métriques, prêt à passer à la vue Blade
     */
    public function computeAll(): array
    {
        // Charger toutes les assignations avec leurs relations
        $lignes = CreneauTache::with(['creneau', 'tache', 'personne'])
            ->whereNotNull('id_personne')
            ->whereHas('creneau')
            ->get();

        if ($lignes->isEmpty()) {
            return $this->emptyStats();
        }

        $taskCounts    = [];  // ['Dupont Jean' => int]
        $dayCounts     = [];  // ['Dupont Jean' => ['vendredis' => int, 'samedis' => int]]
        $tasksByPerson = [];  // ['Dupont Jean' => ['entree' => int, ...]]
        $workDays      = [];  // ['Dupont Jean' => [0, 1, 2, ...]] (indices de lignes)

        // Trier par date pour l'analyse des jours consécutifs
        $lignes = $lignes->sortBy(fn($l) => $l->creneau->date);

        // Grouper par créneau pour compter les indices de position
        $creneauxParDate = $lignes->groupBy(fn($l) => $l->creneau->date->toDateString());
        $indexParDate    = [];
        $i = 0;
        foreach ($creneauxParDate as $date => $_) {
            $indexParDate[$date] = $i++;
        }

        foreach ($lignes as $ligne) {
            if (! $ligne->personne || ! $ligne->tache || ! $ligne->creneau) {
                continue;
            }

            $nom     = $ligne->personne->nom . ' ' . $ligne->personne->prenom;
            $code    = $ligne->tache->code;
            $jour    = $ligne->creneau->jour;  // Accesseur du modèle
            $dateIdx = $indexParDate[$ligne->creneau->date->toDateString()] ?? 0;

            // Compteurs par tâche (toujours renseigné, y compris "cours" —
            // affiché dans le détail par personne, indépendamment de son
            // exclusion des métriques d'équité ci-dessous).
            $tasksByPerson[$nom] ??= ['entree' => 0, 'mektaba' => 0, 'salle' => 0, 'amana_food' => 0, 'cours' => 0];
            $tasksByPerson[$nom][$code] = ($tasksByPerson[$nom][$code] ?? 0) + 1;

            // "cours" est exclu des métriques d'équité (taskCounts, dayCounts,
            // jours consécutifs) : par convention une seule personne l'assure
            // chaque semaine (voir RotationEngine::assignCours), donc ce
            // créneau fixe ne doit pas être traité comme un signal de
            // déséquilibre de rotation ni de fatigue (jours consécutifs).
            if ($code === 'cours') {
                continue;
            }

            // Compteurs totaux
            $taskCounts[$nom] = ($taskCounts[$nom] ?? 0) + 1;

            // Compteurs par jour
            $dayCounts[$nom] ??= ['vendredis' => 0, 'samedis' => 0];
            if ($jour === 'Vendredi') $dayCounts[$nom]['vendredis']++;
            if ($jour === 'Samedi')   $dayCounts[$nom]['samedis']++;

            // Jours de travail (pour calcul consécutifs)
            $workDays[$nom][] = $dateIdx;
        }

        // Si quelqu'un n'a jamais fait que "cours" (exclu ci-dessus des
        // métriques d'équité), il doit tout de même apparaître dans le
        // tableau des statistiques — avec 0 tâche de rotation comptabilisée.
        foreach (array_keys($tasksByPerson) as $nom) {
            $taskCounts[$nom] ??= 0;
        }

        // Calcul des jours consécutifs max
        $consecutiveDays = [];
        foreach ($workDays as $nom => $jours) {
            $jours = array_unique($jours);
            sort($jours);
            $maxConsecutif  = 1;
            $streakActuelle = 1;
            for ($j = 1; $j < count($jours); $j++) {
                if ($jours[$j] - $jours[$j - 1] === 1) {
                    $streakActuelle++;
                    $maxConsecutif = max($maxConsecutif, $streakActuelle);
                } else {
                    $streakActuelle = 1;
                }
            }
            $consecutiveDays[$nom] = count($jours) > 0 ? $maxConsecutif : 0;
        }

        // Calcul des absences (vendredis/samedis) dans la période du planning
        $dateMin    = $lignes->min(fn($l) => $l->creneau->date);
        $dateMax    = $lignes->max(fn($l) => $l->creneau->date);
        $absences   = Absence::with('personne')
            ->where('date_fin', '>=', $dateMin)
            ->where('date_debut', '<=', $dateMax)
            ->get();
        $absenceDays = [];
        $personnes   = array_keys($taskCounts);
        foreach ($personnes as $nom) {
            $nb = $this->countAbsenceDays($nom, $dateMin, $dateMax, $absences);
            if ($nb > 0) {
                $absenceDays[$nom] = $nb;
            }
        }

        // ── Métriques globales ──────────────────────────────────────────────
        $totalTasks  = array_sum($taskCounts);
        $nbPersonnes = count($taskCounts);
        $valeurs     = array_values($taskCounts);

        $moyenne     = $nbPersonnes > 0 ? $totalTasks / $nbPersonnes : 0;
        $variance    = 0;
        foreach ($valeurs as $v) {
            $variance += ($v - $moyenne) ** 2;
        }
        $variance       = $nbPersonnes > 0 ? $variance / $nbPersonnes : 0;
        $ecartType      = sqrt($variance);
        $coeffVariation = $moyenne > 0 ? ($ecartType / $moyenne) * 100 : 0;

        // Déséquilibre vendredi/samedi
        $desequilibreTotalVS = 0;
        foreach ($dayCounts as $dc) {
            $desequilibreTotalVS += abs(($dc['vendredis'] ?? 0) - ($dc['samedis'] ?? 0));
        }
        $desequilibreMoyen = $nbPersonnes > 0 ? $desequilibreTotalVS / $nbPersonnes : 0;

        // Taux d'utilisation
        $totalSlots     = count($creneauxParDate) * 4; // 4 tâches par créneau
        $tauxUtil       = $totalSlots > 0 ? ($totalTasks / $totalSlots) * 100 : 0;

        // Distribution amana_food
        $amanaFoodCounts = array_map(fn($t) => $t['amana_food'] ?? 0, $tasksByPerson);
        $minAmana = ! empty($amanaFoodCounts) ? min($amanaFoodCounts) : 0;
        $maxAmana = ! empty($amanaFoodCounts) ? max($amanaFoodCounts) : 0;
        $avgAmana = $nbPersonnes > 0 ? array_sum($amanaFoodCounts) / $nbPersonnes : 0;

        // Score d'équité global (même formule que Statistics.js)
        $persAvecHautConsec = count(array_filter($consecutiveDays, fn($c) => $c > 2));
        $fairnessScore      = 100;
        $fairnessScore      -= min((int) $coeffVariation, 30);
        $fairnessScore      -= min($persAvecHautConsec * 5, 20);
        $fairnessScore      -= min((int) ($desequilibreMoyen * 3), 20);
        $fairnessScore      = max(0, $fairnessScore);

        // Trier les personnes par total décroissant
        arsort($taskCounts);

        return [
            'personnes'             => array_keys($taskCounts),
            'taskCounts'            => $taskCounts,
            'dayCounts'             => $dayCounts,
            'tasksByPerson'         => $tasksByPerson,
            'consecutiveDays'       => $consecutiveDays,
            'absenceDays'           => $absenceDays,
            'totalDays'             => count($creneauxParDate),
            'dateDebut'             => $dateMin instanceof Carbon ? $dateMin->toDateString() : (string) $dateMin,
            'dateFin'               => $dateMax instanceof Carbon ? $dateMax->toDateString() : (string) $dateMax,
            'totalTasks'            => $totalTasks,
            'nbPersonnes'           => $nbPersonnes,
            'moyenneTaches'         => round($moyenne, 1),
            'minTaches'             => ! empty($valeurs) ? min($valeurs) : 0,
            'maxTaches'             => ! empty($valeurs) ? max($valeurs) : 0,
            'ecartType'             => round($ecartType, 2),
            'coefficientVariation'  => round($coeffVariation, 1),
            'desequilibreMoyen'     => round($desequilibreMoyen, 1),
            'tauxUtilisation'       => round($tauxUtil, 1),
            'minAmanaFood'          => $minAmana,
            'maxAmanaFood'          => $maxAmana,
            'avgAmanaFood'          => round($avgAmana, 1),
            'maxConsecutif'         => ! empty($consecutiveDays) ? max($consecutiveDays) : 0,
            'persAvecHautConsec'    => $persAvecHautConsec,
            'totalAbsenceDays'      => array_sum($absenceDays),
            'nbPersonnesAbsentes'   => count($absenceDays),
            'fairnessScore'         => $fairnessScore,
        ];
    }

    /**
     * Compte les vendredis/samedis d'absence pour une personne dans une période.
     */
    private function countAbsenceDays(string $nom, $debut, $fin, Collection $absences): int
    {
        $count   = 0;
        $current = Carbon::parse($debut)->startOfDay();
        $end     = Carbon::parse($fin)->startOfDay();

        while ($current->lte($end)) {
            // Uniquement vendredis (5) et samedis (6)
            if (in_array($current->dayOfWeek, [5, 6], true)) {
                $dateStr = $current->toDateString();
                $absent  = $absences->contains(function ($a) use ($nom, $dateStr) {
                    if (! $a->personne) return false;
                    $nomAbs = $a->personne->nom . ' ' . $a->personne->prenom;
                    return $nomAbs === $nom
                        && $a->date_debut->toDateString() <= $dateStr
                        && $a->date_fin->toDateString() >= $dateStr;
                });
                if ($absent) $count++;
            }
            $current->addDay();
        }

        return $count;
    }

    /** Retourne un tableau vide quand il n'y a pas encore de planning. */
    private function emptyStats(): array
    {
        return [
            'personnes'            => [],
            'taskCounts'           => [],
            'dayCounts'            => [],
            'tasksByPerson'        => [],
            'consecutiveDays'      => [],
            'absenceDays'          => [],
            'totalDays'            => 0,
            'dateDebut'            => null,
            'dateFin'              => null,
            'totalTasks'           => 0,
            'nbPersonnes'          => 0,
            'moyenneTaches'        => 0,
            'minTaches'            => 0,
            'maxTaches'            => 0,
            'ecartType'            => 0,
            'coefficientVariation' => 0,
            'desequilibreMoyen'    => 0,
            'tauxUtilisation'      => 0,
            'minAmanaFood'         => 0,
            'maxAmanaFood'         => 0,
            'avgAmanaFood'         => 0,
            'maxConsecutif'        => 0,
            'persAvecHautConsec'   => 0,
            'totalAbsenceDays'     => 0,
            'nbPersonnesAbsentes'  => 0,
            'fairnessScore'        => 0,
        ];
    }
}
