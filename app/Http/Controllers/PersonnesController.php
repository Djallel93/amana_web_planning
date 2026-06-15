<?php
// app/Http/Controllers/PersonnesController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Personnes\StorePersonneRequest;
use App\Http\Requests\Personnes\UpdatePersonneRequest;
use App\Models\Personne;
use App\Services\RoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PersonnesController extends Controller
{
    public function __construct(
        private readonly RoleService $roleService,
    ) {
    }

    // ── CRUD ──────────────────────────────────────────────────────────────

    public function index(): View
    {
        $personnes = Personne::with([
            'roles' => function ($q) {
                $q->whereHas('application', fn($q2) => $q2->where('code', 'planning'));
            }
        ])
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get();

        return view('personnes.index', compact('personnes'));
    }

    public function create(): View
    {
        $statuts = ['En attente', 'Validé', 'Suspendu', 'Archivé'];
        $roles = $this->roleService->planningRoles();

        return view('personnes.form', compact('statuts', 'roles'));
    }

    public function store(StorePersonneRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $roleCode = $data['role'];
        unset($data['role']);

        $personne = Personne::create($data);
        $this->roleService->syncRolePlanning($personne, $roleCode);

        audit('create', 'personnes', $personne->id, null, array_merge(
            $personne->toArray(),
            ['role' => $roleCode]
        ));

        return redirect()->route('personnes.index')
            ->with('success', "Personne « {$personne->prenom} {$personne->nom} » créée avec le rôle {$roleCode}.");
    }

    public function edit(int $id): View
    {
        $personne = Personne::findOrFail($id);
        $statuts = ['En attente', 'Validé', 'Suspendu', 'Archivé'];
        $roles = $this->roleService->planningRoles();
        $currentRole = $this->roleService->currentRoleCode($personne);

        return view('personnes.form', compact('personne', 'statuts', 'roles', 'currentRole'));
    }

    public function update(UpdatePersonneRequest $request, int $id): RedirectResponse
    {
        $personne = Personne::findOrFail($id);
        $avant = $personne->toArray();

        $data = $request->validated();
        $roleCode = $data['role'];
        unset($data['role']);

        $personne->update($data);
        $this->roleService->syncRolePlanning($personne, $roleCode);

        audit('update', 'personnes', $personne->id, $avant, array_merge(
            $personne->fresh()->toArray(),
            ['role' => $roleCode]
        ));

        return redirect()->route('personnes.index')
            ->with('success', "Personne « {$personne->prenom} {$personne->nom} » mise à jour.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $personne = Personne::findOrFail($id);
        $avant = $personne->toArray();
        $nom = "{$personne->prenom} {$personne->nom}";

        $personne->delete();

        audit('delete', 'personnes', $id, $avant, null);

        return redirect()->route('personnes.index')
            ->with('success', "Personne « {$nom} » supprimée.");
    }
}