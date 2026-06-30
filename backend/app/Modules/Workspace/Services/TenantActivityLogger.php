<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Platform\Support\StructuredAuditLogWriter;
use App\Modules\Workspace\Models\TenantActivityLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;

final class TenantActivityLogger
{
    public function __construct(
        private readonly StructuredAuditLogWriter $structuredAudit,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        string $module,
        string $action,
        ?string $summary = null,
        ?string $entityType = null,
        ?string $entityId = null,
        ?string $entityLabel = null,
        Authenticatable|TenantUser|null $actor = null,
        array $metadata = [],
    ): TenantActivityLog {
        $actorId = $actor?->getAuthIdentifier();

        $log = TenantActivityLog::query()->create([
            'id' => (string) Str::uuid(),
            'module' => $module,
            'action' => $action,
            'summary' => $summary,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_label' => $entityLabel,
            'actor_user_id' => $actorId !== null ? (string) $actorId : null,
            'ip_address' => request()->ip(),
            'metadata_json' => $metadata === [] ? null : $metadata,
            'created_at' => now(),
        ]);

        $this->structuredAudit->write('tenant.workspace', $action, [
            'tenant_id' => tenant()?->getTenantKey(),
            'module' => $module,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_label' => $entityLabel,
            'actor_user_id' => $actorId,
            'summary' => $summary,
            'metadata' => $metadata,
        ]);

        return $log;
    }
}
