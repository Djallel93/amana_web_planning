<?php
// app/Http/Controllers/PersonnesController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Personnes\StorePersonneRequest;
use App\Http\Requests\Personnes\UpdatePersonneRequest;
use App\Models\Personne;
use App\Models\Vehicule;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Contrôleur CRUD pour les personnes (membres officiels + bénévoles).
 */
class PersonnesController extends Controller
{
    /**
     * Liste toutes les personnes.
     */
    public function index(): View
    {
        $personnes = Personne::with('vehicule')
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get();

        return view('personnes.index', compact('personnes'));
    }

    /**
     * Affiche le formulaire de création.
     */
    public function create(): View
    {
        $vehicules = Vehicule::orderBy('type')->get();
        $statuts   = ['En attente', 'Validé', 'Suspendu', 'Archivé'];

        return view('personnes.form', compact('vehicules', 'statuts'));
    }

    /**
     * Enregistre une nouvelle personne.
     */
    public function store(StorePersonneRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['tirelire'] = $request->boolean('tirelire');

        $personne = Personne::create($data);

        audit('create', 'personnes', $personne->id, null, $personne->toArray());

        return redirect()->route('personnes.index')
            ->with('success', "Personne « {$personne->prenom} {$personne->nom} » créée avec succès.");
    }

    /**
     * Affiche le formulaire d'édition.
     */
    public function edit(int $id): View
    {
        $personne  = Personne::findOrFail($id);
        $vehicules = Vehicule::orderBy('type')->get();
        $statuts   = ['En attente', 'Validé', 'Suspendu', 'Archivé'];

        return view('personnes.form', compact('personne', 'vehicules', 'statuts'));
    }

    /**
     * Met à jour une personne.
     */
    public function update(UpdatePersonneRequest $request, int $id): RedirectResponse
    {
        $personne = Personne::findOrFail($id);
        $avant    = $personne->toArray();

        $data = $request->validated();
        $data['tirelire'] = $request->boolean('tirelire');

        $personne->update($data);

        audit('update', 'personnes', $personne->id, $avant, $personne->fresh()->toArray());

        return redirect()->route('personnes.index')
            ->with('success', "Personne « {$personne->prenom} {$personne->nom} » mise à jour.");
    }

    /**
     * Supprime une personne.
     * Les absences et restrictions sont supprimées en cascade (FK ON DELETE CASCADE).
     */
    public function destroy(int $id): RedirectResponse
    {
        $personne = Personne::findOrFail($id);
        $avant    = $personne->toArray();
        $nom      = "{$personne->prenom} {$personne->nom}";

        $personne->delete();

        audit('delete', 'personnes', $id, $avant, null);

        return redirect()->route('personnes.index')
            ->with('success', "Personne « {$nom} » supprimée.");
    }
}
