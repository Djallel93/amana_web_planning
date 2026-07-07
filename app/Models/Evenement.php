<?php
// app/Models/Evenement.php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle pour ref_evenements.
 *
 * Un événement peut bloquer certaines tâches lors de la génération du planning.
 * Si aucune tâche n'est liée → événement purement informatif (s'affiche dans
 * la bannière de semaine sans affecter les assignations).
 *
 * Si au moins un calendrier est renseigné (relation calendriers), un webhook
 * Make.com est déclenché lors de la création, modification ou suppression de
 * l'événement pour synchroniser Google Calendar — un événement peut désormais
 * être synchronisé sur PLUSIEURS calendriers à la fois.
 *
 * @property int         $id
 * @property string      $nom
 * @property \Carbon\Carbon $date_debut
 * @property \Carbon\Carbon $date_fin
 * @property string|null $description
 */
class Evenement extends Model
{
    protected $table = 'ref_evenements';
    public $timestamps = false;

    protected $fillable = [
        'nom',
        'date_debut',
        'date_fin',
        'description',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin'   => 'date',
    ];

    // ── Relations ──────────────────────────────────────────────────────────

    /**
     * Créneaux liés à cet événement (N-N).
     */
    public function creneaux(): BelongsToMany
    {
        return $this->belongsToMany(
            Creneau::class,
            'plan_creneaux_evenements',
            'id_evenement',
            'id_planning'
        );
    }

    /**
     * Tâches bloquées par cet événement (N-N).
     * Si vide → événement informatif uniquement.
     */
    public function tachesBloquees(): BelongsToMany
    {
        return $this->belongsToMany(
            Tache::class,
            'ref_evenements_taches',
            'id_evenement',
            'id_tache'
        );
    }

    /**
     * Calendriers Google Calendar sur lesquels cet événement est synchronisé
     * (1-N — un événement peut être synchronisé sur plusieurs calendriers).
     * Si vide → pas de synchronisation calendrier.
     */
    public function calendriers(): HasMany
    {
        return $this->hasMany(EvenementCalendrier::class, 'id_evenement');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Retourne true si cet événement bloque au moins une tâche.
     */
    public function bloqueDesTaches(): bool
    {
        return $this->tachesBloquees->isNotEmpty();
    }

    /**
     * Retourne true si cet événement bloque toutes les tâches actives.
     */
    public function bloqueTout(int $nbTachesActives): bool
    {
        return $this->tachesBloquees->count() >= $nbTachesActives;
    }

    /**
     * Retourne true si une synchronisation calendrier est configurée
     * (au moins un calendrier lié).
     */
    public function hasCalendarSync(): bool
    {
        if (!$this->relationLoaded('calendriers')) {
            $this->load('calendriers');
        }
        return $this->calendriers->isNotEmpty();
    }

    /**
     * Retourne la liste des noms de calendriers liés à cet événement.
     *
     * @return array<int, string>
     */
    public function calendarNames(): array
    {
        if (!$this->relationLoaded('calendriers')) {
            $this->load('calendriers');
        }
        return $this->calendriers->pluck('calendar_name')->values()->all();
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeActifALaDate($query, string $date)
    {
        return $query->where('date_debut', '<=', $date)
            ->where('date_fin', '>=', $date);
    }

    public function scopeFutursOuEnCours($query)
    {
        return $query->where('date_fin', '>=', now()->toDateString());
    }
}
