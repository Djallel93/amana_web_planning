<?php
// app/Services/RotationEngine.php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Moteur de rotation des tâches.
 *
 * Traduction PHP du fichier RotationEngine.js (Google Apps Script v9.4).
 * La logique est préservée à l'identique pour garantir les mêmes résultats.
 *
 * Algorithme :
 *  1. amana_food → rotation stricte (cycle global, le moins assigné passe en premier)
 *  2. entree, mektaba, salle, cours → score d'équilibrage avec pénalité adaptative
 */
class RotationEngine
{
    private DataLoader $loader;

    public function __construct(DataLoader $loader)
    {
        $this->loader = $loader;
    }

    /**
     * Assigne un jour complet (5 tâches).
     * Équivalent de assignDay() dans RotationEngine.js.
     *
     * @param string $jour    'Vendredi' ou 'Samedi'
     * @param Carbon $date    Date du créneau
     * @param array  $context Contexte de génération (modifié par référence)
     * @return array ['amana_food' => 'Nom Prenom', 'entree' => '...', ...]
     */
    public function assignDay(string $jour, Carbon $date, array &$context): array
    {
        $assignments = [];
        $dejaAssignes = [];

        // 1. AMANA_FOOD (rotation stricte — priorité absolue)
        $assignments['amana_food'] = $this->assignAmanaFood($jour, $date, $context, $dejaAssignes);
        if ($assignments['amana_food'] !== null) {
            $dejaAssignes[] = $assignments['amana_food'];
        }

        // 2. ENTREE (équilibrage avec pénalité adaptative)
        $assignments['entree'] = $this->assignOtherTask('entree', $jour, $date, $context, $dejaAssignes);
        if ($assignments['entree'] !== null) {
            $dejaAssignes[] = $assignments['entree'];
        }

        // 3. MEKTABA
        $assignments['mektaba'] = $this->assignOtherTask('mektaba', $jour, $date, $context, $dejaAssignes);
        if ($assignments['mektaba'] !== null) {
            $dejaAssignes[] = $assignments['mektaba'];
        }

        // 4. SALLE
        $assignments['salle'] = $this->assignOtherTask('salle', $jour, $date, $context, $dejaAssignes);
        if ($assignments['salle'] !== null) {
            $dejaAssignes[] = $assignments['salle'];
        }

        // 5. COURS
        $assignments['cours'] = $this->assignOtherTask('cours', $jour, $date, $context, $dejaAssignes);
        if ($assignments['cours'] !== null) {
            $dejaAssignes[] = $assignments['cours'];
        }

        return $assignments;
    }

    /**
     * Rotation stricte amana_food.
     * Équivalent de assignAmanaFood() dans RotationEngine.js v9.6.
     *
     * Cycle GLOBAL unique (pas de séparation vendredi/samedi).
     * La personne avec le cycle le plus bas passe en premier.
     * En cas d'égalité, priorité à celle qui n'a pas travaillé depuis le plus longtemps.
     *
     * @return string|null Nom de la personne choisie, ou null si aucun candidat
     */
    private function assignAmanaFood(string $jour, Carbon $date, array &$context, array $dejaAssignes): ?string
    {
        $cycles = &$context['amanaFoodCycles'];
        $personnes = $context['personnes'];

        // Personnes éligibles au cycle amana_food
        $eligibles = $personnes->filter(fn($p) => isset($cycles[$this->nomCle($p)]));

        if ($eligibles->isEmpty()) {
            return null;
        }

        $minCycle = collect($cycles)->filter(
            fn($c, $nom) =>
            $eligibles->contains(fn($p) => $this->nomCle($p) === $nom)
        )->min();

        $candidats = [];

        foreach ($eligibles as $personne) {
            $nom = $this->nomCle($personne);
            $cycle = $cycles[$nom];

            if ($cycle !== $minCycle) {
                continue; // Pas au tour de cette personne
            }

            // Vérifier date_debut_planning
            if ($personne->date_debut_planning && $date->lt($personne->date_debut_planning)) {
                continue;
            }

            // Vérifier absence
            if ($this->loader->isPersonAbsent($nom, $date, $context['absences'])) {
                continue;
            }

            // Vérifier restriction pour amana_food ce jour
            $tacheAmana = $context['taches']->firstWhere('code', 'amana_food');
            if ($tacheAmana && !$personne->peutFaireTache($tacheAmana->id, $jour)) {
                continue;
            }

            // Déjà assigné ce jour ?
            if (in_array($nom, $dejaAssignes, true)) {
                continue;
            }

            // Calcul repos
            $lastWork = $context['lastWorkDate'][$nom] ?? null;
            $daysRest = $lastWork ? (int) Carbon::parse($lastWork)->diffInDays($date) : 999;

            $candidats[] = ['nom' => $nom, 'cycle' => $cycle, 'repos' => $daysRest];
        }

        if (empty($candidats)) {
            return null; // Aucun candidat → tâche non assignée
        }

        // Trier par repos décroissant (plus de repos = priorité)
        usort($candidats, fn($a, $b) => $b['repos'] <=> $a['repos']);

        $choisi = $candidats[0]['nom'];

        // Incrémenter le cycle global
        $cycles[$choisi]++;

        return $choisi;
    }

    /**
     * Assignation des autres tâches avec score d'équilibrage et pénalité adaptative.
     * Équivalent de assignOtherTask() dans RotationEngine.js v9.7.
     *
     * Score = (total_tâches × 10) - (jours_repos × 1) + (compteur_cette_tâche × multiplicateur)
     * Plus le score est BAS, plus la personne est prioritaire.
     *
     * Multiplicateur adaptatif selon le nombre d'options disponibles :
     *  ≥8 options → ×80 (diversité maximale)
     *  ≥6 options → ×60
     *  ≥4 options → ×40
     *  <4 options → ×20
     *
     * @return string|null Nom de la personne choisie, ou null
     */
    private function assignOtherTask(
        string $codeTask,
        string $jour,
        Carbon $date,
        array &$context,
        array $dejaAssignes
    ): ?string {
        $candidats = [];

        // Initialiser l'historique si nécessaire
        if (!isset($context['taskHistory'][$codeTask])) {
            $context['taskHistory'][$codeTask] = [];
        }

        $tache = $context['taches']->firstWhere('code', $codeTask);
        if (!$tache) {
            return null;
        }

        foreach ($context['personnes'] as $personne) {
            $nom = $this->nomCle($personne);

            // Vérifier date_debut_planning
            if ($personne->date_debut_planning && $date->lt($personne->date_debut_planning)) {
                continue;
            }

            // Déjà assigné ce jour ?
            if (in_array($nom, $dejaAssignes, true)) {
                continue;
            }

            // Absent ?
            if ($this->loader->isPersonAbsent($nom, $date, $context['absences'])) {
                continue;
            }

            // Restriction pour cette tâche ce jour ?
            if (!$personne->peutFaireTache($tache->id, $jour)) {
                continue;
            }

            // ── Calcul du score ────────────────────────────────────────────
            $totalTasks = $context['totalTasks'][$nom] ?? 0;
            $lastWork = $context['lastWorkDate'][$nom] ?? null;
            $daysRest = $lastWork
                ? Carbon::parse($lastWork)->diffInDays($date)
                : 999;

            $taskCount = $context['taskHistory'][$codeTask][$nom] ?? 0;
            $numOptions = $context['personOptions'][$nom] ?? 8;

            // Multiplicateur adaptatif
            $penaltyMultiplier = match (true) {
                $numOptions >= 8 => 80,
                $numOptions >= 6 => 60,
                $numOptions >= 4 => 40,
                default => 20,
            };

            $score = ($totalTasks * 10) - ($daysRest * 1) + ($taskCount * $penaltyMultiplier);

            $candidats[] = [
                'nom' => $nom,
                'score' => $score,
                'totalTasks' => $totalTasks,
                'daysRest' => $daysRest,
                'taskCount' => $taskCount,
                'numOptions' => $numOptions,
                'penaltyMultiplier' => $penaltyMultiplier,
            ];
        }

        if (empty($candidats)) {
            return null;
        }

        // Trier par score croissant (plus bas = prioritaire)
        usort($candidats, fn($a, $b) => $a['score'] <=> $b['score']);

        $choisi = $candidats[0]['nom'];

        // Incrémenter le compteur par tâche
        $context['taskHistory'][$codeTask][$choisi] =
            ($context['taskHistory'][$codeTask][$choisi] ?? 0) + 1;

        return $choisi;
    }

    /**
     * Met à jour le contexte après l'assignation d'un jour.
     * Équivalent de updateContextAfterDay() dans SchedulerMain.js v9.4.
     *
     * @param array  $context     Contexte à mettre à jour (par référence)
     * @param array  $assignments Assignations du jour ['amana_food' => 'Nom', ...]
     * @param Carbon $date        Date du créneau
     */
    public function updateContextAfterDay(array &$context, array $assignments, Carbon $date): void
    {
        foreach ($assignments as $codeTask => $nom) {
            if ($nom === null) {
                continue;
            }

            // Mettre à jour lastWorkDate
            $context['lastWorkDate'][$nom] = $date->clone();

            // Mettre à jour totalTasks
            $context['totalTasks'][$nom] = ($context['totalTasks'][$nom] ?? 0) + 1;
        }
    }

    /**
     * Retourne la clé unique d'une personne ("nom prenom").
     */
    private function nomCle(object $personne): string
    {
        return $personne->nom . ' ' . $personne->prenom;
    }
}