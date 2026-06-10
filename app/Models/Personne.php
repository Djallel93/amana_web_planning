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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Notifications\Notifiable;

/**
 * Modèle principal de l'application — remplace User.php de Laravel.
 *
 * @property int         $id
 * @property string      $nom
 * @property string      $prenom
 * @property string      $email
 * @property string|null $password
 * @property string|null $remember_token
 * @property string|null $email_verified_at
 * @property string|null $telephone
 * @property \Carbon\Carbon|null $date_debut_planning
 * @property \Carbon\Carbon|null $date_inscription_benevole
 * @property string      $statut
 * @property bool        $tirelire
 * @property int|null    $id_vehicule
 */
class Personne extends Model implements
    AuthenticatableContract,
    AuthorizableContract,
    CanResetPasswordContract,
    MustVerifyEmailContract
{
    use Authenticatable;
    use Authorizable;
    use CanResetPassword;
    use MustVerifyEmail;
    use Notifiable;

    protected $table = 'ref_personnes';

    public $timestamps = false;

    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'password',
        'telephone',
        'date_debut_planning',
        'date_inscription_benevole',
        'statut',
        'tirelire',
        'id_vehicule',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'date_debut_planning' => 'date',
        'date_inscription_benevole' => 'date',
        'email_verified_at' => 'datetime',
        'tirelire' => 'boolean',
        'derniere_maj' => 'datetime',
    ];

    // ── Relations ─────────────────────────────────────────────────────────

    public function vehicule(): BelongsTo
    {
        return $this->belongsTo(Vehicule::class, 'id_vehicule');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'ref_personnes_roles',
            'id_personne',
            'id_role'
        )->withPivot('date_attribution');
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

    // ── Méthodes de rôles ─────────────────────────────────────────────────

    public function rolesForApp(string $appCode): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'ref_personnes_roles',
            'id_personne',
            'id_role'
        )
            ->withPivot('date_attribution')
            ->whereHas('application', fn($q) => $q->where('code', $appCode));
    }

    public function hasRole(string $roleCode, string $appCode = 'planning'): bool
    {
        return $this->roles()
            ->whereHas('application', fn($q) => $q->where('code', $appCode))
            ->where('ref_roles.code', $roleCode)
            ->exists();
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin', 'planning');
    }

    public function isGestionnaire(): bool
    {
        return $this->hasRole('gestionnaire', 'planning');
    }

    public function isMembre(): bool
    {
        return $this->hasRole('membre', 'planning') || $this->isAdmin() || $this->isGestionnaire();
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

    public function scopeMembreOfficiel($query)
    {
        return $query->whereNotNull('date_debut_planning');
    }

    public function scopeBenevole($query)
    {
        return $query->whereNotNull('date_inscription_benevole');
    }

    /**
     * Scope : toutes les personnes avec statut 'Validé'.
     *
     * date_debut_planning n'est pas un critère d'affichage — elle sert
     * uniquement dans le scheduler pour ne pas assigner un nouveau membre
     * avant sa date d'arrivée, et dans les stats pour ne pas le pénaliser.
     * Toute personne validée dans cette app est un membre officiel.
     *
     * Le paramètre $date est conservé pour compatibilité avec les appels
     * existants dans le scheduler mais n'est plus utilisé ici.
     */
    public function scopeActifAuPlanning($query, string $date = null)
    {
        return $query->valide();
    }

    /**
     * Scope : tous les admins de l'application planning.
     */
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

    // ── Méthodes métier ───────────────────────────────────────────────────

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