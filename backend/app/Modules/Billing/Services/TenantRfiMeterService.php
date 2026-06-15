<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Models\Tenant;
use App\Models\TenantBillingRfiCompletion;
use App\Modules\Rollout\Models\RolloutProgram;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

final class TenantRfiMeterService
{
    public function __construct(
        private readonly TenantPlanEntitlementsService $entitlements,
    ) {}

    public function isMeteringEnforced(Tenant $tenant): bool
    {
        return $tenant->billing_meter_starts_at !== null;
    }

    public function billableCount(Tenant $tenant): int
    {
        if (! $this->isMeteringEnforced($tenant)) {
            return 0;
        }

        return TenantBillingRfiCompletion::query()
            ->where('tenant_id', $tenant->id)
            ->where('rfi_at', '>=', $tenant->billing_meter_starts_at)
            ->count();
    }

    public function rfiLimit(Tenant $tenant): int
    {
        return $this->entitlements->effectiveRfiLimit($tenant);
    }

    public function rfiAvailable(Tenant $tenant): int
    {
        return max(0, $this->rfiLimit($tenant) - $this->billableCount($tenant));
    }

    /**
     * @return array{used: int, limit: int, available: int, metering_active: bool}
     */
    public function snapshot(Tenant $tenant): array
    {
        $limit = $this->rfiLimit($tenant);
        $used = $this->billableCount($tenant);

        return [
            'used' => $used,
            'limit' => $limit,
            'available' => max(0, $limit - $used),
            'metering_active' => $this->isMeteringEnforced($tenant),
        ];
    }

    public function isNewBillableRfi(Tenant $tenant, RolloutProgram $program, Carbon $actualRfiDate): bool
    {
        if ($program->actual_rfi_date !== null) {
            return false;
        }

        if (! $this->isMeteringEnforced($tenant)) {
            return false;
        }

        return ! $actualRfiDate->lt($tenant->billing_meter_starts_at);
    }

    public function assertCanRecordRfi(Tenant $tenant, RolloutProgram $program, Carbon $actualRfiDate): void
    {
        if (! $this->isNewBillableRfi($tenant, $program, $actualRfiDate)) {
            return;
        }

        $limit = $this->rfiLimit($tenant);
        if ($limit <= 0) {
            throw ValidationException::withMessages([
                'actual_rfi_date' => [
                    __(
                        'RFI billing is active but this tenant has no included RFI units. Ask TowerOS to adjust the plan or grandfather units.',
                    ),
                ],
            ]);
        }

        $used = $this->billableCount($tenant);
        if ($used >= $limit) {
            throw ValidationException::withMessages([
                'actual_rfi_date' => [
                    __(
                        'RFI unit limit reached (:used / :limit). Tower inventory can still be updated — contact TowerOS to add capacity or grandfather units.',
                        ['used' => $used, 'limit' => $limit],
                    ),
                ],
            ]);
        }
    }

    public function recordCompletion(Tenant $tenant, RolloutProgram $program, Carbon $actualRfiDate): void
    {
        if (! $this->isMeteringEnforced($tenant)) {
            return;
        }

        if ($actualRfiDate->lt($tenant->billing_meter_starts_at)) {
            return;
        }

        TenantBillingRfiCompletion::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'rollout_id' => $program->id,
            ],
            [
                'site_id' => $program->site_id,
                'rfi_at' => $actualRfiDate,
            ],
        );
    }
}
