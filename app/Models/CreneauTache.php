<?php
// app/Models/CreneauTache.php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle pour plan_creneaux_taches.
 * Liaison entre un créneau, une tâche et la personne assignée.
 */
class CreneauTache extends Model
{
    protected $table = 'plan_creneaux_taches';
    public $timestamps = false;
    // Clé primaire composite — Eloquent ne supporte pas nativement,
    // on désactive l'auto-increment
    public $incrementing = false;
    protected $primaryKey = null;

    protected $fillable = ['id_planning', 'id_tache', 'id_personne'];

    public function creneau(): BelongsTo
    {
        return $this->belongsTo(Creneau::class, 'id_planning');
    }

    public function tache(): BelongsTo
    {
        return $this->belongsTo(Tache::class, 'id_tache');
    }

    public function personne(): BelongsTo
    {
        return $this->belongsTo(Personne::class, 'id_personne');
    }

    /** Scope : tâches non assignées (personne = NULL) */
    public function scopeNonAssigne($query)
    {
        return $query->whereNull('id_personne');
    }
}
