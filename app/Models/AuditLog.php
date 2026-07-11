<?php
// app/Models/AuditLog.php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle pour la table audit_logs.
 * Enregistre toute action sensible dans l'application.
 *
 * Utilisation via le helper global :
 *   audit('create', 'personnes', $personne->id, null, $personne->toArray());
 *
 * user_id est résolu automatiquement par AuditHelper depuis Auth::id().
 * Il est null pour les actions système (jobs en queue, webhook, etc.)
 */
class AuditLog extends Model
{
    protected $table = 'audit_logs';

    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'id_application',
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
        'after' => 'array',
    ];

    // ── Relations ─────────────────────────────────────────────────────────

    /**
     * La personne qui a effectué l'action.
     * Peut être null pour les actions système (jobs, webhook).
     */
    public function personne(): BelongsTo
    {
        return $this->belongsTo(Personne::class, 'user_id');
    }

    /**
     * L'application (ref_applications) à l'origine de cette entrée.
     * Permet à plusieurs applications AMANA de partager cette table.
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'id_application');
    }
}