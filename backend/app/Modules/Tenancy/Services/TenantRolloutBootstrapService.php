<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Services;

use App\Models\Tenant;

/**
 * Legacy rollout holiday bootstrap — no-op after Rollout module removal.
 */
final class TenantRolloutBootstrapService
{
    /**
     * @return array{public_holidays_seeded: int, holiday_years: list<int>}
     */
    public function provision(Tenant $tenant): array
    {
        return [
            'public_holidays_seeded' => 0,
            'holiday_years' => [],
        ];
    }
}
