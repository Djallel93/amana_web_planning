<?php
// app/Http/Controllers/Admin/AuditLogController.php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Personne;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Contrôleur pour le journal d'audit (lecture seule, admin uniquement).
 *
 * Aucune action d'annulation ("revert") n'est proposée ici volontairement :
 * les entrées avant/après sont de simples instantanés JSON, et restaurer un
 * état passé sans rejouer les effets de bord associés (régénération du
 * planning, webhooks, etc.) pourrait mettre la base dans un état incohérent.
 * Ce contrôleur est donc strictement consultatif.
 *
 * Routes :
 *   GET /admin/journal        → shell Blade (point de montage JournalAudit.vue)
 *   GET /admin/journal/data   → JSON paginé, filtrable
 */
class AuditLogController extends Controller
{
    /**
     * Modules et actions possibles, utilisés pour peupler les filtres.
     * Recensés depuis les appels audit() existants dans le code — à
     * compléter ici si un nouveau module/action est ajouté ailleurs.
     */
    private const MODULES = [
        'absences', 'auth', 'bilan', 'candidatures', 'echanges',
        'evenements', 'inscription', 'personnes', 'planning',
        'restrictions', 'settings',
    ];

    private const ACTIONS = [
        'create', 'update', 'delete', 'generate', 'login', 'logout', 'webhook',
    ];

    public function index(): View
    {
        $personnes = Personne::orderBy('nom')->get(['id', 'nom', 'prenom']);

        return view('admin.journal.index', [
            'personnes' => $personnes,
            'modules'   => self::MODULES,
            'actions'   => self::ACTIONS,
        ]);
    }

    /**
     * Retourne une page d'entrées du journal, filtrée selon les paramètres
     * fournis. Tous les filtres sont optionnels et combinables.
     *
     * GET /admin/journal/data
     *   ?module=planning
     *   &action=update
     *   &user_id=12
     *   &from=2026-06-01
     *   &to=2026-06-30
     *   &page=2
     */
    public function data(Request $request): JsonResponse
    {
        $request->validate([
            'module'  => ['nullable', 'string', 'in:' . implode(',', self::MODULES)],
            'action'  => ['nullable', 'string', 'in:' . implode(',', self::ACTIONS)],
            'user_id' => ['nullable', 'integer'],
            'from'    => ['nullable', 'date'],
            'to'      => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $query = AuditLog::with('personne')
            ->when($request->filled('module'), fn($q) => $q->where('module', $request->query('module')))
            ->when($request->filled('action'), fn($q) => $q->where('action', $request->query('action')))
            ->when($request->filled('user_id'), fn($q) => $q->where('user_id', $request->query('user_id')))
            ->when($request->filled('from'), fn($q) => $q->whereDate('created_at', '>=', $request->query('from')))
            ->when($request->filled('to'), fn($q) => $q->whereDate('created_at', '<=', $request->query('to')))
            ->orderByDesc('created_at');

        $page = $query->paginate(40)->withQueryString();

        return response()->json([
            'data' => collect($page->items())->map(fn(AuditLog $log) => $this->serialize($log)),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'total'        => $page->total(),
            ],
        ]);
    }

    private function serialize(AuditLog $log): array
    {
        return [
            'id'         => $log->id,
            'date'       => $log->created_at->locale('fr')->isoFormat('D MMM YYYY [à] HH:mm:ss'),
            'utilisateur' => $log->personne
                ? "{$log->personne->prenom} {$log->personne->nom}"
                : 'Système',
            'action'     => $log->action,
            'module'     => $log->module,
            'entityId'   => $log->entity_id,
            'before'     => $log->before,
            'after'      => $log->after,
            'ipAddress'  => $log->ip_address,
            'userAgent'  => $log->user_agent,
        ];
    }
}
