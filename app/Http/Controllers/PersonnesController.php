<?php
// app/Http/Controllers/PersonnesController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Personnes\StorePersonneRequest;
use App\Http\Requests\Personnes\UpdatePersonneRequest;
use App\Models\Application;
use App\Models\Personne;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PersonnesController extends Controller
{
    // ── Helpers ───────────────────────────────────────────────────────────

    private function planningApp(): ?Application
    {
        static $app = null;
        return $app ??= Application::where('code', 'planning')->first();
    }

    private function planningRoles()
    {
        $app = $this->planningApp();
        if (!$app)
            return collect();

        return Role::where('id_application', $app->id)
            ->whereIn('code', ['admin', 'gestionnaire', 'membre', 'benevole'])
            ->orderByRaw("FIELD(code, 'admin', 'gestionnaire', 'membre', 'benevole')")
            ->get();
    }

    private function syncRolePlanning(Personne $personne, string $roleCode): void
    {
        $app = $this->planningApp();
        if (!$app)
            return;

        $planningRoleIds = Role::where('id_application', $app->id)->pluck('id')->toArray();
        if (!empty($planningRoleIds)) {
            DB::table('ref_personnes_roles')
                ->where('id_personne', $personne->id)
                ->whereIn('id_role', $planningRoleIds)
                ->delete();
        }

        $role = Role::where('code', $roleCode)
            ->where('id_application', $app->id)
            ->first();

        if ($role) {
            DB::table('ref_personnes_roles')->insert([
                'id_personne' => $personne->id,
                'id_role' => $role->id,
                'date_attribution' => now()->toDateString(),
            ]);
        }
    }

    private function currentRoleCode(Personne $personne): ?string
    {
        $app = $this->planningApp();
        if (!$app)
            return null;

        $role = $personne->roles()
            ->whereHas('application', fn($q) => $q->where('code', 'planning'))
            ->first();

        return $role?->code;
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
        $roles = $this->planningRoles();

        return view('personnes.form', compact('statuts', 'roles'));
    }

    public function store(StorePersonneRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $roleCode = $data['role'];
        unset($data['role']);

        $personne = Personne::create($data);
        $this->syncRolePlanning($personne, $roleCode);

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
        $roles = $this->planningRoles();
        $currentRole = $this->currentRoleCode($personne);

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
        $this->syncRolePlanning($personne, $roleCode);

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