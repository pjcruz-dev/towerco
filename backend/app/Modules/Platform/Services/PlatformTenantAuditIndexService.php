<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services;

use App\Models\PlatformTenantAuditLog;
use App\Models\Tenant;
use App\Modules\Platform\Support\PlatformTenantAuditEventType;
use Illuminate\Database\Eloquent\Builder;

final class PlatformTenantAuditIndexService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function forTenant(Tenant $tenant, int $limit): array
    {
        return $this->mapRows(
            $this->baseQuery()
                ->where('tenant_id', $tenant->id)
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get(),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recent(int $limit): array
    {
        return $this->mapRows(
            $this->baseQuery()
                ->with(['tenant.domains:id,domain,tenant_id'])
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get(),
        );
    }

    /**
     * @return Builder<PlatformTenantAuditLog>
     */
    private function baseQuery(): Builder
    {
        return PlatformTenantAuditLog::query();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PlatformTenantAuditLog>  $rows
     * @return list<array<string, mixed>>
     */
    private function mapRows($rows): array
    {
        return $rows
            ->map(function (PlatformTenantAuditLog $log): array {
                $tenant = $log->relationLoaded('tenant') ? $log->tenant : null;
                $primaryDomain = null;
                if ($tenant !== null && $tenant->relationLoaded('domains')) {
                    $primaryDomain = $tenant->domains->first()?->domain;
                }

                return [
                    'id' => $log->id,
                    'tenant_id' => $log->tenant_id,
                    'tenant_slug' => $tenant?->slug,
                    'tenant_domain' => $primaryDomain,
                    'event_type' => $log->event_type,
                    'event_label' => $this->eventLabel($log->event_type),
                    'actor_email' => $log->actor_email,
                    'changes' => $log->changes,
                    'metadata' => $log->metadata,
                    'created_at' => $log->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    private function eventLabel(string $eventType): string
    {
        return match ($eventType) {
            PlatformTenantAuditEventType::TENANT_PROVISIONED => 'Tenant provisioned',
            PlatformTenantAuditEventType::TENANT_ENVIRONMENT_PROVISIONED => 'Environment tenant provisioned',
            PlatformTenantAuditEventType::TENANT_DELETED => 'Tenant deleted',
            PlatformTenantAuditEventType::TENANT_MFA_UPDATED => 'MFA policy updated',
            PlatformTenantAuditEventType::TENANT_BRANDING_UPDATED => 'Branding updated',
            PlatformTenantAuditEventType::TENANT_MODULES_UPDATED => 'Workspace modules updated',
            PlatformTenantAuditEventType::TENANT_BILLING_UPDATED => 'Billing updated',
            PlatformTenantAuditEventType::TENANT_PLAYBOOK_ASSIGNED => 'Rollout playbook assigned',
            PlatformTenantAuditEventType::TENANT_IMPERSONATION_STARTED => 'Platform impersonation started',
            PlatformTenantAuditEventType::TENANT_ACCESS_UPDATED => 'Operator access updated',
            default => str_replace(['.', '_'], ' ', $eventType),
        };
    }
}
