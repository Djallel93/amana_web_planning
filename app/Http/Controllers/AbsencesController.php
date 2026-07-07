<?php
// app/Http/Controllers/AbsencesController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Absences\StoreAbsenceRequest;
use App\Http\Requests\Absences\UpdateAbsenceRequest;
use App\Models\Absence;
use App\Models\Personne;
use App\Services\AbsenceRegenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Contrôleur pour les absences.
 *
 * Règles d'accès :
 *   - Admin / Gestionnaire : voit toutes les absences, peut en ajouter/supprimer pour n'importe qui
 *   - Membre : voit toutes les absences (pour savoir qui est disponible),
 *              mais ne peut ajouter/supprimer que les siennes
 *
 * Effet de bord (voir App\Services\AbsenceRegenerationService) :
 *   Ajouter ou modifier une absence qui chevauche une date future pour
 *   laquelle la personne est déjà assignée déclenche automatiquement une
 *   régénération du planning à partir de la première date impactée, afin de
 *   maintenir l'équilibrage des tâches (rotation stricte / score adaptatif).
 *   La suppression d'une absence n'a volontairement aucun effet de ce type :
 *   elle rend seulement la personne de nouveau disponible pour de futures
 *   générations, sans nécessiter de correction immédiate.
 */
class AbsencesController extends Controller
{
    public function __construct(
        private readonly AbsenceRegenerationService $regenerationService,
    ) {
    }

    /**
     * Liste toutes les absences.
     * Tout le monde peut voir les absences — admin, gestionnaire et membre.
     *
     * Pour le formulaire d'ajout :
     *   - Admin / Gestionnaire : peut choisir n'importe quelle personne
     *   - Membre : peut seulement s'ajouter lui-même
     */
    public function index(): View
    {
        /** @var \App\Models\Personne $user */
        $user = Auth::user();

        $absences = Absence::with('personne')
            ->orderBy('date_debut', 'desc')
            ->get();

        // Admin / Gestionnaire : liste toutes les personnes actives pour le formulaire
        // Membre : seulement lui-même dans le formulaire
        if ($user->isAdmin() || $user->isGestionnaire()) {
            $personnes = Personne::actifAuPlanning()
                ->orderBy('nom')
                ->get();
        } else {
            $personnes = Personne::where('id', $user->id)->get();
        }

        return view('absences.index', compact('absences', 'personnes'));
    }

    /**
     * Enregistre une nouvelle absence.
     *
     * Admin / Gestionnaire : peut enregistrer une absence pour n'importe qui.
     * Membre               : peut seulement enregistrer une absence pour lui-même.
     *                        Si le champ id_personne ne correspond pas à son ID → refus.
     */
    public function store(StoreAbsenceRequest $request): RedirectResponse
    {
        /** @var \App\Models\Personne $user */
        $user = Auth::user();

        // Vérification de sécurité pour les membres
        if (! $user->isAdmin() && ! $user->isGestionnaire()) {
            if ((int) $request->validated('id_personne') !== $user->id) {
                return redirect()->route('absences.index')
                    ->with('error', 'Vous ne pouvez enregistrer une absence que pour vous-même.');
            }
        }

        $absence  = Absence::create($request->validated());
        $personne = $absence->personne;

        audit('create', 'absences', $absence->id, null, $absence->toArray());

        $message = "Absence ajoutée pour {$personne->prenom} {$personne->nom}.";

        $regeneration = $this->regenerationService->regenererSiNecessaire($absence);
        if ($regeneration !== null) {
            $message .= ' ' . $regeneration['message'];
        }

        return redirect()->route('absences.index')
            ->with('success', $message);
    }

    /**
     * Modifie une absence existante (appelé en AJAX depuis le modal d'édition).
     *
     * Admin / Gestionnaire : peut modifier n'importe quelle absence, y compris
     *                        réassigner la personne concernée.
     * Membre               : peut modifier seulement ses propres absences,
     *                        et ne peut pas changer la personne concernée.
     */
    public function update(UpdateAbsenceRequest $request, int $id): JsonResponse
    {
        /** @var \App\Models\Personne $user */
        $user    = Auth::user();
        $absence = Absence::with('personne')->findOrFail($id);

        $estPrivilegie = $user->isAdmin() || $user->isGestionnaire();

        // Vérification de sécurité pour les membres : ni l'absence modifiée
        // ni la personne cible ne peuvent être autre chose qu'eux-mêmes.
        if (! $estPrivilegie) {
            if ($absence->id_personne !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez modifier que vos propres absences.',
                ], 403);
            }

            if ((int) $request->validated('id_personne') !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez enregistrer une absence que pour vous-même.',
                ], 403);
            }
        }

        $avant = $absence->toArray();
        $absence->update($request->validated());
        $absence->refresh()->load('personne');

        audit('update', 'absences', $absence->id, $avant, $absence->toArray());

        $message = "Absence de {$absence->personne->prenom} {$absence->personne->nom} mise à jour.";

        $regeneration = $this->regenerationService->regenererSiNecessaire($absence);
        if ($regeneration !== null) {
            $message .= ' ' . $regeneration['message'];
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'planning_regenere' => $regeneration !== null,
            'absence' => [
                'id'          => $absence->id,
                'id_personne' => $absence->id_personne,
                'personne'    => $absence->personne->prenom . ' ' . $absence->personne->nom,
                'date_debut'  => $absence->date_debut->toDateString(),
                'date_fin'    => $absence->date_fin->toDateString(),
                'raison'      => $absence->raison,
            ],
        ]);
    }

    /**
     * Supprime une absence.
     *
     * Admin / Gestionnaire : peut supprimer n'importe quelle absence.
     * Membre               : peut supprimer seulement ses propres absences.
     */
    public function destroy(int $id): RedirectResponse
    {
        /** @var \App\Models\Personne $user */
        $user    = Auth::user();
        $absence = Absence::with('personne')->findOrFail($id);

        // Vérification de sécurité pour les membres
        if (! $user->isAdmin() && ! $user->isGestionnaire() && $absence->id_personne !== $user->id) {
            return redirect()->route('absences.index')
                ->with('error', 'Vous ne pouvez supprimer que vos propres absences.');
        }

        $avant   = $absence->toArray();
        $nomPers = $absence->personne
            ? "{$absence->personne->prenom} {$absence->personne->nom}"
            : "ID {$absence->id_personne}";

        $absence->delete();

        audit('delete', 'absences', $id, $avant, null);

        return redirect()->route('absences.index')
            ->with('success', "Absence de {$nomPers} supprimée.");
    }
}
