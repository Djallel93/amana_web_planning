<?php
// app/Models/Personne.php
//
// Étend le modèle partagé Amana\Shared\Models\Personne (voir amana/shared)
// avec les relations et la logique métier propres au planning uniquement
// (absences, restrictions, créneaux de tâches). roles(), isAdmin(),
// isGestionnaire(), isMembre(), les scopes valide()/enAttente(), et
// getNomCompletAttribute() sont hérités tels quels du modèle partagé.

declare(strict_types=1);

namespace App\Models;

use Amana\Shared\Models\Personne as SharedPersonne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Personne extends SharedPersonne
{
    public function absences(): HasMany
    {
        return $this->hasMany(Absence::class, 'id_personne');
    }

    public function restrictions(): HasMany
    {
        return $this->hasMany(Restriction::class, 'id_personne');
    }

    public function creneauxTaches(): HasMany
    {
        return $this->hasMany(CreneauTache::class, 'id_personne');
    }

    // ── Scope propre au planning : "actif" veut dire déjà dans la rotation ──

    public function scopeActifAuPlanning($query)
    {
        return $query->valide();
    }

    public function scopeAdminsPlanning($query)
    {
        return $query->adminsDe('planning');
    }

    // ── Métier propre au planning ────────────────────────────────────────

    public function estAbsentLe(string $date): bool
    {
        return $this->absences()
            ->where('date_debut', '<=', $date)
            ->where('date_fin', '>=', $date)
            ->exists();
    }

    /**
     * Cache d'instance du code du rôle planning courant — évite de refaire
     * la requête roles() à chaque appel de roleAutoriseTache() (celui-ci est
     * appelé en boucle, tâche × jour, par RotationEngine/DataLoader pour
     * chaque personne).
     */
    private ?string $planningRoleCodeCache = null;

    /**
     * Cache statique id_tache => code — la table ref_taches est petite
     * (5 lignes) et ne change jamais en cours de requête, une seule
     * requête suffit pour tout le cycle de génération.
     */
    private static ?array $tacheCodesById = null;

    public function peutFaireTache(int $idTache, string $jour): bool
    {
        if (!$this->roleAutoriseTache($idTache)) {
            return false;
        }

        $restriction = $this->restrictions()
            ->where('id_tache', $idTache)
            ->where('jour', $jour)
            ->first();

        return $restriction === null || $restriction->autorise;
    }

    /**
     * Filtre par rôle, appliqué AVANT la table `restrictions` (voir
     * config/planning.php). Les préférences personnelles de l'utilisateur
     * (page Disponibilités) ne sont ni lues ni modifiées ici — un rôle non
     * listé dans role_task_restrictions (membre, gestionnaire, admin) n'a
     * aucune restriction liée au rôle, seule la table `restrictions`
     * s'applique pour lui, comme avant l'introduction de ce filtre.
     */
    private function roleAutoriseTache(int $idTache): bool
    {
        $restrictionsParRole = config('planning.role_task_restrictions', []);

        if (empty($restrictionsParRole)) {
            return true;
        }

        $roleCode = $this->planningRoleCodeCache ??= (string) ($this->roles()
            ->whereHas('application', fn($q) => $q->where('code', 'planning'))
            ->value('ref_roles.code') ?? '');

        if (!isset($restrictionsParRole[$roleCode])) {
            return true;
        }

        self::$tacheCodesById ??= Tache::pluck('code', 'id')->all();
        $tacheCode = self::$tacheCodesById[$idTache] ?? null;

        return $tacheCode !== null && in_array($tacheCode, $restrictionsParRole[$roleCode], true);
    }
}
