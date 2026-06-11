<?php
// app/Models/Personne.php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Notifications\Notifiable;

/**
 * Modèle principal — remplace User.php de Laravel.
 * Colonnes supprimées : id_vehicule, tirelire, date_inscription_benevole
 * (appartiennent aux modules livraisons/tirelire/bénévoles).
 */
class Personne extends Model implements
    AuthenticatableContract,
    AuthorizableContract,
    CanResetPasswordContract,
    MustVerifyEmailContract
{
    use Authenticatable, Authorizable, CanResetPassword, MustVerifyEmail, Notifiable;

    protected $table = 'ref_personnes';
    public $timestamps = false;

    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'password',
        'telephone',
        'date_debut_planning',
        'statut',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'date_debut_planning' => 'date',
        'email_verified_at' => 'datetime',
        'derniere_maj' => 'datetime',
    ];

    // ── Relations ─────────────────────────────────────────────────────────

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'ref_personnes_roles', 'id_personne', 'id_role')
            ->withPivot('date_attribution');
    }

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

    // ── Rôles ─────────────────────────────────────────────────────────────

    public function hasRole(string $roleCode, string $appCode = 'planning'): bool
    {
        return $this->roles()
            ->whereHas('application', fn($q) => $q->where('code', $appCode))
            ->where('ref_roles.code', $roleCode)
            ->exists();
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }
    public function isGestionnaire(): bool
    {
        return $this->hasRole('gestionnaire');
    }
    public function isMembre(): bool
    {
        return $this->hasRole('membre') || $this->isAdmin() || $this->isGestionnaire();
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeValide($query)
    {
        return $query->where('statut', 'Validé');
    }
    public function scopeEnAttente($query)
    {
        return $query->where('statut', 'En attente');
    }
    public function scopeActifAuPlanning($query)
    {
        return $query->valide();
    }

    public function scopeAdminsPlanning($query)
    {
        return $query->whereHas('roles', function ($q) {
            $q->where('ref_roles.code', 'admin')
                ->whereHas('application', fn($q2) => $q2->where('code', 'planning'));
        });
    }

    // ── Accesseurs ────────────────────────────────────────────────────────

    public function getNomCompletAttribute(): string
    {
        return $this->prenom . ' ' . strtoupper($this->nom);
    }

    public function routeNotificationForMail(): string
    {
        return $this->email;
    }

    // ── Métier ────────────────────────────────────────────────────────────

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