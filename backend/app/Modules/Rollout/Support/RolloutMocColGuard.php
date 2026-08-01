<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Support;

use App\Modules\Rollout\Models\RolloutProgram;
use Illuminate\Validation\ValidationException;

/**
 * P4 — MOC + COL Securing (pre–Day-1): eLAS IRR path before TSSR.
 */
final class RolloutMocColGuard
{
    public static function hasMocColPhase(RolloutProgram $program): bool
    {
        $program->loadMissing('timelinePhases');

        return $program->timelinePhases->contains(
            static fn ($phase): bool => $phase->phase_key === 'moc_col',
        );
    }

    public static function isMocColPassed(RolloutProgram $program): bool
    {
        $program->loadMissing('timelinePhases');
        $phase = $program->timelinePhases->firstWhere('phase_key', 'moc_col');

        if ($phase === null) {
            return true;
        }

        return $phase->gate_status === 'passed' || $phase->actual_end_date !== null;
    }

    public static function isReadyForMocCol(RolloutProgram $program): bool
    {
        return RolloutPreAssessmentGuard::isReadyForPreAssessment($program)
            && RolloutPreAssessmentGuard::isPreAssessmentPassed($program);
    }

    public static function assertReadyForMocCol(RolloutProgram $program): void
    {
        RolloutPreAssessmentGuard::assertReadyForPreAssessment($program);

        if (! RolloutPreAssessmentGuard::hasPreAssessmentPhase($program)) {
            return;
        }

        if (RolloutPreAssessmentGuard::isPreAssessmentPassed($program)) {
            return;
        }

        throw ValidationException::withMessages([
            'gate_status' => [__('Complete P3 first: pass MNO Pre-assessment before MOC/COL (P4).')],
        ]);
    }

    /**
     * When moc_col exists on the timeline (v3), TSSR waits for P4 as well as P3.
     */
    public static function assertPassedBeforeTssr(RolloutProgram $program): void
    {
        if (! self::hasMocColPhase($program)) {
            return;
        }

        self::assertReadyForMocCol($program);

        if (self::isMocColPassed($program)) {
            return;
        }

        throw ValidationException::withMessages([
            'gate_status' => [__('Complete P4 first: pass MOC/COL (eLAS IRR) before TSSR create/review.')],
        ]);
    }
}
