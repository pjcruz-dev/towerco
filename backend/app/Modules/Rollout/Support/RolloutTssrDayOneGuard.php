<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Support;

use App\Modules\Rollout\Models\RolloutProgram;
use Illuminate\Validation\ValidationException;

/**
 * P5 — TSSR create/review → TSSR MNO approval → Record Day-1.
 */
final class RolloutTssrDayOneGuard
{
    public static function hasTssrCreationPhase(RolloutProgram $program): bool
    {
        $program->loadMissing('timelinePhases');

        return $program->timelinePhases->contains(
            static fn ($phase): bool => $phase->phase_key === 'tssr_creation',
        );
    }

    public static function isTssrCreationPassed(RolloutProgram $program): bool
    {
        $program->loadMissing('timelinePhases');
        $phase = $program->timelinePhases->firstWhere('phase_key', 'tssr_creation');

        if ($phase === null) {
            return true;
        }

        return $phase->gate_status === 'passed' || $phase->actual_end_date !== null;
    }

    public static function isDayOneSet(RolloutProgram $program): bool
    {
        return $program->tssr_approved_date !== null;
    }

    public static function isReadyForTssrCreation(RolloutProgram $program): bool
    {
        try {
            RolloutPreAssessmentGuard::assertReadyForTssr($program);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    public static function assertReadyForTssrCreation(RolloutProgram $program): void
    {
        RolloutPreAssessmentGuard::assertReadyForTssr($program);
    }

    public static function assertReadyForDayOne(RolloutProgram $program): void
    {
        RolloutPreAssessmentGuard::assertReadyForTssr($program);

        if (self::isTssrCreationPassed($program)) {
            return;
        }

        throw ValidationException::withMessages([
            'tssr_approved_date' => [__('Complete P5 first: pass TSSR create/review (Engineering) before recording Day-1.')],
        ]);
    }

    public static function assertReadyForTssrMnoGate(RolloutProgram $program): void
    {
        self::assertReadyForTssrCreation($program);

        if (self::isTssrCreationPassed($program)) {
            return;
        }

        throw ValidationException::withMessages([
            'gate_status' => [__('Pass TSSR create/review before TSSR MNO approval / Day-1.')],
        ]);
    }
}
