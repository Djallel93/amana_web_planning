<?php
// app/Http/Controllers/AbsencesController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Absences\StoreAbsenceRequest;
use App\Models\Absence;
use App\Models\Personne;
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
 */
class AbsencesController extends Controller
{
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

        return redirect()->route('absences.index')
            ->with('success', "Absence ajoutée pour {$personne->prenom} {$personne->nom}.");
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
