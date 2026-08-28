<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\Rollout\Support\TenantWorkingDaysCalendarFactory;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Resolves SLA threshold instants — wall-clock or working-day minutes
 * (Mon–Fri, tenant public holidays) for production-safe reminder/escalation aging.
 */
final class EApprovalSlaClock
{
    public function __construct(
        private readonly EApprovalSettingsService $settings,
        private readonly TenantWorkingDaysCalendarFactory $calendars,
    ) {}

    public function usesWorkingDays(): bool
    {
        return $this->settings->getBool(EApprovalSettingsService::SLA_USE_WORKING_DAYS, true);
    }

    /**
     * Instant at which an approval created at-or-before is considered past the threshold.
     */
    public function thresholdBefore(CarbonInterface $now, int $minutes): Carbon
    {
        $minutes = max(0, $minutes);

        if (! $this->usesWorkingDays()) {
            return Carbon::parse($now)->subMinutes($minutes);
        }

        return $this->calendars->make()->subWorkingMinutes($now, $minutes);
    }
}
