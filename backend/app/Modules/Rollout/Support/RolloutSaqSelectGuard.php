<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Support;

use App\Modules\Rollout\Models\RolloutProgram;
use Illuminate\Validation\ValidationException;

/**
 * P2 — SAQ Site Hunting: ≥3 active candidates → select one → Site Hunting gate.
 */
final class RolloutSaqSelectGuard
{
    public const MIN_ACTIVE_CANDIDATES = 3;

    public static function activeCandidateCount(RolloutProgram $program): int
    {
        $program->loadMissing('candidates');

        return $program->candidates
            ->filter(static fn ($c): bool => $c->status !== 'rejected')
            ->count();
    }

    public static function hasSelectedCandidate(RolloutProgram $program): bool
    {
        $program->loadMissing('candidates');

        return $program->candidates->contains(
            static fn ($c): bool => $c->status === 'selected',
        );
    }

    public static function isReadyToSelect(RolloutProgram $program): bool
    {
        return RolloutEndorsementGuard::isEstablished($program)
            && self::activeCandidateCount($program) >= self::MIN_ACTIVE_CANDIDATES;
    }

    public static function isSiteHuntingGateReady(RolloutProgram $program): bool
    {
        return self::isReadyToSelect($program) && self::hasSelectedCandidate($program);
    }

    public static function assertReadyToSelect(RolloutProgram $program): void
    {
        RolloutEndorsementGuard::assertEstablished($program);

        $count = self::activeCandidateCount($program);
        if ($count >= self::MIN_ACTIVE_CANDIDATES) {
            return;
        }

        $need = self::MIN_ACTIVE_CANDIDATES - $count;

        throw ValidationException::withMessages([
            'candidates' => [__(
                'Complete P2 SAQ first: add :need more active candidate(s) (need :min non-rejected) before selecting.',
                ['need' => $need, 'min' => self::MIN_ACTIVE_CANDIDATES],
            )],
        ]);
    }

    public static function assertSiteHuntingGateReady(RolloutProgram $program): void
    {
        self::assertReadyToSelect($program);

        if (self::hasSelectedCandidate($program)) {
            return;
        }

        throw ValidationException::withMessages([
            'gate_status' => [__('Complete P2 SAQ: select one of the ≥3 candidates before passing the Site Hunting gate.')],
        ]);
    }
}
