<?php
// app/Models/CalendrierGoogle.php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Registre des calendriers Google Calendar connus de l'application — voir
 * le docblock de la migration
 * 2026_05_24_000002_create_ref_tables.php pour le détail
 * de pourquoi cette table existe (impossibilité de découvrir automatiquement
 * les calendriers partagés avec un compte de service).
 *
 * Alimente `/api/calendriers` (CalendriersController), consommé par le
 * dropdown de sélection de calendrier (SearchableSelect.vue) côté
 * Paramètres et formulaire d'événement — sans appel Google Calendar API,
 * uniquement une lecture DB.
 */
class CalendrierGoogle extends Model
{
    protected $table = 'ref_calendriers_google';

    protected $fillable = [
        'calendar_id',
        'nom',
        'description',
        'actif',
        'inclure_nouveaux_membres',
        'derniere_verification_at',
    ];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
            'inclure_nouveaux_membres' => 'boolean',
            'derniere_verification_at' => 'datetime',
        ];
    }
}
