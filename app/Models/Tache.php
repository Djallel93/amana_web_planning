<?php
// app/Models/Tache.php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle pour ref_taches.
 * Référentiel des tâches planifiables (entree, mektaba, salle, amana_food).
 */
class Tache extends Model
{
    protected $table = 'ref_taches';
    public $timestamps = false;

    protected $fillable = ['code', 'libelle', 'actif'];

    protected $casts = [
        'actif' => 'boolean',
    ];

    public function restrictions(): HasMany
    {
        return $this->hasMany(Restriction::class, 'id_tache');
    }

    public function creneauxTaches(): HasMany
    {
        return $this->hasMany(CreneauTache::class, 'id_tache');
    }

    /** Scope : uniquement les tâches actives */
    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }
}
