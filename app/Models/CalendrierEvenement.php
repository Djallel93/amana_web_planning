<?php
// app/Models/CalendrierEvenement.php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle pour plan_calendrier_evenements.
 *
 * Une ligne = un événement Google Calendar existant, pour un (créneau, code
 * de tâche, calendrier) donné. Alimentée par SynchroniserGoogleCalendar
 * après chaque création/mise à jour réussie côté Google Calendar API, et
 * consultée avant tout patch/delete pour retrouver l'event_id exact — voir
 * database/migrations/2026_07_16_000002_create_plan_calendrier_evenements_table.php
 * pour le détail du choix de schéma.
 */
class CalendrierEvenement extends Model
{
    protected $table = 'plan_calendrier_evenements';

    protected $fillable = [
        'id_planning',
        'id_tache',
        'google_calendar_id',
        'google_event_id',
    ];

    public function creneau(): BelongsTo
    {
        return $this->belongsTo(Creneau::class, 'id_planning');
    }

    public function tache(): BelongsTo
    {
        return $this->belongsTo(Tache::class, 'id_tache');
    }
}
