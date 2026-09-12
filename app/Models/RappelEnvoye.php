<?php
// app/Models/RappelEnvoye.php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modèle pour plan_rappels_envoyes — trace des rappels par email déjà
 * envoyés, pour dédupliquer entre exécutions de RappelsQuotidiens /
 * RappelsImminents (voir RappelService).
 */
class RappelEnvoye extends Model
{
    protected $table = 'plan_rappels_envoyes';
    public $timestamps = false;

    protected $fillable = [
        'id_planning',
        'id_tache',
        'id_personne',
        'type_rappel',
        'envoye_at',
    ];

    protected function casts(): array
    {
        return [
            'envoye_at' => 'datetime',
        ];
    }
}
