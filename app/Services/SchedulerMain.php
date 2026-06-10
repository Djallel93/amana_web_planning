<?php
// app/Services/SchedulerMain.php

declare(strict_types=1);

namespace App\Services;

use App\Models\Creneau;
use App\Models\CreneauTache;
use App\Models\Tache;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service principal de génération du planning.
 * Orchestre DataLoader et RotationEngine.
 */
class SchedulerMain
{
    public function __construct(
        private readonly DataLoader $loader,
        private readonly RotationEngine $engine,
    ) {
    }

    /**
     * Génère le planning pour un nombre de semaines donné.
     */
    public function generateSchedule(string $dateDebut, int $semaines): array
    {
        $debut = microtime(true);
        Log::info("[Scheduler] Début génération — date: {$dateDebut}, semaines: {$semaines}");

        $context = $this->loader->initializeContext($dateDebut);

        if ($context['personnes']->isEmpty()) {
            throw new \RuntimeException('Aucune personne active dans le planning.');
        }

        $premiereDate = $context['premierVendredi'];
        Log::info("[Scheduler] Premier vendredi : {$premiereDate->toDateString()}");

        $this->cleanExistingCreneaux($premiereDate);

        $joursGeneres = 0;
        $nonAssignes = 0;

        DB::transaction(function () use ($semaines, $premiereDate, &$context, &$joursGeneres, &$nonAssignes) {
            for ($semaine = 0; $semaine < $semaines; $semaine++) {
                $vendredi = $premiereDate->copy()->addWeeks($semaine);
                $samedi = $vendredi->copy()->addDay();

                [$nv, $na] = $this->generateDay($vendredi, 'Vendredi', $context);
                $joursGeneres += $nv;
                $nonAssignes += $na;

                [$ns, $na2] = $this->generateDay($samedi, 'Samedi', $context);
                $joursGeneres += $ns;
                $nonAssignes += $na2;
            }
        });

        $duree = round((microtime(true) - $debut) * 1000, 0);
        Log::info("[Scheduler] Génération terminée en {$duree}ms — {$joursGeneres} jours, {$nonAssignes} non assignés");

        return [
            'jours_generes' => $joursGeneres,
            'non_assignes' => $nonAssignes,
            'duree_ms' => $duree,
        ];
    }

    /**
     * Génère un jour : crée le créneau et ses assignations.
     *
     * Pour chaque tâche, on vérifie si un événement actif la bloque.
     * Si oui → id_personne = null pour cette tâche spécifiquement.
     * Si toutes les tâches sont bloquées → créneau entièrement vide.
     */
    private function generateDay(Carbon $date, string $jourNom, array &$context): array
    {
        $evenementsActifs = $this->loader->getEvenementsForDate($date, $context['evenements']);

        // Construire l'ensemble des codes de tâches bloquées par les événements actifs
        $tachesBloquees = collect();
        foreach ($evenementsActifs as $evenement) {
            // tachesBloquees est eager-loadé via loadEvenements()
            foreach ($evenement->tachesBloquees as $tache) {
                $tachesBloquees->push($tache->code);
            }
        }
        $tachesBloquees = $tachesBloquees->unique();

        $creneau = Creneau::firstOrCreate(['date' => $date->toDateString()]);

        // Lier les événements actifs à ce créneau
        foreach ($evenementsActifs as $evenement) {
            $creneau->evenements()->syncWithoutDetaching([$evenement->id]);
        }

        $nonAssignes = 0;

        // Si tous les événements bloquent toutes les tâches actives, ou si au moins
        // un événement bloque des tâches, on gère tâche par tâche
        $toutBloque = $tachesBloquees->count() >= $context['taches']->count()
            && $tachesBloquees->isNotEmpty();

        if ($toutBloque) {
            // Toutes les tâches bloquées → aucune assignation
            foreach ($context['taches'] as $tache) {
                CreneauTache::updateOrCreate(
                    ['id_planning' => $creneau->id, 'id_tache' => $tache->id],
                    ['id_personne' => null]
                );
                $nonAssignes++;
            }
            return [1, $nonAssignes];
        }

        // Assignation normale, mais on bloque les tâches concernées
        $assignments = $this->engine->assignDay($jourNom, $date, $context);

        foreach ($context['taches'] as $tache) {
            // Tâche bloquée par un événement → pas d'assignation
            if ($tachesBloquees->contains($tache->code)) {
                CreneauTache::updateOrCreate(
                    ['id_planning' => $creneau->id, 'id_tache' => $tache->id],
                    ['id_personne' => null]
                );
                $nonAssignes++;
                continue;
            }

            $nomAssigne = $assignments[$tache->code] ?? null;
            $personneId = null;

            if ($nomAssigne !== null) {
                $personne = $context['personnes']->first(
                    fn($p) => ($p->nom . ' ' . $p->prenom) === $nomAssigne
                );
                $personneId = $personne?->id;
            }

            if ($personneId === null) {
                $nonAssignes++;
            }

            CreneauTache::updateOrCreate(
                ['id_planning' => $creneau->id, 'id_tache' => $tache->id],
                ['id_personne' => $personneId]
            );
        }

        $this->engine->updateContextAfterDay($context, $assignments, $date);

        return [1, $nonAssignes];
    }

    private function cleanExistingCreneaux(Carbon $depuis): void
    {
        $nb = Creneau::where('date', '>=', $depuis->toDateString())->count();

        if ($nb > 0) {
            Log::info("[Scheduler] Suppression de {$nb} créneaux existants depuis {$depuis->toDateString()}");
            Creneau::where('date', '>=', $depuis->toDateString())->delete();
        }
    }
}