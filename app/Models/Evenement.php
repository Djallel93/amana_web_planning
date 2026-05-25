<?php
// app/Models/Evenement.php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Modèle pour ref_evenements.
 * Double usage : blocage du planning ET/OU mobilisation de bénévoles.
 */
class Evenement extends Model
{
    protected $table = 'ref_evenements';
    public $timestamps = false;

    protected $fillable = [
        'nom',
        'date_debut',
        'date_fin',
        'bloque_planning',
        'necessite_benevoles',
        'description',
    ];

    protected $casts = [
        'date_debut'          => 'date',
        'date_fin'            => 'date',
        'bloque_planning'     => 'boolean',
        'necessite_benevoles' => 'boolean',
    ];

    public function creneaux(): BelongsToMany
    {
        return $this->belongsToMany(
            Creneau::class,
            'plan_creneaux_evenements',
            'id_evenement',
            'id_planning'
        );
    }

    /** Scope : événements qui bloquent le planning */
    public function scopeBloqueant($query)
    {
        return $query->where('bloque_planning', true);
    }

    /** Scope : événements actifs (couvrant la date donnée) */
    public function scopeActifALaDate($query, string $date)
    {
        return $query->where('date_debut', '<=', $date)
                     ->where('date_fin', '>=', $date);
    }

    /** Scope : événements futurs ou en cours */
    public function scopeFutursOuEnCours($query)
    {
        return $query->where('date_fin', '>=', now()->toDateString());
    }
}
