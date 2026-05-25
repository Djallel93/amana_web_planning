<?php
// app/Models/Personne.php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle Eloquent pour la table ref_personnes.
 *
 * @property int         $id
 * @property string      $nom
 * @property string      $prenom
 * @property string      $email
 * @property string|null $telephone
 * @property \Carbon\Carbon|null $date_debut_planning
 * @property \Carbon\Carbon|null $date_inscription_benevole
 * @property string      $statut
 * @property bool        $tirelire
 * @property int|null    $id_vehicule
 */
class Personne extends Model
{
    // Le nom de la table ne suit pas la convention Laravel (pluriel anglais)
    protected $table = 'ref_personnes';

    // Pas de colonnes created_at / updated_at standard — on a derniere_maj
    public $timestamps = false;

    /**
     * Colonnes modifiables en masse (via create() ou fill()).
     * On liste explicitement pour éviter la mass-assignment vulnerability.
     */
    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'telephone',
        'date_debut_planning',
        'date_inscription_benevole',
        'statut',
        'tirelire',
        'id_vehicule',
    ];

    /**
     * Conversions automatiques de types.
     * Laravel convertit ces colonnes au bon type PHP lors de la lecture.
     */
    protected $casts = [
        'date_debut_planning'       => 'date',
        'date_inscription_benevole' => 'date',
        'tirelire'                  => 'boolean',
        'derniere_maj'              => 'datetime',
    ];

    // ──────────────────────────────────────────────────────────────────────
    // RELATIONS
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Véhicule associé à cette personne (pour les bénévoles livreurs).
     */
    public function vehicule(): BelongsTo
    {
        return $this->belongsTo(Vehicule::class, 'id_vehicule');
    }

    /**
     * Rôles de la personne (relation N-N via ref_personnes_roles).
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

    /**
     * Absences de cette personne.
     */
    public function absences(): HasMany
    {
        return $this->hasMany(Absence::class, 'id_personne');
    }

    /**
     * Restrictions de disponibilité (personne × tâche × jour).
     */
    public function restrictions(): HasMany
    {
        return $this->hasMany(Restriction::class, 'id_personne');
    }

    /**
     * Assignations dans le planning (via plan_creneaux_taches).
     */
    public function creneauxTaches(): HasMany
    {
        return $this->hasMany(CreneauTache::class, 'id_personne');
    }

    // ──────────────────────────────────────────────────────────────────────
    // SCOPES (filtres réutilisables)
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Scope : personnes avec le statut "Validé" uniquement.
     * Usage : Personne::valide()->get()
     */
    public function scopeValide($query)
    {
        return $query->where('statut', 'Validé');
    }

    /**
     * Scope : membres officiels du planning (date_debut_planning renseignée).
     * Usage : Personne::membreOfficiel()->get()
     */
    public function scopeMembreOfficiel($query)
    {
        return $query->whereNotNull('date_debut_planning');
    }

    /**
     * Scope : bénévoles inscrits (date_inscription_benevole renseignée).
     */
    public function scopeBenevole($query)
    {
        return $query->whereNotNull('date_inscription_benevole');
    }

    /**
     * Scope : personnes actives dans le planning à une date donnée.
     * Usage : Personne::actifAuPlanning('2025-01-10')->get()
     */
    public function scopeActifAuPlanning($query, string $date = null)
    {
        $date ??= now()->toDateString();
        return $query
            ->valide()
            ->membreOfficiel()
            ->where('date_debut_planning', '<=', $date);
    }

    // ──────────────────────────────────────────────────────────────────────
    // ACCESSEURS
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Nom complet "Prénom NOM".
     */
    public function getNomCompletAttribute(): string
    {
        return $this->prenom . ' ' . strtoupper($this->nom);
    }

    /**
     * Vérifie si la personne est absente à une date donnée.
     */
    public function estAbsentLe(string $date): bool
    {
        return $this->absences()
            ->where('date_debut', '<=', $date)
            ->where('date_fin', '>=', $date)
            ->exists();
    }

    /**
     * Vérifie si la personne peut effectuer une tâche un jour donné.
     *
     * @param int    $idTache  ID de la tâche
     * @param string $jour     Jour en français : 'Vendredi', 'Samedi', etc.
     */
    public function peutFaireTache(int $idTache, string $jour): bool
    {
        $restriction = $this->restrictions()
            ->where('id_tache', $idTache)
            ->where('jour', $jour)
            ->first();

        // Si aucune restriction trouvée → par défaut, autorisé
        return $restriction === null || $restriction->autorise;
    }
}
