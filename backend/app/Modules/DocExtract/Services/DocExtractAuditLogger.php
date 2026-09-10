<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Workspace\Services\TenantActivityLogger;
use App\Modules\Workspace\Support\WorkspaceAuditChanges;
use Illuminate\Contracts\Auth\Authenticatable;

final class DocExtractAuditLogger
{
    public function __construct(
        private readonly TenantActivityLogger $activity,
    ) {}

    /**
     * @param  array<string, array{from?: mixed, to?: mixed}|mixed>  $changes
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        string $action,
        string $summary,
        string $entityType,
        string $entityId,
        ?string $entityLabel = null,
        Authenticatable|TenantUser|null $actor = null,
        array $changes = [],
        array $metadata = [],
    ): void {
        $this->activity->record(
            module: 'doc_extract',
            action: $action,
            summary: $summary,
            entityType: $entityType,
            entityId: $entityId,
            entityLabel: $entityLabel,
            actor: $actor,
            metadata: $metadata,
            changes: WorkspaceAuditChanges::of($changes),
        );
    }
}
