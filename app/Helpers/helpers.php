<?php

declare(strict_types=1);

use App\Helpers\AuditHelper;

if (! function_exists('audit')) {
    function audit(
        string $action,
        string $module,
        ?int $entityId = null,
        ?array $before = null,
        ?array $after = null
    ): void {
        AuditHelper::log($action, $module, $entityId, $before, $after);
    }
}