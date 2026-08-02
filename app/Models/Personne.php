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

    public function peutFaireTache(int $idTache, string $jour): bool
    {
        $restriction = $this->restrictions()
            ->where('id_tache', $idTache)
            ->where('jour', $jour)
            ->first();

        return $restriction === null || $restriction->autorise;
    }
}
