<?php
// app/Models/Absence.php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle pour plan_absences.
 */
class Absence extends Model
{
    protected $table = 'plan_absences';
    public $timestamps = false;

    protected $fillable = ['id_personne', 'date_debut', 'date_fin', 'raison'];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin'   => 'date',
    ];

    public function personne(): BelongsTo
    {
        return $this->belongsTo(Personne::class, 'id_personne');
    }

    /** Scope : absences actives à une date donnée */
    public function scopeActiveALaDate($query, string $date)
    {
        return $query->where('date_debut', '<=', $date)
                     ->where('date_fin', '>=', $date);
    }

    /** Scope : absences futures */
    public function scopeFutures($query)
    {
        return $query->where('date_fin', '>=', now()->toDateString());
    }
}
