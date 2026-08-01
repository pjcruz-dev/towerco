<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Support\RolloutOpsGeography;
use App\Modules\Rollout\Support\TenantWorkingDaysCalendarFactory;
use App\Modules\Tenancy\Support\TenantScopedCache;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
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
            fn (): array => $this->queryAtRiskIds(),
        );
    }

    /**
     * @return list<string>
     */
    private function queryAtRiskIds(): array
    {
        // Make sure the denormalized remaining-days are fresh for today (amortized to once
        // per day; the scheduled command normally does this ahead of the first request).
        $this->ensureFreshFor(Carbon::today());

        return $this->baseAtRiskQuery()
            ->whereNotNull('sla_remaining_working_days')
            ->where('sla_remaining_working_days', '<=', self::RISK_WORKING_DAYS_THRESHOLD)
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->values()
            ->all();
    }

    /**
     * Recompute and persist sla_remaining_working_days for every eligible rollout in the
     * current tenant. Returns the number of rows refreshed. Safe to call from a schedule.
     */
    public function recomputeAll(?Carbon $today = null): int
    {
        if (! Schema::connection('tenant')->hasTable('rollout_programs')) {
            return 0;
        }

        $today = $today ?? Carbon::today();
        $todayString = $today->toDateString();

        $programs = $this->baseAtRiskQuery()
            ->select(['id', 'region', 'territory', 'area', 'target_rfi_working_date'])
            ->get();

        $updated = 0;
        foreach ($programs as $program) {
            $target = $program->target_rfi_working_date;
            if ($target === null) {
                continue;
            }

            $remaining = $this->calendarFactory
                ->make(RolloutOpsGeography::forProgram($program))
                ->workingDaysBetween($today, Carbon::parse($target));

            // Update via the base query builder so recompute never touches updated_at
            // (keeps "recent rollouts" ordering and update-based caches stable).
            DB::connection('tenant')
                ->table('rollout_programs')
                ->where('id', $program->id)
                ->update([
                    'sla_remaining_working_days' => $remaining,
                    'sla_risk_computed_on' => $todayString,
                ]);
            $updated++;
        }

        return $updated;
    }

    private function ensureFreshFor(Carbon $today): void
    {
        $needsRecompute = $this->baseAtRiskQuery()
            ->where(static function ($query) use ($today): void {
                $query->whereNull('sla_risk_computed_on')
                    ->orWhereDate('sla_risk_computed_on', '<', $today->toDateString());
            })
            ->exists();

        if ($needsRecompute) {
            $this->recomputeAll($today);
        }
    }

    /**
     * Eligible rollouts for SLA-at-risk evaluation (active parents with a target and no actual RFI).
     *
     * @return Builder<RolloutProgram>
     */
    private function baseAtRiskQuery(): Builder
    {
        return RolloutProgram::query()
            ->whereNotNull('target_rfi_working_date')
            ->whereNull('actual_rfi_date')
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereNull('parent_rollout_id');
    }
}
