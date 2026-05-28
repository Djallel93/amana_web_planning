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
 * Gère l'affichage, la génération, le rollback, l'export PDF et les statistiques.
 */
class PlanningController extends Controller
{
    public function __construct(
        private readonly SchedulerMain $scheduler,
        private readonly Statistics $stats,
    ) {
    }

    /**
     * Affiche le planning trié du plus récent au plus ancien.
     */
    public function index(): View
    {
        $creneaux = Creneau::with(['taches.tache', 'taches.personne', 'evenements'])
            ->orderBy('date', 'desc')   // ← plus récent en premier
            ->get()
            ->groupBy(fn($c) => $c->date->isoWeek() . '-' . $c->date->year);

        return view('planning.index', compact('creneaux'));
    }

    /**
     * Affiche le formulaire de génération.
     * Si une session de rollback est active, elle est passée à la vue.
     */
    public function showGenerateForm(): View
    {
        return view('planning.generate');
    }

    /**
     * Lance la génération du planning.
     * Envoie ensuite le webhook Make.com de manière asynchrone (via queue).
     */
    public function generate(PlanningGenerateRequest $request): RedirectResponse
    {
        try {
            $dateDebut = $request->validated('date_debut');
            $semaines = (int) $request->validated('semaines');

            $resultat = $this->scheduler->generateSchedule($dateDebut, $semaines);

            // Stocker les créneaux générés pour le rollback
            $lastGenerated = $this->buildRollbackData($dateDebut, $semaines);
            session(['last_generated_creneaux' => $lastGenerated]);

            audit('generate', 'planning', null, null, $resultat);

            // ── Envoi du webhook Make.com (asynchrone via queue) ─────────────
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

    /**
     * Construit les données de rollback après une génération.
     * Récupère les créneaux créés/modifiés à partir de la date de début.
     */
    private function buildRollbackData(string $dateDebut, int $semaines): array
    {
        // Trouver le premier vendredi
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

    /**
     * Rollback : annulation totale ou partielle.
     */
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

        // Partial rollback
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

        // Only delete if they're in the generated set
        $allowedIds = array_column($generated, 'id');
        $safeDelete = array_intersect($toDelete, $allowedIds);
        $nb = count($safeDelete);
        Creneau::whereIn('id', $safeDelete)->delete();

        // Remove deleted from session
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

    /**
     * Dismiss the rollback session without deleting anything.
     */
    public function rollbackDismiss(): RedirectResponse
    {
        session()->forget('last_generated_creneaux');
        return redirect()->route('planning.generate.form')
            ->with('success', 'Planning conservé. Session de rollback fermée.');
    }

    /**
     * Affiche le formulaire d'export PDF.
     */
    public function showExportForm(): View
    {
        return view('planning.export');
    }

    /**
     * Génère et télécharge le PDF du planning pour une plage de dates.
     */
    public function exportPdf(PlanningExportRequest $request): mixed
    {
        $dateDebut = $request->validated('date_debut');
        $dateFin = $request->validated('date_fin');

        $creneaux = Creneau::with(['taches.tache', 'taches.personne', 'evenements'])
            ->whereBetween('date', [$dateDebut, $dateFin])
            ->orderBy('date', 'asc')
            ->get()
            ->groupBy(fn($c) => $c->date->isoWeek() . '-' . $c->date->year);

        // Use DomPDF via Laravel facade
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

    /**
     * Affiche les statistiques du planning.
     */
    public function statistics(): View
    {
        $stats = $this->stats->computeAll();
        return view('statistics.index', compact('stats'));
    }
}
