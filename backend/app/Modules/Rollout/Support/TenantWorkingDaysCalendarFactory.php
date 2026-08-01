<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Support;

use App\Modules\Rollout\Models\TenantPublicHoliday;
use Illuminate\Support\Facades\Schema;

final class TenantWorkingDaysCalendarFactory
{
    public function make(?string $opsScope = null): WorkingDaysCalendar
    {
        return new WorkingDaysCalendar($this->activeHolidayDates($opsScope));
    }

    /**
     * National holidays always apply. Territory-scoped holidays apply when ops scope matches
     * (rollout territory preferred; region used as legacy fallback by callers).
     *
     * @return list<string>
     */
    public function activeHolidayDates(?string $opsScope = null, ?int $year = null): array
    {
        if (! Schema::connection('tenant')->hasTable('tenant_public_holidays')) {
            return [];
        }

        $year = $year ?? (int) now()->format('Y');
        $matchKey = RolloutOpsGeography::matchKey($opsScope);

        return TenantPublicHoliday::query()
            ->where('calendar_year', $year)
            ->where(function ($query) use ($matchKey): void {
                $query->whereNull('region');

                if ($matchKey !== null) {
                    $query->orWhereRaw('LOWER(region) = ?', [$matchKey]);
                }
            })
            ->orderBy('holiday_date')
            ->pluck('holiday_date')
            ->map(static fn ($date) => $date->toDateString())
            ->values()
            ->all();
    }

    public function holidayScopeLabel(?string $opsScope): string
    {
        $normalized = RolloutOpsGeography::normalize($opsScope);

        return $normalized !== null
            ? $normalized.' + national'
            : 'National only';
    }
}
