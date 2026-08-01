<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Support;

use App\Modules\Rollout\Models\RolloutProgram;
use Illuminate\Validation\ValidationException;

/**
 * P3 — Pre-assessment Approval (MNO): selected candidate proceeds toward TSSR.
 */
final class RolloutPreAssessmentGuard
{
    public static function isSiteHuntingPassed(RolloutProgram $program): bool
    {
        $program->loadMissing('timelinePhases');
        $phase = $program->timelinePhases->firstWhere('phase_key', 'site_hunting');

        if ($phase === null) {
            // Legacy playbooks without an explicit hunting gate: selection + ≥3 is enough.
            return RolloutSaqSelectGuard::isSiteHuntingGateReady($program);
        }

        return $phase->gate_status === 'passed' || $phase->actual_end_date !== null;
    }

    public static function hasPreAssessmentPhase(RolloutProgram $program): bool
    {
        $program->loadMissing('timelinePhases');

        return $program->timelinePhases->contains(
            static fn ($phase): bool => $phase->phase_key === 'pre_assessment',
        );
    }

    public static function isPreAssessmentPassed(RolloutProgram $program): bool
    {
        $program->loadMissing('timelinePhases');
        $phase = $program->timelinePhases->firstWhere('phase_key', 'pre_assessment');

        if ($phase === null) {
            return true;
        }

        return $phase->gate_status === 'passed' || $phase->actual_end_date !== null;
    }

    public static function isReadyForPreAssessment(RolloutProgram $program): bool
    {
        return RolloutEndorsementGuard::isEstablished($program)
            && RolloutSaqSelectGuard::hasSelectedCandidate($program)
            && self::isSiteHuntingPassed($program);
    }

    public static function assertReadyForPreAssessment(RolloutProgram $program): void
    {
        RolloutEndorsementGuard::assertEstablished($program);
        RolloutSaqSelectGuard::assertSiteHuntingGateReady($program);

        if (self::isSiteHuntingPassed($program)) {
            return;
        }

        throw ValidationException::withMessages([
            'gate_status' => [__('Complete P2 first: pass the Site Hunting gate before MNO Pre-assessment (P3).')],
        ]);
    }

    /**
     * TSSR create/review (and later MNO TSSR) should wait for P3 (and P4 when present).
     */
    public static function assertReadyForTssr(RolloutProgram $program): void
    {
        if (self::hasPreAssessmentPhase($program)) {
            self::assertReadyForPreAssessment($program);

            if (! self::isPreAssessmentPassed($program)) {
                throw ValidationException::withMessages([
                    'gate_status' => [__('Complete P3 first: pass MNO Pre-assessment before TSSR create/review.')],
                ]);
            }
        }

        RolloutMocColGuard::assertPassedBeforeTssr($program);
    }
}
