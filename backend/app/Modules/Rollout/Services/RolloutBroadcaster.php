<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Rollout\Events\RolloutCandidateSelected;
use App\Modules\Rollout\Events\RolloutUpdated;
use App\Modules\Rollout\Models\RolloutProgram;

final class RolloutBroadcaster
{
    public function rolloutUpdated(RolloutProgram $program, string $reason): void
    {
        if (! $this->shouldBroadcast()) {
            return;
        }

        $tenantId = $this->tenantId();
        if ($tenantId === null) {
            return;
        }

        event(new RolloutUpdated(
            tenantId: $tenantId,
            rolloutId: (string) $program->id,
            rolloutRef: (string) $program->rollout_ref,
            reason: $reason,
        ));
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function fromAuditEvent(RolloutProgram $program, string $event, array $properties = []): void
    {
        if ($event === 'rollout.candidate_selected') {
            $this->candidateSelected(
                $program,
                isset($properties['candidate_number']) ? (int) $properties['candidate_number'] : 0,
            );

            return;
        }

        $this->rolloutUpdated($program, $event);
    }

    public function candidateSelected(RolloutProgram $program, int $candidateNumber): void
    {
        if (! $this->shouldBroadcast()) {
            return;
        }

        $tenantId = $this->tenantId();
        if ($tenantId === null) {
            return;
        }

        event(new RolloutCandidateSelected(
            tenantId: $tenantId,
            rolloutId: (string) $program->id,
            rolloutRef: (string) $program->rollout_ref,
            tcoSiteId: $program->tco_site_id,
            candidateNumber: $candidateNumber,
        ));
    }

    private function shouldBroadcast(): bool
    {
        $driver = (string) config('broadcasting.default', 'null');

        return $driver !== 'null' && $driver !== '';
    }

    private function tenantId(): ?string
    {
        if (! function_exists('tenancy') || ! tenancy()->initialized) {
            return null;
        }

        $tenantId = tenant('id');

        return $tenantId !== null ? (string) $tenantId : null;
    }
}
