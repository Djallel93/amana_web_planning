<?php
// app/Http/Controllers/PlanningController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\DateHelper;
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
     * La vue Blade ne rend plus que le header de page — toutes les données
     * (créneaux, bannières, filtres) sont chargées côté client par
     * PlanningGrid.vue via GET /planning/data (PlanningApiController::data()).
     * On ne fait donc plus aucune requête DB ici.
     */
    public function index(): View
    {
        return view('planning.index');
    }

    /**
     * Construit les bannières informatives à afficher dans les blocs semaine.
     */
    protected function buildBannièresInformatives(
        \Illuminate\Support\Collection $tousEvenements,
        \Illuminate\Support\Collection $creneauxParSemaine
    ): array {
        $bannières = [];

        foreach ($creneauxParSemaine as $semaineCle => $creneaux) {
            $first = $creneaux->first();
            $weekStart = $first->date->clone()->subDays($first->date->isoWeekday() - 1)->startOfDay();
            $weekEnd = $weekStart->clone()->addDays(6)->endOfDay();

            foreach ($tousEvenements as $evenement) {
                if ($evenement->date_debut->lte($weekEnd) && $evenement->date_fin->gte($weekStart)) {
                    $debutDansSemaine = $evenement->date_debut->lt($weekStart)
                        ? $weekStart->clone()
                        : $evenement->date_debut->clone();
                    $finDansSemaine = $evenement->date_fin->gt($weekEnd)
                        ? $weekEnd->clone()
                        : $evenement->date_fin->clone();

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

    /**
     * Génère le planning.
     *
     * Si des créneaux futurs existent et que la génération n'a pas encore été
     * confirmée, redirige vers le formulaire avec un avertissement détaillé.
     * La confirmation se fait via le champ caché `confirmed=1`.
     */
    public function generate(PlanningGenerateRequest $request): RedirectResponse
    {
        $dateDebut = $request->validated('date_debut');
        $semaines = (int) $request->validated('semaines');

        // ── Calculer le premier vendredi ──────────────────────────────────
        $premierVendredi = DateHelper::premierVendredi($dateDebut);

        // ── Détecter les créneaux qui seraient écrasés ────────────────────
        $creneauxExistants = Creneau::where('date', '>=', $premierVendredi->toDateString())
            ->orderBy('date')
            ->get();

        if ($creneauxExistants->isNotEmpty() && $request->input('confirmed') !== '1') {
            // Construire la liste des semaines affectées (pour l'affichage)
            $semainesAffectées = $creneauxExistants
                ->groupBy(fn($c) => $c->date->isoWeek() . '-' . $c->date->year)
                ->map(function ($groupe) {
                    $first = $groupe->first();
                    $last = $groupe->last();
                    return [
                        'label' => 'Semaine ' . $first->semaine,
                        'dates' => $first->date->locale('fr')->isoFormat('D MMM')
                            . ' — '
                            . $last->date->locale('fr')->isoFormat('D MMM YYYY'),
                        'nb_creneaux' => $groupe->count(),
                    ];
                })
                ->values()
                ->toArray();

            session([
                'pending_generation' => [
                    'date_debut' => $dateDebut,
                    'semaines' => $semaines,
                    'semaines_affectees' => $semainesAffectées,
                    'nb_total' => $creneauxExistants->count(),
                ],
            ]);

            return redirect()->route('planning.generate.form')
                ->with('warning', 'Des créneaux existants vont être supprimés. Veuillez confirmer ci-dessous.');
        }

        // Nettoyer la session de confirmation si elle traîne
        session()->forget('pending_generation');

        // ── Génération effective ──────────────────────────────────────────
        try {
            $resultat = $this->scheduler->generateSchedule($dateDebut, $semaines);

            $lastGenerated = $this->buildRollbackData($dateDebut, $semaines);
            session(['last_generated_creneaux' => $lastGenerated]);

            audit('generate', 'planning', null, null, $resultat);

            if (config('services.make.webhook_url')) {
                $payload = app(\App\Services\WebhookPayloadBuilder::class)
                    ->build($dateDebut, $semaines);

                \App\Jobs\EnvoyerWebhookMake::dispatch($payload, 'post');

                Log::info('[PlanningController] Webhook Make.com dispatché en queue (POST).');
            }

            return redirect()->route('planning.generate.form')
                ->with('success', "Planning généré : {$resultat['jours_generes']} jours créés en {$resultat['duree_ms']}ms. ({$resultat['non_assignes']} non assigné(s))");

        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Erreur lors de la génération : ' . $e->getMessage());
        }
    }

    /**
     * Aperçu (dry-run) du planning sans rien enregistrer.
     * Affiche le résultat proposé dans une vue dédiée.
     */
    public function preview(PlanningGenerateRequest $request): View|RedirectResponse
    {
        $dateDebut = $request->validated('date_debut');
        $semaines = (int) $request->validated('semaines');

        try {
            $propositions = $this->scheduler->generateSchedule($dateDebut, $semaines, dryRun: true);

            return view('planning.preview', compact('propositions', 'dateDebut', 'semaines'));

        } catch (\Exception $e) {
            return redirect()->route('planning.generate.form')
                ->withInput()
                ->with('error', 'Erreur lors de la prévisualisation : ' . $e->getMessage());
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function buildRollbackData(string $dateDebut, int $semaines): array
    {
        $date = DateHelper::premierVendredi($dateDebut);
        $dateFin = $date->clone()->addWeeks($semaines)->addDay();

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