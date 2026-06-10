<?php
// app/Http/Controllers/EvenementsController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Evenements\StoreEvenementRequest;
use App\Http\Requests\Evenements\UpdateEvenementRequest;
use App\Models\Evenement;
use App\Models\Tache;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Contrôleur CRUD pour les événements organisationnels.
 */
class EvenementsController extends Controller
{
    public function index(): View
    {
        $evenements = Evenement::with('tachesBloquees')
            ->orderBy('date_debut', 'desc')
            ->get();

        return view('evenements.index', compact('evenements'));
    }

    public function create(): View
    {
        $taches = Tache::actif()->orderBy('id')->get();
        return view('evenements.form', compact('taches'));
    }

    public function store(StoreEvenementRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $tacheIds = $data['taches'] ?? [];
        unset($data['taches']);

        $evenement = Evenement::create($data);
        $evenement->tachesBloquees()->sync($tacheIds);

        audit('create', 'evenements', $evenement->id, null, array_merge(
            $evenement->toArray(),
            ['taches_bloquees' => $tacheIds]
        ));

        return redirect()->route('evenements.index')
            ->with('success', "Événement « {$evenement->nom} » créé.");
    }

    public function edit(int $id): View
    {
        $evenement = Evenement::with('tachesBloquees')->findOrFail($id);
        $taches = Tache::actif()->orderBy('id')->get();
        return view('evenements.form', compact('evenement', 'taches'));
    }

    public function update(UpdateEvenementRequest $request, int $id): RedirectResponse
    {
        $evenement = Evenement::findOrFail($id);
        $avant = $evenement->toArray();

        $data = $request->validated();
        $tacheIds = $data['taches'] ?? [];
        unset($data['taches']);

        $evenement->update($data);
        $evenement->tachesBloquees()->sync($tacheIds);

        audit('update', 'evenements', $evenement->id, $avant, array_merge(
            $evenement->fresh()->toArray(),
            ['taches_bloquees' => $tacheIds]
        ));

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