<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Platform\Support\StructuredAuditLogWriter;
use App\Modules\Workspace\Models\TenantActivityLog;
use App\Modules\Workspace\Support\WorkspaceAuditChanges;
use App\Modules\Workspace\Support\WorkspaceAuditTaxonomy;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;

final class TenantActivityLogger
{
    public function __construct(
        private readonly StructuredAuditLogWriter $structuredAudit,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, array{from?: mixed, to?: mixed}|mixed>  $changes
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
        array $changes = [],
        ?string $reason = null,
        ?string $category = null,
        ?string $severity = null,
    ): TenantActivityLog {
        $actorId = $actor?->getAuthIdentifier();
        $normalizedChanges = WorkspaceAuditChanges::of($changes);

        if ($normalizedChanges !== []) {
            $metadata = array_merge($metadata, ['changes' => $normalizedChanges]);
        }

        $classified = WorkspaceAuditTaxonomy::classify($module, $action);
        $resolvedCategory = WorkspaceAuditTaxonomy::normalizeCategory($category) ?? $classified['category'];
        $resolvedSeverity = WorkspaceAuditTaxonomy::normalizeSeverity($severity) ?? $classified['severity'];
        $resolvedReason = $reason !== null && trim($reason) !== '' ? trim($reason) : null;

        $log = TenantActivityLog::query()->create([
            'id' => (string) Str::uuid(),
            'module' => $module,
            'action' => $action,
            'category' => $resolvedCategory,
            'severity' => $resolvedSeverity,
            'summary' => $summary,
            'reason' => $resolvedReason,
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
            'category' => $resolvedCategory,
            'severity' => $resolvedSeverity,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_label' => $entityLabel,
            'actor_user_id' => $actorId,
            'summary' => $summary,
            'reason' => $resolvedReason,
            'metadata' => $metadata,
            'changes' => $normalizedChanges === [] ? null : $normalizedChanges,
        ]);

        return $log;
    }
}
