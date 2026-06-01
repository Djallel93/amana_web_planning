<?php
// app/Helpers/AuditHelper.php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;

/**
 * Helper pour journaliser les actions sensibles.
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
     * @param string     $action      create | update | delete | generate | login | logout
     * @param string     $module      personnes | planning | restrictions | absences | evenements | auth
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