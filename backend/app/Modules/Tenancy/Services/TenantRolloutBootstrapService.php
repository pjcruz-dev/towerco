<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Services;

use App\Models\Tenant;
use App\Modules\Rollout\Services\TenantPublicHolidayService;

/**
 * Idempotent rollout-related bootstrap steps for newly provisioned tenants.
 */
final class TenantRolloutBootstrapService
{
    public function __construct(
        private readonly TenantPublicHolidayService $holidays,
    ) {}

    /**
     * @return array{public_holidays_seeded: int, holiday_years: list<int>}
     */
    public function provision(Tenant $tenant): array
    {
        if (! (bool) config('toweros.tenant_provisioning.auto_seed_holidays', true)) {
            return [
                'public_holidays_seeded' => 0,
                'holiday_years' => [],
            ];
        }

        $years = $this->holidayYearsToSeed();
        $seeded = 0;

        $tenant->run(function () use ($years, &$seeded): void {
            foreach ($years as $year) {
                $seeded += $this->holidays->seedPhilippinesYear($year);
            }
        });

        return [
            'public_holidays_seeded' => $seeded,
            'holiday_years' => $years,
        ];
    }

    /**
     * @return list<int>
     */
    private function holidayYearsToSeed(): array
    {
        $current = (int) now()->format('Y');
        $years = [$current];

        if ((bool) config('toweros.tenant_provisioning.seed_next_holiday_year', true)) {
            $years[] = $current + 1;
        }

        return array_values(array_unique($years));
    }
}
