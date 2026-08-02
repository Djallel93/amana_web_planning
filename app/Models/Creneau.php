<?php
// app/Models/Creneau.php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Modèle pour plan_creneaux.
 * Un créneau = une date de permanence (vendredi ou samedi).
 */
class Creneau extends Model
{
    protected $table = 'plan_creneaux';
    public $timestamps = false;

    protected $fillable = ['date'];

    protected $casts = [
        'date' => 'date',
    ];

    // ── Relations ──────────────────────────────────────────────────────────

    /**
     * Assignations de tâches pour ce créneau.
     */
    public function taches(): HasMany
    {
        return $this->hasMany(CreneauTache::class, 'id_planning');
    }

    /**
     * Événements organisationnels liés à ce créneau (N-N).
     */
    public function evenements(): BelongsToMany
    {
        return $this->belongsToMany(
            Evenement::class,
            'plan_creneaux_evenements',
            'id_planning',
            'id_evenement'
        );
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    /** Créneau à venir (date >= aujourd'hui) */
    public function scopeFuturs($query)
    {
        return $query->where('date', '>=', now()->toDateString());
    }

    /** Créneau passé */
    public function scopePasses($query)
    {
        return $query->where('date', '<', now()->toDateString());
    }

    /** Créneau dans une plage de dates */
    public function scopeEntreDates($query, string $debut, string $fin)
    {
        return $query->whereBetween('date', [$debut, $fin]);
    }

    // ── Accesseurs ─────────────────────────────────────────────────────────

    /**
     * Retourne le jour de la semaine en français.
     * Exemple : "Vendredi", "Samedi"
     */
    public function getJourAttribute(): string
    {
        $jours = [
            0 => 'Dimanche',
            1 => 'Lundi',
            2 => 'Mardi',
            3 => 'Mercredi',
            4 => 'Jeudi',
            5 => 'Vendredi',
            6 => 'Samedi',
        ];
        return $jours[$this->date->dayOfWeek] ?? '';
    }

    /**
     * Numéro de semaine ISO.
     */
    public function getSemaineAttribute(): int
    {
        return (int) $this->date->isoWeek();
    }

    // ── Métier ─────────────────────────────────────────────────────────────

    /**
     * Retourne la collection des codes de tâches bloquées par les événements
     * liés à ce créneau.
     *
     * Suppose que la relation evenements.tachesBloquees est déjà eager-loaded
     * (ce qui est le cas dans PlanningController::index()).
     *
     * Exemple : collect(['amana_food', 'entree'])
     */
    public function tachesBloqueesCodes(): Collection
    {
        return $this->evenements
            ->flatMap(fn($evenement) => $evenement->tachesBloquees->pluck('code'))
            ->unique()
            ->values();
    }
}