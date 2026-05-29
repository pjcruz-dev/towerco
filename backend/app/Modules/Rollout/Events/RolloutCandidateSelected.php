<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class RolloutCandidateSelected implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $rolloutId,
        public readonly string $rolloutRef,
        public readonly ?string $tcoSiteId,
        public readonly int $candidateNumber,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.'.$this->tenantId.'.rollouts'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'RolloutCandidateSelected';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'rollout_id' => $this->rolloutId,
            'rollout_ref' => $this->rolloutRef,
            'tco_site_id' => $this->tcoSiteId,
            'candidate_number' => $this->candidateNumber,
        ];
    }
}
