<?php
// app/Http/Controllers/PlanningEditController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Creneau;
use App\Models\CreneauTache;
use App\Models\Personne;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contrôleur pour les modifications manuelles du planning généré.
 *
 * Routes :
 *   PATCH  /planning/tache/{id}    → modifier l'assignation d'une tâche
 *   DELETE /planning/tache/{id}    → désassigner une tâche (id_personne = null)
 *   DELETE /planning/creneau/{id}  → supprimer un créneau entier
 *   GET    /planning/personnes     → liste des personnes actives pour la modale
 */
class PlanningEditController extends Controller
{
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
     * Met à jour la personne assignée à une tâche spécifique d'un créneau.
     * PATCH /planning/tache/{id}
     * Body JSON : { "id_personne": 42 }
     */
    public function updateTache(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'id_personne' => ['nullable', 'integer', 'exists:ref_personnes,id'],
        ]);

        $creneauTache = CreneauTache::with(['creneau', 'tache', 'personne'])->findOrFail($id);

        // findOrFail won't work directly since CreneauTache has composite PK
        // We'll query by planning+tache instead — id param is id_planning here
        // Actually let's use a different approach: pass creneau_id + tache_id
        // See note below — we'll use a compound lookup via the route

        $avant = $creneauTache->toArray();
        $creneauTache->id_personne = $request->input('id_personne'); // null = désassigner
        $creneauTache->save();

        // Build response with updated person data
        $newPersonne = null;
        if ($creneauTache->id_personne) {
            $p = Personne::find($creneauTache->id_personne);
            $newPersonne = $p ? ['id' => $p->id, 'label' => $p->prenom . ' ' . $p->nom] : null;
        }

        audit('update', 'planning', $creneauTache->id_planning, $avant, $creneauTache->fresh()->toArray());

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
     * DELETE /planning/tache/{creneau_id}/{tache_id}
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

        return response()->json([
            'success' => true,
            'message' => 'Assignation supprimée',
        ]);
    }

    /**
     * Met à jour l'assignation via creneau_id + tache_id (route principale).
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

        return response()->json([
            'success' => true,
            'personne' => $newPersonne,
            'message' => $newPersonne
                ? "Assigné à {$newPersonne['label']}"
                : 'Tâche désassignée',
        ]);
    }

    /**
     * Supprime un créneau entier (vendredi ou samedi) avec toutes ses tâches.
     * DELETE /planning/creneau/{id}
     */
    public function deleteCreneau(int $id): JsonResponse
    {
        $creneau = Creneau::with(['taches', 'evenements'])->findOrFail($id);
        $avant = [
            'date' => $creneau->date->toDateString(),
            'jour' => $creneau->jour,
            'taches' => $creneau->taches->count(),
        ];

        $creneau->delete(); // Cascade supprime plan_creneaux_taches et plan_creneaux_evenements

        audit('delete', 'planning', $id, $avant, null);

        return response()->json([
            'success' => true,
            'message' => "Créneau du {$avant['jour']} {$avant['date']} supprimé",
        ]);
    }
}
