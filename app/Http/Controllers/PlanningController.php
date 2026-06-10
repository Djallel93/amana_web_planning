<?php
// app/Http/Controllers/PlanningController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Planning\PlanningExportRequest;
use App\Http\Requests\Planning\PlanningGenerateRequest;
use App\Models\Creneau;
use App\Services\SchedulerMain;
use App\Services\Statistics;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;

/**
 * Contrôleur du module Planning.
 */
class PlanningController extends Controller
{
    public function __construct(
        private readonly SchedulerMain $scheduler,
        private readonly Statistics $stats,
    ) {
    }

    /**
     * Affiche le planning.
     *
     * Par défaut : 1 an glissant (aujourd'hui - 365 jours → futur).
     * Avec ?historique=1 : tout l'historique.
     */
    public function index(Request $request): View
    {
        $historique = $request->boolean('historique');

        $query = Creneau::with(['taches.tache', 'taches.personne', 'evenements.tachesBloquees'])
            ->orderBy('date', 'desc');

        if (!$historique) {
            $dateMin = now()->subYear()->toDateString();
            $query->where('date', '>=', $dateMin);
        }

        $creneaux = $query->get()
            ->groupBy(fn($c) => $c->date->isoWeek() . '-' . $c->date->year);

        // Charger tous les événements de la période pour les bannières informatives
        // (événements sans tâches bloquées, ou sur des jours non-vendredi/samedi)
        $evenementsQuery = \App\Models\Evenement::with('tachesBloquees')
            ->orderBy('date_debut');

        if (!$historique) {
            $evenementsQuery->where('date_fin', '>=', now()->subYear()->toDateString());
        }

        $tousEvenements = $evenementsQuery->get();

        // Construire les bannières informatives : événements actifs par semaine ISO
        // Un événement informatif (aucune tâche bloquée) ou couvrant un jour hors Ven/Sam
        // est affiché comme bannière dans le bloc semaine
        $bannièresParSemaine = $this->buildBannièresInformatives($tousEvenements, $creneaux);

        return view('planning.index', compact('creneaux', 'historique', 'bannièresParSemaine'));
    }

    /**
     * Construit les bannières informatives à afficher dans les blocs semaine.
     *
     * Une bannière est affichée pour un événement si :
     * - Il n'a aucune tâche bloquée (purement informatif), OU
     * - Il couvre au moins un jour dans la semaine (pour info visuelle)
     *
     * Retourne un tableau indexé par clé semaine ('isoWeek-year') => [evenements]
     */
    private function buildBannièresInformatives(
        \Illuminate\Support\Collection $tousEvenements,
        \Illuminate\Support\Collection $creneauxParSemaine
    ): array {
        $bannières = [];

        // Récupérer toutes les clés semaine présentes dans le planning
        foreach ($creneauxParSemaine as $semaineCle => $creneaux) {
            $first = $creneaux->first();
            $weekStart = $first->date->copy()->subDays($first->date->isoWeekday() - 1)->startOfDay();
            $weekEnd = $weekStart->copy()->addDays(6)->endOfDay();

            foreach ($tousEvenements as $evenement) {
                // L'événement se chevauche-t-il avec cette semaine ?
                if ($evenement->date_debut->lte($weekEnd) && $evenement->date_fin->gte($weekStart)) {
                    // Calculer les dates exactes de l'événement dans cette semaine
                    $debutDansSemaine = $evenement->date_debut->lt($weekStart)
                        ? $weekStart->copy()
                        : $evenement->date_debut->copy();
                    $finDansSemaine = $evenement->date_fin->gt($weekEnd)
                        ? $weekEnd->copy()
                        : $evenement->date_fin->copy();

                    $bannières[$semaineCle][] = [
                        'evenement' => $evenement,
                        'debut_semaine' => $debutDansSemaine,
                        'fin_semaine' => $finDansSemaine,
                        'informatif' => $evenement->tachesBloquees->isEmpty(),
                    ];
                }
            }
        }

        return $bannières;
    }

    public function showGenerateForm(): View
    {
        return view('planning.generate');
    }

    public function generate(PlanningGenerateRequest $request): RedirectResponse
    {
        try {
            $dateDebut = $request->validated('date_debut');
            $semaines = (int) $request->validated('semaines');

            $resultat = $this->scheduler->generateSchedule($dateDebut, $semaines);

            $lastGenerated = $this->buildRollbackData($dateDebut, $semaines);
            session(['last_generated_creneaux' => $lastGenerated]);

            audit('generate', 'planning', null, null, $resultat);

            if (env('MAKE_WEBHOOK_URL')) {
                $payload = app(\App\Services\WebhookPayloadBuilder::class)
                    ->build($dateDebut, $semaines);

                \App\Jobs\EnvoyerWebhookMake::dispatch($payload);

                Log::info('[PlanningController] Webhook Make.com dispatché en queue.');
            }

            return redirect()->route('planning.generate.form')
                ->with('success', "Planning généré : {$resultat['jours_generes']} jours créés en {$resultat['duree_ms']}ms. ({$resultat['non_assignes']} non assigné(s))");

        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Erreur lors de la génération : ' . $e->getMessage());
        }
    }

    private function buildRollbackData(string $dateDebut, int $semaines): array
    {
        $date = Carbon::parse($dateDebut)->startOfDay();
        while ($date->dayOfWeek !== 5) {
            $date->addDay();
        }

        $dateFin = $date->copy()->addWeeks($semaines)->addDay();

        $creneaux = Creneau::whereBetween('date', [$date->toDateString(), $dateFin->toDateString()])
            ->orderBy('date')
            ->get();

        return $creneaux->map(function ($c) {
            return [
                'id' => $c->id,
                'date' => $c->date->toDateString(),
                'week_label' => 'Semaine ' . $c->date->isoWeek() . ' — ' .
                    $c->date->locale('fr')->isoFormat('D MMMM YYYY'),
            ];
        })->toArray();
    }

    public function rollback(Request $request): RedirectResponse
    {
        $type = $request->input('rollback_type', 'total');
        $generated = session('last_generated_creneaux', []);

        if (empty($generated)) {
            return redirect()->route('planning.generate.form')
                ->with('error', 'Aucune session de rollback active.');
        }

        if ($type === 'total') {
            $ids = array_column($generated, 'id');
            $nb = Creneau::whereIn('id', $ids)->count();
            Creneau::whereIn('id', $ids)->delete();
            session()->forget('last_generated_creneaux');

            audit('delete', 'planning', null, ['rollback' => 'total', 'count' => $nb], null);

            return redirect()->route('planning.generate.form')
                ->with('success', "Annulation totale : {$nb} créneaux supprimés.");
        }

        $selectedWeeks = $request->input('selected_weeks', []);
        $creneauIdsByWeek = $request->input('creneau_ids', []);

        $toDelete = [];
        foreach ($selectedWeeks as $weekLabel) {
            if (isset($creneauIdsByWeek[$weekLabel])) {
                $toDelete = array_merge($toDelete, $creneauIdsByWeek[$weekLabel]);
            }
        }

        $toDelete = array_map('intval', $toDelete);

        if (empty($toDelete)) {
            return redirect()->back()->with('error', 'Aucun créneau sélectionné pour l\'annulation.');
        }

        $allowedIds = array_column($generated, 'id');
        $safeDelete = array_intersect($toDelete, $allowedIds);
        $nb = count($safeDelete);
        Creneau::whereIn('id', $safeDelete)->delete();

        $remaining = array_filter($generated, fn($item) => !in_array($item['id'], $safeDelete));
        if (empty($remaining)) {
            session()->forget('last_generated_creneaux');
        } else {
            session(['last_generated_creneaux' => array_values($remaining)]);
        }

        audit('delete', 'planning', null, ['rollback' => 'partial', 'count' => $nb], null);

        return redirect()->route('planning.generate.form')
            ->with('success', "Annulation partielle : {$nb} créneau(x) supprimé(s).");
    }

    public function rollbackDismiss(): RedirectResponse
    {
        session()->forget('last_generated_creneaux');
        return redirect()->route('planning.generate.form')
            ->with('success', 'Planning conservé. Session de rollback fermée.');
    }

    public function showExportForm(): View
    {
        return view('planning.export');
    }

    public function exportPdf(PlanningExportRequest $request): mixed
    {
        $dateDebut = $request->validated('date_debut');
        $dateFin = $request->validated('date_fin');

        $creneaux = Creneau::with(['taches.tache', 'taches.personne', 'evenements'])
            ->whereBetween('date', [$dateDebut, $dateFin])
            ->orderBy('date', 'asc')
            ->get()
            ->groupBy(fn($c) => $c->date->isoWeek() . '-' . $c->date->year);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('planning.pdf', compact('creneaux', 'dateDebut', 'dateFin'))
            ->setPaper('a4', 'landscape')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'defaultFont' => 'DejaVu Sans',
            ]);

        $filename = 'planning-amana-' . $dateDebut . '-au-' . $dateFin . '.pdf';

        return $pdf->download($filename);
    }

    public function statistics(): View
    {
        $stats = $this->stats->computeAll();
        return view('statistics.index', compact('stats'));
    }
}