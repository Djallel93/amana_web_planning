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
 *
 * En mode dry-run ($dryRun = true) :
 *   - Aucune donnée n'est persistée (transaction rollback automatique)
 *   - Les créneaux existants ne sont PAS supprimés
 *   - La méthode retourne le tableau des assignations proposées au lieu des compteurs
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
     *
     * @param  string $dateDebut  Date de début (YYYY-MM-DD)
     * @param  int    $semaines   Nombre de semaines à générer
     * @param  bool   $dryRun     Si true : prévisualisation sans persistance
     *
     * @return array  En mode normal : ['jours_generes', 'non_assignes', 'duree_ms']
     *                En mode dry-run : ['creneaux' => [...], 'duree_ms' => ...]
     */
    public function generateSchedule(string $dateDebut, int $semaines, bool $dryRun = false): array
    {
        $debut = microtime(true);
        Log::info("[Scheduler] Début " . ($dryRun ? "DRY-RUN" : "génération") . " — date: {$dateDebut}, semaines: {$semaines}");

        $context = $this->loader->initializeContext($dateDebut);

        if ($context['personnes']->isEmpty()) {
            throw new \RuntimeException('Aucune personne active dans le planning.');
        }

        $premiereDate = $context['premierVendredi'];
        Log::info("[Scheduler] Premier vendredi : {$premiereDate->toDateString()}");

        // ── Mode normal : supprime les créneaux existants ─────────────────
        if (!$dryRun) {
            $this->cleanExistingCreneaux($premiereDate);
        }

        $joursGeneres = 0;
        $nonAssignes = 0;
        $creneauxDryRun = [];

        // ── Exécution dans une transaction ────────────────────────────────
        // En dry-run, on rollback après pour ne rien persister.
        DB::beginTransaction();

        try {
            for ($semaine = 0; $semaine < $semaines; $semaine++) {
                $vendredi = $premiereDate->copy()->addWeeks($semaine);
                $samedi = $vendredi->copy()->addDay();

                [$nv, $naV, $propositionsV] = $this->generateDay($vendredi, 'Vendredi', $context, $dryRun);
                $joursGeneres += $nv;
                $nonAssignes += $naV;
                if ($dryRun) {
                    $creneauxDryRun[] = $propositionsV;
                }

                [$ns, $naS, $propositionsS] = $this->generateDay($samedi, 'Samedi', $context, $dryRun);
                $joursGeneres += $ns;
                $nonAssignes += $naS;
                if ($dryRun) {
                    $creneauxDryRun[] = $propositionsS;
                }
            }

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }

        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $duree = round((microtime(true) - $debut) * 1000, 0);
        Log::info("[Scheduler] " . ($dryRun ? "DRY-RUN" : "Génération") . " terminée en {$duree}ms — {$joursGeneres} jours, {$nonAssignes} non assignés");

        if ($dryRun) {
            return [
                'creneaux' => array_filter($creneauxDryRun), // retire les null
                'duree_ms' => $duree,
                'non_assignes' => $nonAssignes,
            ];
        }

        return [
            'jours_generes' => $joursGeneres,
            'non_assignes' => $nonAssignes,
            'duree_ms' => $duree,
        ];
    }

    /**
     * Génère un jour : crée le créneau et ses assignations.
     *
     * Retourne un tableau de 3 éléments :
     *   [int $joursGeneres, int $nonAssignes, array|null $propositions]
     *
     * $propositions est renseigné uniquement en dry-run.
     */
    private function generateDay(Carbon $date, string $jourNom, array &$context, bool $dryRun): array
    {
        $evenementsActifs = $this->loader->getEvenementsForDate($date, $context['evenements']);

        // Codes de tâches bloquées par les événements actifs
        $tachesBloquees = collect();
        foreach ($evenementsActifs as $evenement) {
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
        $propositions = null;

        $toutBloque = $tachesBloquees->count() >= $context['taches']->count()
            && $tachesBloquees->isNotEmpty();

        if ($toutBloque) {
            foreach ($context['taches'] as $tache) {
                CreneauTache::updateOrCreate(
                    ['id_planning' => $creneau->id, 'id_tache' => $tache->id],
                    ['id_personne' => null]
                );
                $nonAssignes++;
            }

            if ($dryRun) {
                $propositions = $this->buildDryRunProposition($date, $jourNom, $creneau, $context, [], $tachesBloquees, $evenementsActifs);
            }

            return [1, $nonAssignes, $propositions];
        }

        $assignments = $this->engine->assignDay($jourNom, $date, $context);

        foreach ($context['taches'] as $tache) {
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

        if ($dryRun) {
            $propositions = $this->buildDryRunProposition($date, $jourNom, $creneau, $context, $assignments, $tachesBloquees, $evenementsActifs);
        }

        return [1, $nonAssignes, $propositions];
    }

    /**
     * Construit la structure de données de prévisualisation pour un jour.
     * Utilisée uniquement en dry-run pour alimenter la vue preview.
     */
    private function buildDryRunProposition(
        Carbon $date,
        string $jourNom,
        Creneau $creneau,
        array $context,
        array $assignments,
        \Illuminate\Support\Collection $tachesBloquees,
        \Illuminate\Support\Collection $evenementsActifs
    ): array {
        $tachesData = [];

        foreach ($context['taches'] as $tache) {
            $bloquee = $tachesBloquees->contains($tache->code);

            if ($bloquee) {
                $tachesData[$tache->code] = [
                    'libelle' => $tache->libelle,
                    'bloquee' => true,
                    'personne' => null,
                    'nom_complet' => null,
                ];
                continue;
            }

            $nomAssigne = $assignments[$tache->code] ?? null;
            $personne = null;

            if ($nomAssigne !== null) {
                $personne = $context['personnes']->first(
                    fn($p) => ($p->nom . ' ' . $p->prenom) === $nomAssigne
                );
            }

            $tachesData[$tache->code] = [
                'libelle' => $tache->libelle,
                'bloquee' => false,
                'personne' => $personne,
                'nom_complet' => $personne ? $personne->prenom . ' ' . $personne->nom : null,
            ];
        }

        return [
            'date' => $date->toDateString(),
            'jour' => $jourNom,
            'semaine' => (int) $date->isoWeek(),
            'date_label' => $date->locale('fr')->isoFormat('D MMMM YYYY'),
            'evenements' => $evenementsActifs->pluck('nom')->implode(', ') ?: null,
            'taches' => $tachesData,
        ];
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