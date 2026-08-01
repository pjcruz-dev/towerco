<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Support;

use App\Modules\Rollout\Models\RolloutProgram;
use Illuminate\Validation\ValidationException;

/**
 * P7 — Construction + Energization → Record RFI (★ site ready).
 */
final class RolloutConstructionRfiGuard
{
    public static function isRfiRecorded(RolloutProgram $program): bool
    {
        return $program->actual_rfi_date !== null;
    }

    public static function isConstructionPassed(RolloutProgram $program): bool
    {
        $program->loadMissing('timelinePhases');
        $phase = $program->timelinePhases->firstWhere('phase_key', 'construction');

        if ($phase === null) {
            return self::isRfiRecorded($program);
        }

        return $phase->gate_status === 'passed' || $phase->actual_end_date !== null;
    }

    public static function isReadyForConstruction(RolloutProgram $program): bool
    {
        try {
            RolloutBuildReadinessGuard::assertPassedBeforeConstruction($program);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    public static function isReadyForRfi(RolloutProgram $program): bool
    {
        try {
            self::assertReadyForRfi($program);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    public static function assertReadyForConstruction(RolloutProgram $program): void
    {
        RolloutBuildReadinessGuard::assertPassedBeforeConstruction($program);
    }

    /**
     * RFI closes delivery (site ready). Requires Day-1 and P6 build readiness.
     */
    public static function assertReadyForRfi(RolloutProgram $program): void
    {
        if ($program->tssr_approved_date === null) {
            throw ValidationException::withMessages([
                'actual_rfi_date' => [__('Complete P5 first: record Day-1 before recording RFI (site ready).')],
            ]);
        }

        RolloutBuildReadinessGuard::assertPassedBeforeConstruction($program);
    }

    /**
     * Site License / Handover (P8) wait until RFI marks the site ready.
     */
    public static function assertPassedBeforeCloseOut(RolloutProgram $program): void
    {
        self::assertReadyForRfi($program);

        if (self::isRfiRecorded($program)) {
            return;
        }

        throw ValidationException::withMessages([
            'gate_status' => [__('Complete P7 first: record RFI (site ready) before Site License / Handover.')],
        ]);
    }
}
