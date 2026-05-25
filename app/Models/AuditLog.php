<?php
// app/Models/AuditLog.php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modèle pour la table audit_logs.
 * Enregistre toute action sensible dans l'application.
 *
 * Utilisation via le helper global :
 *   audit('create', 'personnes', $personne->id, null, $personne->toArray());
 */
class AuditLog extends Model
{
    protected $table = 'audit_logs';

    // On utilise les timestamps Laravel standard (created_at / updated_at)
    public $timestamps = true;

    protected $fillable = [
        'action',
        'module',
        'entity_id',
        'entity_type',
        'before',
        'after',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'before' => 'array',
        'after'  => 'array',
    ];
}
