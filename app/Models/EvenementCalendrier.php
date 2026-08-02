<?php
// app/Models/EvenementCalendrier.php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle pour ref_evenements_calendriers.
 *
 * Une ligne = un calendrier Google Calendar sur lequel un événement
 * organisationnel donné doit être synchronisé. Un événement peut avoir
 * zéro (pas de synchro), un, ou plusieurs calendriers.
 *
 * `calendar_name` reste un libellé d'affichage (résolu depuis le registre
 * `ref_calendriers_google` au moment de l'enregistrement, voir
 * EvenementsController::syncCalendriers()) — l'identifiant réellement utilisé pour parler à l'API Google Calendar est
 * `google_calendar_id`. `google_event_id` est renseigné après la première
 * création réussie côté Google Calendar (voir SynchroniserGoogleCalendar) et
 * réutilisé tel quel pour les mises à jour/suppressions suivantes.
 */
class EvenementCalendrier extends Model
{
    protected $table = 'ref_evenements_calendriers';
    public $timestamps = false;

    protected $fillable = ['id_evenement', 'calendar_name', 'google_calendar_id', 'google_event_id'];

    public function evenement(): BelongsTo
    {
        return $this->belongsTo(Evenement::class, 'id_evenement');
    }
}
