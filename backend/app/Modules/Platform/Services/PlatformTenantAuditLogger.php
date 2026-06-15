<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services;

use App\Models\PlatformTenantAuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Platform\Support\StructuredAuditLogWriter;
use Illuminate\Support\Str;

final class PlatformTenantAuditLogger
{
    public function __construct(
        private readonly StructuredAuditLogWriter $auditWriter,
    ) {}
    /**
     * @param  array<string, array{from: mixed, to: mixed}>|null  $changes
     * @param  array<string, mixed>|null  $metadata
     */
    public function log(
        string $eventType,
        ?Tenant $tenant,
        ?User $actor,
        ?array $changes = null,
        ?array $metadata = null,
    ): void {
        if ($changes !== null && $changes === []) {
            return;
        }

        $recordId = (string) Str::uuid();

        PlatformTenantAuditLog::query()->create([
            'id' => $recordId,
            'tenant_id' => $tenant?->id,
            'event_type' => $eventType,
            'actor_user_id' => $actor?->id,
            'actor_email' => $actor?->email,
            'changes' => $changes,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);

        $this->auditWriter->write('platform.tenant', $eventType, [
            'record_id' => $recordId,
            'tenant_id' => $tenant?->id,
            'actor_user_id' => $actor?->id,
            'actor_email' => $actor?->email,
            'changes' => $changes,
            'metadata' => $metadata,
        ]);
    }
}
