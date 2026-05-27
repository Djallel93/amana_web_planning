<?php
// app/Http/Controllers/EvenementsController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Evenements\StoreEvenementRequest;
use App\Http\Requests\Evenements\UpdateEvenementRequest;
use App\Models\Evenement;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Contrôleur CRUD pour les événements organisationnels.
 */
class EvenementsController extends Controller
{
    public function index(): View
    {
        $evenements = Evenement::orderBy('date_debut', 'desc')->get();
        return view('evenements.index', compact('evenements'));
    }

    public function create(): View
    {
        return view('evenements.form');
    }

    public function store(StoreEvenementRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['bloque_planning'] = $request->boolean('bloque_planning');
        $data['necessite_benevoles'] = $request->boolean('necessite_benevoles');

        $evenement = Evenement::create($data);

        audit('create', 'evenements', $evenement->id, null, $evenement->toArray());

        return redirect()->route('evenements.index')
            ->with('success', "Événement « {$evenement->nom} » créé.");
    }

    public function edit(int $id): View
    {
        $evenement = Evenement::findOrFail($id);
        return view('evenements.form', compact('evenement'));
    }

    public function update(UpdateEvenementRequest $request, int $id): RedirectResponse
    {
        $evenement = Evenement::findOrFail($id);
        $avant = $evenement->toArray();

        $data = $request->validated();
        $data['bloque_planning'] = $request->boolean('bloque_planning');
        $data['necessite_benevoles'] = $request->boolean('necessite_benevoles');

        $evenement->update($data);

        audit('update', 'evenements', $evenement->id, $avant, $evenement->fresh()->toArray());

        return redirect()->route('evenements.index')
            ->with('success', "Événement « {$evenement->nom} » mis à jour.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $evenement = Evenement::findOrFail($id);
        $avant = $evenement->toArray();
        $nom = $evenement->nom;

        $evenement->delete();

        audit('delete', 'evenements', $id, $avant, null);

        return redirect()->route('evenements.index')
            ->with('success', "Événement « {$nom} » supprimé.");
    }
}
