<?php
// app/Http/Controllers/PlanningController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Planning\PlanningGenerateRequest;
use App\Models\Creneau;
use App\Services\SchedulerMain;
use App\Services\Statistics;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Contrôleur du module Planning.
 * Gère l'affichage, la génération et les statistiques.
 */
class PlanningController extends Controller
{
    public function __construct(
        private readonly SchedulerMain $scheduler,
        private readonly Statistics    $stats,
    ) {}

    /**
     * Affiche le planning par semaines.
     */
    public function index(): View
    {
        $creneaux = Creneau::with(['taches.tache', 'taches.personne', 'evenements'])
            ->orderBy('date')
            ->get()
            ->groupBy(fn($c) => $c->date->isoWeek() . '-' . $c->date->year);

        return view('planning.index', compact('creneaux'));
    }

    /**
     * Affiche le formulaire de génération.
     */
    public function showGenerateForm(): View
    {
        return view('planning.generate');
    }

    /**
     * Lance la génération du planning.
     */
    public function generate(PlanningGenerateRequest $request): RedirectResponse
    {
        try {
            $resultat = $this->scheduler->generateSchedule(
                $request->validated('date_debut'),
                (int) $request->validated('semaines')
            );

            audit('generate', 'planning', null, null, $resultat);

            return redirect()->route('planning.index')
                ->with('success', "Planning généré avec succès : {$resultat['jours_generes']} jours créés en {$resultat['duree_ms']}ms. ({$resultat['non_assignes']} tâche(s) non assignée(s))");

        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Erreur lors de la génération : ' . $e->getMessage());
        }
    }

    /**
     * Affiche les statistiques du planning.
     */
    public function statistics(): View
    {
        $stats = $this->stats->computeAll();
        return view('statistics.index', compact('stats'));
    }
}
