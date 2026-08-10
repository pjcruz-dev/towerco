<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalAuditLog;
use App\Modules\Workspace\Services\TenantActivityLogger;
use App\Modules\Workspace\Support\WorkspaceAuditChanges;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;

final class EApprovalAuditLogger
{
    public function __construct(
        private readonly TenantActivityLogger $activity,
    ) {}

    /**
     * @param  array<string, array{from?: mixed, to?: mixed}|mixed>  $changes
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        string $action,
        ?string $targetId = null,
        ?string $remarks = null,
        ?Authenticatable $actor = null,
        array $changes = [],
        array $metadata = [],
        ?string $entityType = null,
        ?string $entityLabel = null,
    ): void {
        $normalizedChanges = WorkspaceAuditChanges::of($changes);

        EApprovalAuditLog::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $actor?->getAuthIdentifier(),
            'action' => $action,
            'target_id' => $targetId,
            'remarks' => $remarks,
        ]);

        $resolvedEntityType = $entityType;
        if ($resolvedEntityType === null && $targetId !== null) {
            $resolvedEntityType = 'submission';
        }

        $this->activity->record(
            module: 'e_approval',
            action: $action,
            summary: $remarks,
            entityType: $resolvedEntityType,
            entityId: $targetId,
            entityLabel: $entityLabel,
            actor: $actor,
            metadata: $metadata,
            changes: $normalizedChanges,
            reason: $this->reasonFor($action, $remarks),
        );
    }

    private function reasonFor(string $action, ?string $remarks): ?string
    {
        if ($remarks === null || trim($remarks) === '') {
            return null;
        }

        $sensitive = [
            'request_rejected',
            'submission_cancelled',
            'revision_requested',
            'form_deleted',
        ];

        return in_array($action, $sensitive, true) ? trim($remarks) : null;
    }
}
