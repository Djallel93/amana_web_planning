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
 * Implémente toutes les interfaces Laravel nécessaires pour :
 *   - L'authentification (login / logout / remember me)
 *   - La réinitialisation de mot de passe (lien par email)
 *   - La vérification d'email
 *   - Les notifications (emails système)
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

    // Pas de created_at / updated_at standard — on a derniere_maj
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

    /**
     * Colonnes masquées lors de la sérialisation (ex: réponses JSON).
     * Le mot de passe et le token ne doivent jamais être exposés.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'date_debut_planning'       => 'date',
        'date_inscription_benevole' => 'date',
        'email_verified_at'         => 'datetime',
        'tirelire'                  => 'boolean',
        'derniere_maj'              => 'datetime',
    ];

    // ──────────────────────────────────────────────────────────────────────
    // RELATIONS
    // ──────────────────────────────────────────────────────────────────────

    public function vehicule(): BelongsTo
    {
        return $this->belongsTo(Vehicule::class, 'id_vehicule');
    }

    /**
     * Rôles de la personne, avec la possibilité de filtrer par application.
     *
     * Utilisation :
     *   $personne->roles                                    → tous les rôles
     *   $personne->rolesForApp('planning')                  → rôles planning
     */
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

    // ──────────────────────────────────────────────────────────────────────
    // MÉTHODES DE RÔLES
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Retourne les rôles de la personne pour une application donnée.
     *
     * Exemple : $personne->rolesForApp('planning')
     */
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

    /**
     * Vérifie si la personne a un rôle donné dans une application.
     *
     * Exemple : $personne->hasRole('admin', 'planning')
     */
    public function hasRole(string $roleCode, string $appCode = 'planning'): bool
    {
        return $this->roles()
            ->whereHas('application', fn($q) => $q->where('code', $appCode))
            ->where('ref_roles.code', $roleCode)
            ->exists();
    }

    /**
     * Vérifie si la personne est admin de l'application planning.
     * Raccourci pratique utilisé dans les vues et contrôleurs.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin', 'planning');
    }

    /**
     * Vérifie si la personne est gestionnaire de l'application planning.
     * Le gestionnaire a accès à tout sauf la gestion des utilisateurs.
     */
    public function isGestionnaire(): bool
    {
        return $this->hasRole('gestionnaire', 'planning');
    }

    /**
     * Vérifie si la personne est membre (ou admin ou gestionnaire) de l'application planning.
     * Un admin et un gestionnaire sont aussi considérés comme membres.
     */
    public function isMembre(): bool
    {
        return $this->hasRole('membre', 'planning') || $this->isAdmin() || $this->isGestionnaire();
    }

    // ──────────────────────────────────────────────────────────────────────
    // SCOPES
    // ──────────────────────────────────────────────────────────────────────

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

    public function scopeActifAuPlanning($query, string $date = null)
    {
        $date ??= now()->toDateString();
        return $query
            ->valide()
            ->membreOfficiel()
            ->where('date_debut_planning', '<=', $date);
    }

    /**
     * Scope : tous les admins de l'application planning.
     * Utilisé pour envoyer les notifications aux administrateurs.
     *
     * Exemple : Personne::adminsPlanning()->get()
     */
    public function scopeAdminsPlanning($query)
    {
        return $query->whereHas('roles', function ($q) {
            $q->where('ref_roles.code', 'admin')
              ->whereHas('application', fn($q2) => $q2->where('code', 'planning'));
        });
    }

    // ──────────────────────────────────────────────────────────────────────
    // ACCESSEURS
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Nom complet "Prénom NOM" — utilisé dans les vues et les emails.
     */
    public function getNomCompletAttribute(): string
    {
        return $this->prenom . ' ' . strtoupper($this->nom);
    }

    /**
     * Requis par Laravel Notifiable pour savoir où envoyer les notifications.
     * On retourne l'email de la personne.
     */
    public function routeNotificationForMail(): string
    {
        return $this->email;
    }

    // ──────────────────────────────────────────────────────────────────────
    // MÉTHODES MÉTIER
    // ──────────────────────────────────────────────────────────────────────

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
