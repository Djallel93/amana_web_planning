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
 * Chaque modification déclenche un webhook vers Make.com avec le verbe HTTP
 * correspondant à la nature de l'action :
 *   - réassignation (patchAssignation)      → PATCH
 *   - désassignation (unassignTache)        → DELETE
 *   - suppression d'un créneau entier       → DELETE
 *   - création manuelle d'un créneau        → POST
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

        // firstOrCreate plutôt que firstOrFail : si la ligne CreneauTache n'existe
        // pas encore (tâche jamais assignée sur ce créneau), on la crée à la volée
        // plutôt que de retourner une 404. Cela rend le PATCH idempotent pour les
        // deux cas (assignation initiale et réassignation).
        $ct = CreneauTache::firstOrCreate(
            [
                'id_planning' => $creneauId,
                'id_tache'    => $tacheId,
            ],
            ['id_personne' => null]
        );

        $avant = $ct->toArray();
        $ct->id_personne = $request->input('id_personne');
        $ct->save();

        $newPersonne = null;
        if ($ct->id_personne) {
            $p = Personne::find($ct->id_personne);
            $newPersonne = $p ? ['id' => $p->id, 'label' => $p->prenom . ' ' . $p->nom] : null;
        }

        audit('update', 'planning', $creneauId, $avant, $ct->fresh()->toArray());

        // ── Déclencher le webhook PATCH pour refléter la réassignation ─────
        $this->dispatchWebhookReassignation($creneauId, $tacheId);

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

        // ── Déclencher le webhook DELETE pour refléter la désassignation ───
        $this->dispatchWebhookUnassignation($creneauId, $tacheId);

        return response()->json([
            'success' => true,
            'message' => 'Assignation supprimée',
        ]);
    }

    /**
     * Supprime un créneau entier avec toutes ses tâches.
     * DELETE /planning/creneau/{id}
     */
    public function deleteCreneau(int $id): JsonResponse
    {
        $creneau = Creneau::with(['taches', 'evenements.tachesBloquees'])->findOrFail($id);
        $avant = [
            'date' => $creneau->date->toDateString(),
            'jour' => $creneau->jour,
            'taches' => $creneau->taches->count(),
        ];

        // ── Construire et envoyer le webhook DELETE AVANT la suppression ──
        // (on a encore besoin des tâches bloquées par événement pour savoir
        // quels événements calendrier ont potentiellement été créés)
        $this->dispatchWebhookDeleteCreneau($creneau);

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

        // ── Déclencher le webhook POST pour le nouveau créneau ─────────────
        $this->dispatchWebhookCreation($creneau);

        return response()->json([
            'success' => true,
            'message' => "Créneau du {$creneau->jour} " .
                $carbonDate->locale('fr')->isoFormat('D MMM YYYY') . ' créé.',
            'date' => $date,
        ]);
    }

    // ── Private : dispatch des webhooks ──────────────────────────────────
    // Chacun est silencieux en cas d'erreur (ne doit pas faire échouer la
    // réponse JSON de l'action principale).

    private function dispatchWebhookReassignation(int $creneauId, int $tacheId): void
    {
        if (empty(config('services.make.webhook_url'))) {
            return;
        }

        try {
            $creneau = Creneau::findOrFail($creneauId);
            $tache = Tache::findOrFail($tacheId);
            $payload = $this->webhookBuilder->buildForReassignation($creneau, $tache);

            EnvoyerWebhookMake::dispatch($payload, 'patch');

            Log::info('[PlanningEditController] Webhook PATCH dispatché (réassignation)', [
                'creneau_id' => $creneauId,
                'tache_id' => $tacheId,
            ]);
        } catch (\Throwable $e) {
            Log::error('[PlanningEditController] Échec dispatch webhook PATCH', [
                'creneau_id' => $creneauId,
                'tache_id' => $tacheId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function dispatchWebhookUnassignation(int $creneauId, int $tacheId): void
    {
        if (empty(config('services.make.webhook_url'))) {
            return;
        }

        try {
            $creneau = Creneau::findOrFail($creneauId);
            $tache = Tache::findOrFail($tacheId);
            $payload = $this->webhookBuilder->buildForUnassignation($creneau, $tache);

            EnvoyerWebhookMake::dispatch($payload, 'delete');

            Log::info('[PlanningEditController] Webhook DELETE dispatché (désassignation)', [
                'creneau_id' => $creneauId,
                'tache_id' => $tacheId,
            ]);
        } catch (\Throwable $e) {
            Log::error('[PlanningEditController] Échec dispatch webhook DELETE', [
                'creneau_id' => $creneauId,
                'tache_id' => $tacheId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function dispatchWebhookDeleteCreneau(Creneau $creneau): void
    {
        if (empty(config('services.make.webhook_url'))) {
            return;
        }

        try {
            $payload = $this->webhookBuilder->buildForDeleteCreneau($creneau);

            EnvoyerWebhookMake::dispatch($payload, 'delete');

            Log::info('[PlanningEditController] Webhook DELETE dispatché (créneau supprimé)', [
                'creneau_id' => $creneau->id,
                'date' => $creneau->date->toDateString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[PlanningEditController] Échec dispatch webhook DELETE créneau', [
                'creneau_id' => $creneau->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function dispatchWebhookCreation(Creneau $creneau): void
    {
        if (empty(config('services.make.webhook_url'))) {
            return;
        }

        try {
            $payload = $this->webhookBuilder->buildForCreation($creneau);

            EnvoyerWebhookMake::dispatch($payload, 'post');

            Log::info('[PlanningEditController] Webhook POST dispatché (créneau créé)', [
                'creneau_id' => $creneau->id,
                'date' => $creneau->date->toDateString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[PlanningEditController] Échec dispatch webhook POST créneau', [
                'creneau_id' => $creneau->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}