<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Resolves SLA threshold instants using wall-clock minutes.
 * Working-day calendars lived in the removed Rollout module.
 */
final class EApprovalSlaClock
{
    public function __construct(
        private readonly EApprovalSettingsService $settings,
    ) {}

    public function usesWorkingDays(): bool
    {
        return false;
    }

    /**
     * Instant at which an approval created at-or-before is considered past the threshold.
     */
    public function thresholdBefore(CarbonInterface $now, int $minutes): Carbon
    {
        $minutes = max(0, $minutes);

        return Carbon::parse($now)->subMinutes($minutes);
    }
}
