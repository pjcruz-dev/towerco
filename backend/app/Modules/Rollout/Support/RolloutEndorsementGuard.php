<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Support;

use App\Modules\Rollout\Models\RolloutProgram;

/**
 * P1 — Endorsement & Planning / Site Tracker enrolment readiness.
 */
final class RolloutEndorsementGuard
{
    public static function isEstablished(RolloutProgram $program): bool
    {
        if ($program->endorsement_date !== null) {
            return true;
        }

        $program->loadMissing('timelinePhases');

        $phase = $program->timelinePhases->firstWhere('phase_key', 'endorsement');
        if ($phase === null) {
            return false;
        }

        return $phase->gate_status === 'passed'
            || $phase->actual_end_date !== null;
    }

    public static function assertEstablished(RolloutProgram $program): void
    {
        if (self::isEstablished($program)) {
            return;
        }

        throw \Illuminate\Validation\ValidationException::withMessages([
            'endorsement' => [__('Complete P1 Endorsement first: set the MNO endorsement date (Site Tracker enrolment) before SAQ work.')],
        ]);
    }
}
