<?php
// app/Helpers/AuditHelper.php

declare(strict_types=1);

namespace App\Helpers;

use Amana\Shared\Models\Application;
use Amana\Shared\Models\AuditLog;
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
     * ID de l'application courante dans amana_commun.ref_applications,
     * mis en cache pour la durée du processus (même motif que
     * RoleService::planningApp()). false = résolution déjà tentée et
     * échouée, pour ne pas refaire la requête à chaque appel.
     */
    private static int|false|null $applicationId = null;

    /**
     * Retourne l'id_application à écrire dans audit_logs (base partagée).
     * Null si l'application n'est pas encore enregistrée dans
     * ref_applications (voir PlanningApplicationSeeder) — l'entrée d'audit
     * est alors écrite sans rattachement plutôt que de faire échouer
     * l'action métier appelante.
     */
    public static function applicationId(): ?int
    {
        if (self::$applicationId === null) {
            $code = config('amana-shared.app_code', 'planning');
            self::$applicationId = Application::where('code', $code)->value('id') ?? false;
        }

        return self::$applicationId === false ? null : self::$applicationId;
    }

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
            'id_application' => self::applicationId(),
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