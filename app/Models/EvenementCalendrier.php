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
 */
class EvenementCalendrier extends Model
{
    protected $table = 'ref_evenements_calendriers';
    public $timestamps = false;

    protected $fillable = ['id_evenement', 'calendar_name'];

    public function evenement(): BelongsTo
    {
        return $this->belongsTo(Evenement::class, 'id_evenement');
    }
}
