<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Support\TenantWorkingDaysCalendarFactory;
use Carbon\Carbon;
use App\Modules\Tenancy\Support\TenantScopedCache;
use Illuminate\Support\Facades\Schema;

final class RolloutSlaAtRiskService
{
    public const RISK_WORKING_DAYS_THRESHOLD = 10;

    private const CACHE_SECONDS = 60;

    public function __construct(
        private readonly TenantWorkingDaysCalendarFactory $calendarFactory,
    ) {}

    public function count(): int
    {
        return count($this->ids());
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        if (! Schema::connection('tenant')->hasTable('rollout_programs')) {
            return [];
        }

        $tenantId = (string) (tenant('id') ?? 'unknown');

        return TenantScopedCache::remember(
            "rollout:sla_at_risk_ids:{$tenantId}",
            self::CACHE_SECONDS,
            fn (): array => $this->computeIds(),
        );
    }

    /**
     * @return list<string>
     */
    private function computeIds(): array
    {
        $today = Carbon::today();

        return RolloutProgram::query()
            ->select(['id', 'region', 'target_rfi_working_date'])
            ->whereNotNull('target_rfi_working_date')
            ->whereNull('actual_rfi_date')
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereNull('parent_rollout_id')
            ->get()
            ->filter(function (RolloutProgram $program) use ($today): bool {
                $target = $program->target_rfi_working_date;
                if ($target === null) {
                    return false;
                }

                $remaining = $this->calendarFactory
                    ->make($program->region)
                    ->workingDaysBetween($today, Carbon::parse($target));

                return $remaining <= self::RISK_WORKING_DAYS_THRESHOLD;
            })
            ->map(static fn (RolloutProgram $program): string => (string) $program->id)
            ->values()
            ->all();
    }
}
