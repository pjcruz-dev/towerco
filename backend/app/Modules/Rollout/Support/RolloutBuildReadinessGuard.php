<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Support;

use App\Modules\Rollout\Models\RolloutProgram;
use Illuminate\Validation\ValidationException;

/**
 * P6 — Build readiness (post–Day-1): Pre-Construction → Permitting → SKOM.
 */
final class RolloutBuildReadinessGuard
{
    public static function isDayOneSet(RolloutProgram $program): bool
    {
        return $program->tssr_approved_date !== null;
    }

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

    public static function isReadyForPreConstruction(RolloutProgram $program): bool
    {
        return self::isDayOneSet($program);
    }

    public static function isReadyForPermitting(RolloutProgram $program): bool
    {
        return self::isReadyForPreConstruction($program)
            && self::isPhasePassed($program, 'pre_construction');
    }

    public static function isReadyForSkom(RolloutProgram $program): bool
    {
        return self::isReadyForPermitting($program)
            && self::isPhasePassed($program, 'permitting');
    }

    public static function isBuildReadinessComplete(RolloutProgram $program): bool
    {
        return self::isReadyForSkom($program)
            && self::isPhasePassed($program, 'skom');
    }

    public static function assertReadyForPreConstruction(RolloutProgram $program): void
    {
        if (self::isDayOneSet($program)) {
            return;
        }

        throw ValidationException::withMessages([
            'gate_status' => [__('Complete P5 first: record Day-1 (TSSR approved) before Pre-Construction (P6).')],
        ]);
    }

    public static function assertReadyForPermitting(RolloutProgram $program): void
    {
        self::assertReadyForPreConstruction($program);

        if (! self::hasPhase($program, 'pre_construction')) {
            return;
        }

        if (self::isPhasePassed($program, 'pre_construction')) {
            return;
        }

        throw ValidationException::withMessages([
            'gate_status' => [__('Complete Pre-Construction gate before Permitting (P6).')],
        ]);
    }

    public static function assertReadyForSkom(RolloutProgram $program): void
    {
        self::assertReadyForPermitting($program);

        if (! self::hasPhase($program, 'permitting')) {
            return;
        }

        if (self::isPhasePassed($program, 'permitting')) {
            return;
        }

        throw ValidationException::withMessages([
            'gate_status' => [__('Complete Permitting gate before SKOM / Mobilization (P6).')],
        ]);
    }

    /**
     * Construction (P7) waits until P6 is complete when those phases exist on the timeline.
     */
    public static function assertPassedBeforeConstruction(RolloutProgram $program): void
    {
        if (self::hasPhase($program, 'skom')) {
            self::assertReadyForSkom($program);

            if (self::isPhasePassed($program, 'skom')) {
                return;
            }

            throw ValidationException::withMessages([
                'gate_status' => [__('Complete P6 first: pass SKOM / Mobilization before Construction.')],
            ]);
        }

        if (self::hasPhase($program, 'permitting')) {
            self::assertReadyForPermitting($program);

            if (self::isPhasePassed($program, 'permitting')) {
                return;
            }

            throw ValidationException::withMessages([
                'gate_status' => [__('Complete Permitting before Construction.')],
            ]);
        }

        if (self::hasPhase($program, 'pre_construction')) {
            self::assertReadyForPreConstruction($program);

            if (self::isPhasePassed($program, 'pre_construction')) {
                return;
            }

            throw ValidationException::withMessages([
                'gate_status' => [__('Complete Pre-Construction before Construction.')],
            ]);
        }

        self::assertReadyForPreConstruction($program);
    }
}
