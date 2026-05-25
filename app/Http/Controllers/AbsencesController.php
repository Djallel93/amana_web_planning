<?php
// app/Http/Controllers/AbsencesController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Absences\StoreAbsenceRequest;
use App\Models\Absence;
use App\Models\Personne;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Contrôleur pour les absences personnelles.
 */
class AbsencesController extends Controller
{
    /**
     * Liste toutes les absences avec un formulaire d'ajout.
     */
    public function index(): View
    {
        $absences  = Absence::with('personne')
            ->orderBy('date_debut', 'desc')
            ->get();

        $personnes = Personne::actifAuPlanning()
            ->orderBy('nom')
            ->get();

        return view('absences.index', compact('absences', 'personnes'));
    }

    /**
     * Enregistre une nouvelle absence.
     */
    public function store(StoreAbsenceRequest $request): RedirectResponse
    {
        $absence  = Absence::create($request->validated());
        $personne = $absence->personne;

        audit('create', 'absences', $absence->id, null, $absence->toArray());

        return redirect()->route('absences.index')
            ->with('success', "Absence ajoutée pour {$personne->prenom} {$personne->nom}.");
    }

    /**
     * Supprime une absence.
     */
    public function destroy(int $id): RedirectResponse
    {
        $absence  = Absence::with('personne')->findOrFail($id);
        $avant    = $absence->toArray();
        $nomPers  = $absence->personne ? "{$absence->personne->prenom} {$absence->personne->nom}" : "ID {$absence->id_personne}";

        $absence->delete();

        audit('delete', 'absences', $id, $avant, null);

        return redirect()->route('absences.index')
            ->with('success', "Absence de {$nomPers} supprimée.");
    }
}
