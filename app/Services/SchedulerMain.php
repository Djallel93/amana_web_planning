<?php
// app/Services/SchedulerMain.php

declare(strict_types=1);

namespace App\Services;

use App\Models\Creneau;
use App\Models\CreneauTache;
use App\Models\Personne;
use App\Models\Tache;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service principal de génération du planning.
 * Orchestre DataLoader et RotationEngine.
 * Équivalent PHP de SchedulerMain.js.
 */
class SchedulerMain
{
    public function __construct(
        private readonly DataLoader    $loader,
        private readonly RotationEngine $engine,
    ) {}

    /**
     * Génère le planning pour un nombre de semaines donné.
     *
     * @param string $dateDebut  Date de début au format Y-m-d
     * @param int    $semaines   Nombre de semaines à générer
     * @return array             Résumé : ['jours_generes' => int, 'non_assignes' => int]
     * @throws \Exception        En cas d'erreur
     */
    public function generateSchedule(string $dateDebut, int $semaines): array
    {
        $debut = microtime(true);
        Log::info("[Scheduler] Début génération — date: {$dateDebut}, semaines: {$semaines}");

        // 1. Charger le contexte (personnes, restrictions, absences, historique)
        $context = $this->loader->initializeContext($dateDebut);

        if ($context['personnes']->isEmpty()) {
            throw new \RuntimeException('Aucune personne active dans le planning. Vérifiez les membres avec statut "Validé" et une date de début.');
        }

        $premiereDate = $context['premierVendredi'];
        Log::info("[Scheduler] Premier vendredi : {$premiereDate->toDateString()}");

        // 2. Supprimer les créneaux existants à partir de la date de début
        $this->cleanExistingCreneaux($premiereDate);

        // 3. Générer les semaines dans une transaction DB
        $joursGeneres = 0;
        $nonAssignes  = 0;

        DB::transaction(function () use ($semaines, $premiereDate, &$context, &$joursGeneres, &$nonAssignes) {

            for ($semaine = 0; $semaine < $semaines; $semaine++) {

                // Dates de cette semaine
                $vendredi = $premiereDate->copy()->addWeeks($semaine);
                $samedi   = $vendredi->copy()->addDay();

                // Générer vendredi
                [$nv, $na] = $this->generateDay($vendredi, 'Vendredi', $context);
                $joursGeneres += $nv;
                $nonAssignes  += $na;

                // Mettre à jour le contexte avec les assignations du vendredi
                // (fait dans generateDay via updateContextAfterDay)

                // Générer samedi
                [$ns, $na2] = $this->generateDay($samedi, 'Samedi', $context);
                $joursGeneres += $ns;
                $nonAssignes  += $na2;

                if (($semaine + 1) % 10 === 0) {
                    Log::info("[Scheduler] Progression : " . ($semaine + 1) . "/{$semaines} semaines");
                }
            }
        });

        $duree = round((microtime(true) - $debut) * 1000, 0);
        Log::info("[Scheduler] Génération terminée en {$duree}ms — {$joursGeneres} jours, {$nonAssignes} non assignés");

        return [
            'jours_generes' => $joursGeneres,
            'non_assignes'  => $nonAssignes,
            'duree_ms'      => $duree,
        ];
    }

    /**
     * Génère un jour (vendredi ou samedi) : crée le créneau et ses 4 assignations.
     *
     * @return array [nb_jours_generes, nb_non_assignes]
     */
    private function generateDay(Carbon $date, string $jourNom, array &$context): array
    {
        // Vérifier si un événement bloque ce jour
        $evenementsActifs = $this->loader->getEvenementsForDate($date, $context['evenements']);
        $bloque = $evenementsActifs->contains(fn($e) => $e->bloque_planning);

        // Créer ou récupérer le créneau
        $creneau = Creneau::firstOrCreate(['date' => $date->toDateString()]);

        // Lier les événements actifs à ce créneau (table de jonction)
        foreach ($evenementsActifs as $evenement) {
            $creneau->evenements()->syncWithoutDetaching([$evenement->id]);
        }

        $nonAssignes = 0;

        if ($bloque) {
            // Jour bloqué : créer les lignes de tâches sans personne
            foreach ($context['taches'] as $tache) {
                CreneauTache::updateOrCreate(
                    ['id_planning' => $creneau->id, 'id_tache' => $tache->id],
                    ['id_personne' => null]
                );
                $nonAssignes++;
            }
            return [1, $nonAssignes];
        }

        // Assigner les 4 tâches via le moteur de rotation
        $assignments = $this->engine->assignDay($jourNom, $date, $context);

        // Persister les assignations
        foreach ($context['taches'] as $tache) {
            $nomAssigne = $assignments[$tache->code] ?? null;

            // Résoudre le nom vers un ID personne
            $personneId = null;
            if ($nomAssigne !== null) {
                $personne = $context['personnes']->first(function ($p) use ($nomAssigne) {
                    return ($p->nom . ' ' . $p->prenom) === $nomAssigne;
                });
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

        // Mettre à jour le contexte pour les prochaines assignations
        $this->engine->updateContextAfterDay($context, $assignments, $date);

        return [1, $nonAssignes];
    }

    /**
     * Supprime les créneaux existants à partir d'une date (incluse).
     * Les tâches associées sont supprimées en cascade (FK).
     */
    private function cleanExistingCreneaux(Carbon $depuis): void
    {
        $nb = Creneau::where('date', '>=', $depuis->toDateString())->count();

        if ($nb > 0) {
            Log::info("[Scheduler] Suppression de {$nb} créneaux existants depuis {$depuis->toDateString()}");
            Creneau::where('date', '>=', $depuis->toDateString())->delete();
        }
    }
}
