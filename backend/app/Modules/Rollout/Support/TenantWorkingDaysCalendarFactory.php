<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Support;

use App\Modules\Rollout\Models\TenantPublicHoliday;
use Illuminate\Support\Facades\Schema;

final class TenantWorkingDaysCalendarFactory
{
    public function make(?string $rolloutRegion = null): WorkingDaysCalendar
    {
        return new WorkingDaysCalendar($this->activeHolidayDates($rolloutRegion));
    }

    /**
     * National holidays always apply. Regional holidays apply only when rollout region matches.
     *
     * @return list<string>
     */
    public function activeHolidayDates(?string $rolloutRegion = null, ?int $year = null): array
    {
        if (! Schema::connection('tenant')->hasTable('tenant_public_holidays')) {
            return [];
        }

        $year = $year ?? (int) now()->format('Y');
        $normalizedRegion = $this->normalizeRegion($rolloutRegion);

        return TenantPublicHoliday::query()
            ->where('calendar_year', $year)
            ->where(function ($query) use ($normalizedRegion): void {
                $query->whereNull('region');

                if ($normalizedRegion !== null) {
                    $query->orWhereRaw('LOWER(region) = ?', [$normalizedRegion]);
                }
            })
            ->orderBy('holiday_date')
            ->pluck('holiday_date')
            ->map(static fn ($date) => $date->toDateString())
            ->values()
            ->all();
    }

    public function holidayScopeLabel(?string $rolloutRegion): string
    {
        $normalizedRegion = $this->normalizeRegion($rolloutRegion);

        return $normalizedRegion !== null
            ? strtoupper($normalizedRegion).' + national'
            : 'National only';
    }

    private function normalizeRegion(?string $region): ?string
    {
        if ($region === null) {
            return null;
        }

        $trimmed = strtolower(trim($region));

        return $trimmed !== '' ? $trimmed : null;
    }
}
