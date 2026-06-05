<?php
// app/Http/Controllers/PersonnesController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Personnes\StorePersonneRequest;
use App\Http\Requests\Personnes\UpdatePersonneRequest;
use App\Models\Application;
use App\Models\Personne;
use App\Models\Role;
use App\Models\Vehicule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Contrôleur CRUD pour les personnes.
 * Toutes les personnes sont visibles pour l'admin.
 * La gestion du rôle planning est incluse dans create/edit.
 */
class PersonnesController extends Controller
{
    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Récupère l'application planning (avec cache léger via static).
     */
    private function planningApp(): ?Application
    {
        static $app = null;
        return $app ??= Application::where('code', 'planning')->first();
    }

    /**
     * Récupère les rôles disponibles pour l'application planning.
     * Retourne une collection de Role.
     */
    private function planningRoles()
    {
        $app = $this->planningApp();
        if (!$app) {
            return collect();
        }
        return Role::where('id_application', $app->id)
            ->whereIn('code', ['admin', 'gestionnaire', 'membre', 'benevole'])
            ->orderByRaw("FIELD(code, 'admin', 'gestionnaire', 'membre', 'benevole')")
            ->get();
    }

    /**
     * Attribue un rôle planning à une personne.
     * Supprime d'abord tous les rôles planning existants (un seul rôle par app).
     */
    private function syncRolePlanning(Personne $personne, string $roleCode): void
    {
        $app = $this->planningApp();
        if (!$app) {
            return;
        }

        // Récupérer tous les IDs de rôles de l'application planning
        $planningRoleIds = Role::where('id_application', $app->id)->pluck('id')->toArray();

        // Supprimer tous les rôles planning existants pour cette personne
        if (!empty($planningRoleIds)) {
            DB::table('ref_personnes_roles')
                ->where('id_personne', $personne->id)
                ->whereIn('id_role', $planningRoleIds)
                ->delete();
        }

        // Attribuer le nouveau rôle
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

    /**
     * Retourne le code du rôle planning actuel d'une personne (ou null).
     */
    private function currentRoleCode(Personne $personne): ?string
    {
        $app = $this->planningApp();
        if (!$app) {
            return null;
        }

        $role = $personne->roles()
            ->whereHas('application', fn($q) => $q->where('code', 'planning'))
            ->first();

        return $role?->code;
    }

    // ── CRUD ──────────────────────────────────────────────────────────────

    /**
     * Liste TOUTES les personnes (sans filtre de rôle).
     */
    public function index(): View
    {
        $personnes = Personne::with([
            'roles' => function ($q) {
                // Eager-load only planning roles for display
                $q->whereHas('application', fn($q2) => $q2->where('code', 'planning'));
            }
        ])
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
        $statuts = ['En attente', 'Validé', 'Suspendu', 'Archivé'];
        $roles = $this->planningRoles();

        return view('personnes.form', compact('vehicules', 'statuts', 'roles'));
    }

    /**
     * Enregistre une nouvelle personne avec son rôle.
     */
    public function store(StorePersonneRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $roleCode = $data['role'];
        unset($data['role']); // ne pas passer au modèle

        $personne = Personne::create($data);

        // Attribuer le rôle choisi
        $this->syncRolePlanning($personne, $roleCode);

        audit('create', 'personnes', $personne->id, null, array_merge(
            $personne->toArray(),
            ['role' => $roleCode]
        ));

        return redirect()->route('personnes.index')
            ->with('success', "Personne « {$personne->prenom} {$personne->nom} » créée avec le rôle {$roleCode}.");
    }

    /**
     * Affiche le formulaire d'édition.
     */
    public function edit(int $id): View
    {
        $personne = Personne::findOrFail($id);
        $vehicules = Vehicule::orderBy('type')->get();
        $statuts = ['En attente', 'Validé', 'Suspendu', 'Archivé'];
        $roles = $this->planningRoles();
        $currentRole = $this->currentRoleCode($personne);

        return view('personnes.form', compact('personne', 'vehicules', 'statuts', 'roles', 'currentRole'));
    }

    /**
     * Met à jour une personne et son rôle planning.
     */
    public function update(UpdatePersonneRequest $request, int $id): RedirectResponse
    {
        $personne = Personne::findOrFail($id);
        $avant = $personne->toArray();

        $data = $request->validated();
        $roleCode = $data['role'];
        unset($data['role']);

        $personne->update($data);

        // Synchroniser le rôle (remplace l'ancien)
        $this->syncRolePlanning($personne, $roleCode);

        audit('update', 'personnes', $personne->id, $avant, array_merge(
            $personne->fresh()->toArray(),
            ['role' => $roleCode]
        ));

        return redirect()->route('personnes.index')
            ->with('success', "Personne « {$personne->prenom} {$personne->nom} » mise à jour.");
    }

    /**
     * Supprime une personne.
     */
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