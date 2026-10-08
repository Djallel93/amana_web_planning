<?php
// app/Models/Absence.php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle pour plan_absences.
 */
class Absence extends Model
{
    use HasFactory;

    protected $table = 'plan_absences';

    public const MESSAGE_DOUBLON = 'Une absence identique (même personne, mêmes dates) existe déjà.';

    public $timestamps = false;

    /**
     * Voir Restriction::getConnectionName() pour le detail du piège évité
     * ici (héritage de connexion depuis Personne via
     * Personne::absences()).
     */
    public function getConnectionName(): ?string
    {
        return config('database.default');
    }

    /**
     * google_calendar_id / google_event_id ne sont jamais soumis par le
     * formulaire absences (StoreAbsenceRequest/UpdateAbsenceRequest ne les
     * valident pas) — seulement écrits par SynchroniserGoogleCalendar après
     * synchronisation, sur le même modèle qu'EvenementCalendrier.
     */
    protected $fillable = [
        'id_personne',
        'date_debut',
        'date_fin',
        'raison',
        'google_calendar_id',
        'google_event_id',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
    ];

    /** @return BelongsTo<Personne, $this> */
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

    /**
     * Scope : absences strictement identiques (même personne, mêmes dates de début
     * et de fin) — sert à refuser un doublon (double soumission du formulaire).
     */
    public function scopeIdentique($query, int $idPersonne, string $dateDebut, string $dateFin)
    {
        return $query->where('id_personne', $idPersonne)
            ->where('date_debut', $dateDebut)
            ->where('date_fin', $dateFin);
    }

    /** Scope : absences futures */
    public function scopeFutures($query)
    {
        return $query->where('date_fin', '>=', now()->toDateString());
    }
}
