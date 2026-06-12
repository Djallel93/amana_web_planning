<?php
// app/Helpers/AuditHelper.php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Helper pour journaliser les actions sensibles.
 *
 * user_id est résolu automatiquement depuis Auth::id() — aucun changement
 * nécessaire aux sites d'appel existants. Il est null quand aucun utilisateur
 * n'est authentifié (actions système, jobs en queue, webhook).
 *
 * À appeler dans chaque contrôleur après une action create/update/delete/generate.
 *
 * Exemples d'utilisation :
 *   audit('create',   'personnes',  $p->id,   null,            $p->toArray());
 *   audit('update',   'personnes',  $p->id,   $avant,          $apres);
 *   audit('delete',   'personnes',  $id,      $p->toArray(),   null);
 *   audit('generate', 'planning',   null,     null,            ['semaines' => 4]);
 *   audit('login',    'auth',       null,     null,            null);
 */
class AuditHelper
{
    /**
     * Enregistre une entrée dans le journal d'audit.
     *
     * @param string     $action      create | update | delete | generate | login | logout | webhook
     * @param string     $module      personnes | planning | restrictions | absences | evenements | auth | settings
     * @param int|null   $entityId    ID de l'entité concernée (null pour les actions globales)
     * @param array|null $before      État avant modification
     * @param array|null $after       État après modification
     */
    public static function log(
        string $action,
        string $module,
        ?int $entityId = null,
        ?array $before = null,
        ?array $after = null
    ): void {
        AuditLog::create([
            'user_id' => Auth::id(), // null pour les jobs en queue et actions système
            'action' => $action,
            'module' => $module,
            'entity_id' => $entityId,
            'entity_type' => null,
            'before' => $before,
            'after' => $after,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}