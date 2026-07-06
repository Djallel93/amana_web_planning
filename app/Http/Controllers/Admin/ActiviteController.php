<?php
// app/Http/Controllers/Admin/ActiviteController.php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditStatistics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Contrôleur pour la page Statistiques d'activité (admin uniquement).
 *
 * À ne pas confondre avec :
 *   - Statistics.php / /statistics  → équilibrage de la rotation des tâches
 *   - Bilan / /bilan/statistiques   → présence et collecte du jour
 *
 * Cette page mesure l'usage de l'application elle-même (volume d'actions,
 * connexions, échanges, régénérations automatiques), à partir des données
 * déjà collectées dans audit_logs — aucune nouvelle table.
 *
 * Routes :
 *   GET /admin/activite        → shell Blade (point de montage ActiviteStatistiques.vue)
 *   GET /admin/activite/data   → JSON des métriques sur une période
 */
class ActiviteController extends Controller
{
    public function __construct(
        private readonly AuditStatistics $stats,
    ) {
    }

    public function index(): View
    {
        return view('admin.activite.index');
    }

    /**
     * GET /admin/activite/data?from=YYYY-MM-DD&to=YYYY-MM-DD
     */
    public function data(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
        ]);

        return response()->json(
            $this->stats->computeAll($request->query('from'), $request->query('to'))
        );
    }
}
