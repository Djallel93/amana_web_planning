<?php
// app/Services/RoleService.php

declare(strict_types=1);

namespace App\Services;

use App\Models\Application;
use App\Models\Personne;
use App\Models\Role;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service centralisé pour la gestion des rôles de l'application Planning.
 *
 * Encapsule :
 *   - la résolution (avec cache par requête) de l'application 'planning'
 *   - la liste des rôles disponibles
 *   - la synchronisation du rôle d'une personne
 *   - la lecture du rôle courant d'une personne
 *
 * Utilisé par :
 *   - PersonnesController
 *   - CandidaturesController
 *   - ResetAdminPassword
 */
class RoleService
{
    private ?Application $planningApp = null;

    // ── Résolution de l'application ───────────────────────────────────────

    /**
     * Retourne l'application 'planning', avec cache pour la durée de la requête.
     */
    public function planningApp(): ?Application
    {
        return $this->planningApp ??= Application::where('code', 'planning')->first();
    }

    // ── Rôles disponibles ─────────────────────────────────────────────────

    /**
     * Retourne les rôles planning affichables dans les formulaires.
     */
    public function planningRoles(): Collection
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

    // ── Lecture du rôle courant ───────────────────────────────────────────

    /**
     * Retourne le code du rôle planning actuellement attribué à une personne.
     * Retourne null si aucun rôle planning n'est attribué.
     */
    public function currentRoleCode(Personne $personne): ?string
    {
        $role = $personne->roles()
            ->whereHas('application', fn($q) => $q->where('code', 'planning'))
            ->first();

        return $role?->code;
    }

    // ── Synchronisation du rôle ───────────────────────────────────────────

    /**
     * Attribue un rôle planning à une personne.
     *
     * Supprime d'abord tous les rôles planning existants de la personne,
     * puis insère le nouveau rôle demandé.
     *
     * Silencieux si l'application 'planning' n'existe pas en base.
     */
    public function syncRolePlanning(Personne $personne, string $roleCode): void
    {
        $app = $this->planningApp();

        if (!$app) {
            return;
        }

        // Supprimer tous les rôles planning existants
        $planningRoleIds = Role::where('id_application', $app->id)->pluck('id')->toArray();

        if (!empty($planningRoleIds)) {
            DB::table('ref_personnes_roles')
                ->where('id_personne', $personne->id)
                ->whereIn('id_role', $planningRoleIds)
                ->delete();
        }

        // Insérer le nouveau rôle
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
}