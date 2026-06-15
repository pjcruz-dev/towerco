<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services;

use App\Models\Tenant;
use App\Models\TenantBillingAuditLog;
use App\Models\User;
use App\Modules\Platform\Support\PlatformTenantAuditEventType;
use App\Modules\Platform\Support\StructuredAuditLogWriter;
use Illuminate\Support\Str;

final class TenantBillingAuditLogger
{
    public function __construct(
        private readonly PlatformTenantAuditLogger $platformAudit,
        private readonly StructuredAuditLogWriter $auditWriter,
    ) {}

    /**
     * @param  array<string, array{from: mixed, to: mixed}>  $changes
     */
    public function log(Tenant $tenant, ?User $actor, array $changes): void
    {
        if ($changes === []) {
            return;
        }

        $recordId = (string) Str::uuid();

        TenantBillingAuditLog::query()->create([
            'id' => $recordId,
            'tenant_id' => $tenant->id,
            'actor_user_id' => $actor?->id,
            'actor_email' => $actor?->email,
            'changes' => $changes,
            'created_at' => now(),
        ]);

        $this->auditWriter->write('platform.billing', PlatformTenantAuditEventType::TENANT_BILLING_UPDATED, [
            'record_id' => $recordId,
            'tenant_id' => $tenant->id,
            'actor_user_id' => $actor?->id,
            'actor_email' => $actor?->email,
            'changes' => $changes,
        ]);

        $this->platformAudit->log(
            PlatformTenantAuditEventType::TENANT_BILLING_UPDATED,
            $tenant,
            $actor,
            $changes,
        );
    }
}
