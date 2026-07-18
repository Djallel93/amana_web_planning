<?php
// app/Http/Controllers/PlanningEditController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\SynchroniserGoogleCalendar;
use App\Models\Creneau;
use App\Models\CreneauTache;
use App\Models\Evenement;
use App\Models\Personne;
use App\Models\Tache;
use App\Services\WebhookPayloadBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Contrôleur pour les modifications manuelles du planning généré.
 *
 * Chaque modification synchronise directement Google Calendar (API v3, via
 * SynchroniserGoogleCalendar) avec le mode adapté à la nature de l'action :
 *   - réassignation (patchAssignation)      → PATCH  (upsert, en queue)
 *   - désassignation (unassignTache)        → DELETE (synchrone)
 *   - suppression d'un créneau entier       → DELETE (synchrone)
 *   - création manuelle d'un créneau        → POST   (upsert, en queue)
 *   - annulation d'un cours (annulerCours)  → DELETE (nettoyage calendrier,
 *                                              synchrone) puis POST (annonce
 *                                              annulation, en queue)
 *
 * Les DELETE sont dispatchés en SYNCHRONE (dispatchSync) et non en queue,
 * car ils sont toujours suivis d'une suppression en cascade des lignes
 * plan_calendrier_evenements — voir le docblock de SynchroniserGoogleCalendar
 * pour le détail de ce choix.
 *
 * Routes :
 *   PATCH  /planning/creneau/{creneauId}/tache/{tacheId}  → modifier l'assignation
 *   DELETE /planning/creneau/{creneauId}/tache/{tacheId}  → désassigner une tâche
 *   DELETE /planning/creneau/{id}                         → supprimer un créneau entier
 *   POST   /planning/creneau                              → créer un créneau manuellement
 *   POST   /planning/annulation-cours                     → annuler le cours d'une date
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
                'id_tache' => $tacheId,
            ],
            ['id_personne' => null]
        );

        $avant = $ct->toArray();

        // ⚠️ CreneauTache a une clé primaire composite (id_planning, id_tache)
        // et déclare `$primaryKey = null` pour désactiver l'auto-increment
        // — de ce fait, Eloquent ne peut pas construire de clause WHERE pour
        // un ->save() sur une instance déjà chargée (aucune colonne "id" à
        // utiliser). ->save() ici générerait un UPDATE SANS CLAUSE WHERE, qui
        // mettrait à jour TOUTES les lignes de la table. On passe donc
        // systématiquement par le query builder statique, scopé explicitement
        // par (id_planning, id_tache) — jamais ->save()/->update() sur une
        // instance de ce modèle.
        CreneauTache::where('id_planning', $creneauId)
            ->where('id_tache', $tacheId)
            ->update(['id_personne' => $request->input('id_personne')]);

        $ct = $ct->fresh();

        $newPersonne = null;
        if ($ct->id_personne) {
            $p = Personne::find($ct->id_personne);
            $newPersonne = $p ? ['id' => $p->id, 'label' => $p->prenom . ' ' . $p->nom] : null;
        }

        audit('update', 'planning', $creneauId, $avant, $ct->toArray());

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

        // Voir avertissement dans patchAssignation() — jamais ->save() sur une
        // instance de CreneauTache (clé primaire composite, $primaryKey = null).
        CreneauTache::where('id_planning', $creneauId)
            ->where('id_tache', $tacheId)
            ->update(['id_personne' => null]);

        $ct = $ct->fresh();

        audit('update', 'planning', $creneauId, $avant, $ct->toArray());

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

    /**
     * Annule le cours d'une date déjà générée : désassigne toutes les tâches,
     * bloque la date via un événement organisationnel ("Cours annulé — …",
     * bloquant toutes les tâches actives — visible dans la liste des
     * Événements), supprime tous les événements calendrier existants sur
     * cette date, puis annonce l'annulation comme n'importe quel autre
     * événement social (calendar_annulation_cours).
     *
     * POST /planning/annulation-cours
     * Body JSON : { "date": "2026-07-10" }
     *
     * Si aucun créneau n'existe pour cette date, rien n'est modifié : on
     * retourne un simple avertissement (la date ne peut pas être "bloquée"
     * a priori, seulement annulée une fois le planning généré).
     */
    public function annulerCours(Request $request): JsonResponse
    {
        $request->validate([
            'date' => ['required', 'date', 'after:today'],
        ], [
            'date.required' => 'La date est obligatoire.',
            'date.after' => 'La date doit être future.',
        ]);

        $date = $request->input('date');
        $carbonDate = \Carbon\Carbon::parse($date);

        $creneau = Creneau::with('taches.tache')->where('date', $date)->first();

        if (!$creneau) {
            return response()->json([
                'success' => false,
                'warning' => true,
                'message' => "Aucun planning n'a encore été généré pour le "
                    . $carbonDate->locale('fr')->isoFormat('D MMMM YYYY')
                    . ". Cette date ne peut donc pas être annulée pour le moment.",
            ], 422);
        }

        // ── 1. Nettoyer les événements calendrier existants sur cette date ──
        // À faire AVANT de créer/attacher l'événement bloquant ci-dessous :
        // buildForDeleteCreneau() ignore les tâches déjà bloquées par un
        // événement lié au créneau (puisqu'aucun événement calendrier
        // n'existerait pour elles) — il faut donc capturer l'état "avant
        // annulation" pendant que le créneau n'est pas encore bloqué.
        $this->dispatchWebhookDeleteCreneau($creneau);

        // ── 2. Désassigner toutes les tâches du créneau ─────────────────────
        $avant = $creneau->taches->map(fn($ct) => [
            'id_tache' => $ct->id_tache,
            'id_personne' => $ct->id_personne,
        ])->all();

        foreach ($creneau->taches as $ct) {
            if ($ct->id_personne !== null) {
                // Voir avertissement dans patchAssignation() — jamais ->save() sur
                // une instance de CreneauTache (clé primaire composite, $primaryKey = null),
                // sous peine d'un UPDATE sans clause WHERE affectant TOUTE la table.
                CreneauTache::where('id_planning', $ct->id_planning)
                    ->where('id_tache', $ct->id_tache)
                    ->update(['id_personne' => null]);
            }
        }

        // ── 3. Bloquer la date via un événement organisationnel ─────────────
        // Réutilise le mécanisme existant "événement bloquant toutes les
        // tâches" — apparaît dans la liste des Événements (voulu), et la
        // grille/génération/export respectent immédiatement le blocage.
        $nomEvenement = 'Cours annulé — ' . $carbonDate->locale('fr')->isoFormat('D MMMM YYYY');

        $evenement = Evenement::create([
            'nom' => $nomEvenement,
            'date_debut' => $date,
            'date_fin' => $date,
            'description' => 'Cours annulé via le bouton "Annulation cours" du planning.',
        ]);

        $tacheIds = Tache::actif()->pluck('id');
        $evenement->tachesBloquees()->sync($tacheIds);
        $creneau->evenements()->syncWithoutDetaching([$evenement->id]);

        audit('update', 'planning', $creneau->id, ['taches' => $avant], [
            'annule' => true,
            'evenement_id' => $evenement->id,
        ]);
        audit('create', 'evenements', $evenement->id, null, array_merge(
            $evenement->toArray(),
            ['taches_bloquees' => $tacheIds->all()]
        ));

        // ── 4. Annoncer l'annulation (POST, comme n'importe quel événement) ─
        // Ici, à l'inverse, le créneau DOIT refléter l'état "après annulation"
        // (toutes les tâches bloquées) — buildForAnnulationCours() recharge
        // le créneau et son événement bloquant.
        $this->dispatchWebhookAnnulationCours($creneau);

        return response()->json([
            'success' => true,
            'message' => 'Cours annulé pour le ' . $carbonDate->locale('fr')->isoFormat('D MMMM YYYY') . '.',
            'date' => $date,
        ]);
    }

    // ── Private : dispatch des webhooks ──────────────────────────────────
    // Chacun est silencieux en cas d'erreur (ne doit pas faire échouer la
    // réponse JSON de l'action principale).

    private function dispatchWebhookReassignation(int $creneauId, int $tacheId): void
    {
        try {
            $creneau = Creneau::findOrFail($creneauId);
            $tache = Tache::findOrFail($tacheId);
            $payload = $this->webhookBuilder->buildForReassignation($creneau, $tache);

            SynchroniserGoogleCalendar::dispatch($payload, 'patch');

            Log::info('[PlanningEditController] Synchronisation Google Calendar dispatchée (réassignation)', [
                'creneau_id' => $creneauId,
                'tache_id' => $tacheId,
            ]);
        } catch (\Throwable $e) {
            Log::error('[PlanningEditController] Échec dispatch synchronisation (réassignation)', [
                'creneau_id' => $creneauId,
                'tache_id' => $tacheId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function dispatchWebhookUnassignation(int $creneauId, int $tacheId): void
    {
        try {
            $creneau = Creneau::findOrFail($creneauId);
            $tache = Tache::findOrFail($tacheId);
            $payload = $this->webhookBuilder->buildForUnassignation($creneau, $tache);

            SynchroniserGoogleCalendar::dispatchSync($payload, 'delete');

            Log::info('[PlanningEditController] Synchronisation Google Calendar (délete, désassignation)', [
                'creneau_id' => $creneauId,
                'tache_id' => $tacheId,
            ]);
        } catch (\Throwable $e) {
            Log::error('[PlanningEditController] Échec synchronisation Google Calendar (désassignation)', [
                'creneau_id' => $creneauId,
                'tache_id' => $tacheId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function dispatchWebhookDeleteCreneau(Creneau $creneau): void
    {
        try {
            $payload = $this->webhookBuilder->buildForDeleteCreneau($creneau);

            SynchroniserGoogleCalendar::dispatchSync($payload, 'delete');

            Log::info('[PlanningEditController] Synchronisation Google Calendar (delete, créneau supprimé)', [
                'creneau_id' => $creneau->id,
                'date' => $creneau->date->toDateString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[PlanningEditController] Échec synchronisation Google Calendar (delete créneau)', [
                'creneau_id' => $creneau->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function dispatchWebhookCreation(Creneau $creneau): void
    {
        try {
            $payload = $this->webhookBuilder->buildForCreation($creneau);

            SynchroniserGoogleCalendar::dispatch($payload, 'post');

            Log::info('[PlanningEditController] Synchronisation Google Calendar dispatchée (créneau créé)', [
                'creneau_id' => $creneau->id,
                'date' => $creneau->date->toDateString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[PlanningEditController] Échec dispatch synchronisation (créneau)', [
                'creneau_id' => $creneau->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function dispatchWebhookAnnulationCours(Creneau $creneau): void
    {
        try {
            $payload = $this->webhookBuilder->buildForAnnulationCours($creneau);

            SynchroniserGoogleCalendar::dispatch($payload, 'post');

            Log::info('[PlanningEditController] Synchronisation Google Calendar dispatchée (annulation cours)', [
                'creneau_id' => $creneau->id,
                'date' => $creneau->date->toDateString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[PlanningEditController] Échec dispatch synchronisation (annulation cours)', [
                'creneau_id' => $creneau->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}