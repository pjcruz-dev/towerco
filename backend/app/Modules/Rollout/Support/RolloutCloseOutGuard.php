<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Support;

use App\Modules\Rollout\Models\RolloutProgram;
use Illuminate\Validation\ValidationException;

/**
 * P8 — Close-out (post–site ready): Project milestones → Site License → Handover to Ops.
 * Outside delivery SLA (counts_toward_sla: false).
 */
final class RolloutCloseOutGuard
{
    public static function hasPhase(RolloutProgram $program, string $phaseKey): bool
    {
        $program->loadMissing('timelinePhases');

        return $program->timelinePhases->contains(
            static fn ($phase): bool => $phase->phase_key === $phaseKey,
        );
    }

    public static function isPhasePassed(RolloutProgram $program, string $phaseKey): bool
    {
        $program->loadMissing('timelinePhases');
        $phase = $program->timelinePhases->firstWhere('phase_key', $phaseKey);

        if ($phase === null) {
            return true;
        }

        return $phase->gate_status === 'passed' || $phase->actual_end_date !== null;
    }

    public static function isSiteLicensePassed(RolloutProgram $program): bool
    {
        if ($program->site_license_executed_date !== null) {
            return true;
        }

        return self::isPhasePassed($program, 'site_license');
    }

    public static function isHandoverPassed(RolloutProgram $program): bool
    {
        return self::isPhasePassed($program, 'handover_operations');
    }

    public static function isCloseOutComplete(RolloutProgram $program): bool
    {
        return RolloutConstructionRfiGuard::isRfiRecorded($program)
            && self::isSiteLicensePassed($program)
            && self::isHandoverPassed($program);
    }

    public static function isReadyForSiteLicense(RolloutProgram $program): bool
    {
        try {
            self::assertReadyForSiteLicense($program);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    public static function isReadyForHandover(RolloutProgram $program): bool
    {
        try {
            self::assertReadyForHandover($program);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    public static function assertReadyForSiteLicense(RolloutProgram $program): void
    {
        RolloutConstructionRfiGuard::assertPassedBeforeCloseOut($program);
    }

    public static function assertReadyForHandover(RolloutProgram $program): void
    {
        self::assertReadyForSiteLicense($program);

        if (! self::hasPhase($program, 'site_license')) {
            return;
        }

        if (self::isSiteLicensePassed($program)) {
            return;
        }

        throw ValidationException::withMessages([
            'gate_status' => [__('Complete Site License Processing before Handover to Operations (P8).')],
        ]);
    }
}
