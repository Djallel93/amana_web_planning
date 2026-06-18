<?php
// app/Http/Controllers/PlanningEditController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\EnvoyerWebhookMake;
use App\Models\Creneau;
use App\Models\CreneauTache;
use App\Models\Personne;
use App\Models\Tache;
use App\Services\WebhookPayloadBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Contrôleur pour les modifications manuelles du planning généré.
 *
 * Chaque modification d'assignation (patch ou unassign) déclenche
 * un webhook vers Make.com pour maintenir Google Calendar à jour.
 *
 * Routes :
 *   PATCH  /planning/creneau/{creneauId}/tache/{tacheId}  → modifier l'assignation
 *   DELETE /planning/creneau/{creneauId}/tache/{tacheId}  → désassigner une tâche
 *   DELETE /planning/creneau/{id}                         → supprimer un créneau entier
 *   POST   /planning/creneau                              → créer un créneau manuellement
 *   GET    /planning/personnes-actives                    → liste des personnes pour la modale
 */
class PlanningEditController extends Controller
{
    public function __construct(
        private readonly WebhookPayloadBuilder $webhookBuilder,
    ) {
    }

    /**
     * Retourne la liste des personnes actives pour peupler la modale.
     */
    public function personnes(): JsonResponse
    {
        $personnes = Personne::actifAuPlanning()
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get(['id', 'nom', 'prenom']);

        return response()->json($personnes->map(fn($p) => [
            'id' => $p->id,
            'label' => $p->prenom . ' ' . $p->nom,
        ]));
    }

    /**
     * Met à jour l'assignation via creneau_id + tache_id.
     * PATCH /planning/creneau/{creneauId}/tache/{tacheId}
     * Body JSON : { "id_personne": 42 } ou { "id_personne": null }
     */
    public function patchAssignation(Request $request, int $creneauId, int $tacheId): JsonResponse
    {
        $request->validate([
            'id_personne' => ['nullable', 'integer', 'exists:ref_personnes,id'],
        ]);

        $ct = CreneauTache::where('id_planning', $creneauId)
            ->where('id_tache', $tacheId)
            ->firstOrFail();

        $avant = $ct->toArray();
        $ct->id_personne = $request->input('id_personne');
        $ct->save();

        $newPersonne = null;
        if ($ct->id_personne) {
            $p = Personne::find($ct->id_personne);
            $newPersonne = $p ? ['id' => $p->id, 'label' => $p->prenom . ' ' . $p->nom] : null;
        }

        audit('update', 'planning', $creneauId, $avant, $ct->fresh()->toArray());

        // ── Déclencher le webhook pour refléter la modification ────────────
        $this->dispatchWebhookForCreneau($creneauId);

        return response()->json([
            'success' => true,
            'personne' => $newPersonne,
            'message' => $newPersonne
                ? "Assigné à {$newPersonne['label']}"
                : 'Tâche désassignée',
        ]);
    }

    /**
     * Désassigne complètement une tâche (id_personne → null).
     * DELETE /planning/creneau/{creneauId}/tache/{tacheId}
     */
    public function unassignTache(int $creneauId, int $tacheId): JsonResponse
    {
        $ct = CreneauTache::where('id_planning', $creneauId)
            ->where('id_tache', $tacheId)
            ->firstOrFail();

        $avant = $ct->toArray();
        $ct->id_personne = null;
        $ct->save();

        audit('update', 'planning', $creneauId, $avant, $ct->fresh()->toArray());

        // ── Déclencher le webhook pour refléter la désassignation ──────────
        $this->dispatchWebhookForCreneau($creneauId);

        return response()->json([
            'success' => true,
            'message' => 'Assignation supprimée',
        ]);
    }

    /**
     * Supprime un créneau entier avec toutes ses tâches.
     * DELETE /planning/creneau/{id}
     *
     * Note : pas de webhook ici — la suppression d'un créneau côté Make.com
     * est gérée par la logique de déduplication du scénario (il ne recréera
     * pas un événement qui n'existe plus dans le payload).
     */
    public function deleteCreneau(int $id): JsonResponse
    {
        $creneau = Creneau::with(['taches', 'evenements'])->findOrFail($id);
        $avant = [
            'date' => $creneau->date->toDateString(),
            'jour' => $creneau->jour,
            'taches' => $creneau->taches->count(),
        ];

        $creneau->delete();

        audit('delete', 'planning', $id, $avant, null);

        return response()->json([
            'success' => true,
            'message' => "Créneau du {$avant['jour']} {$avant['date']} supprimé",
        ]);
    }

    /**
     * Crée un créneau manuellement pour une date donnée.
     * POST /planning/creneau
     * Body JSON : { "date": "2025-06-06" }
     *
     * Crée le créneau + une CreneauTache (vide) par tâche active.
     */
    public function createCreneau(Request $request): JsonResponse
    {
        $request->validate([
            'date' => ['required', 'date', 'unique:plan_creneaux,date'],
        ], [
            'date.required' => 'La date est obligatoire.',
            'date.unique' => 'Un créneau existe déjà pour cette date.',
        ]);

        $date = $request->input('date');

        $creneau = Creneau::create(['date' => $date]);

        $taches = Tache::actif()->orderBy('id')->get();
        foreach ($taches as $tache) {
            CreneauTache::create([
                'id_planning' => $creneau->id,
                'id_tache' => $tache->id,
                'id_personne' => null,
            ]);
        }

        $carbonDate = \Carbon\Carbon::parse($date);

        audit('create', 'planning', $creneau->id, null, [
            'date' => $carbonDate->toDateString(),
            'jour' => $creneau->jour,
            'taches' => $taches->count(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Créneau du {$creneau->jour} " .
                $carbonDate->locale('fr')->isoFormat('D MMM YYYY') . ' créé.',
            'date' => $date,
        ]);
    }

    // ── Private ───────────────────────────────────────────────────────────

    /**
     * Construit et dispatche le webhook pour un créneau spécifique.
     * Silencieux en cas d'erreur (ne doit pas faire échouer la réponse JSON).
     */
    private function dispatchWebhookForCreneau(int $creneauId): void
    {
        if (empty(config('MAKE_WEBHOOK_URL'))) {
            return;
        }

        try {
            $creneau = Creneau::findOrFail($creneauId);
            $payload = $this->webhookBuilder->buildForCreneau($creneau);

            EnvoyerWebhookMake::dispatch($payload);

            Log::info('[PlanningEditController] Webhook Make.com dispatché pour créneau', [
                'creneau_id' => $creneauId,
                'date' => $creneau->date->toDateString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[PlanningEditController] Échec dispatch webhook', [
                'creneau_id' => $creneauId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}