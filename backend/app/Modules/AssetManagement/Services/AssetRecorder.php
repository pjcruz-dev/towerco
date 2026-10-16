<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Services;

use App\Modules\AssetManagement\Models\Asset;
use App\Modules\AssetManagement\Models\AssetEvent;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Workspace\Services\TenantActivityLogger;
use Illuminate\Support\Str;

final class AssetRecorder
{
    public function __construct(
        private readonly TenantActivityLogger $activity,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function event(
        Asset $asset,
        string $type,
        ?string $from,
        ?string $to,
        ?TenantUser $actor,
        array $payload = [],
    ): void {
        AssetEvent::query()->create([
            'id' => (string) Str::uuid(),
            'asset_id' => $asset->id,
            'event_type' => $type,
            'from_place' => $from,
            'to_place' => $to,
            'payload_json' => $payload === [] ? null : $payload,
            'actor_user_id' => $actor?->id,
            'occurred_at' => now(),
        ]);

        $this->activity->record(
            module: 'asset_management',
            action: 'asset.'.strtolower($type),
            summary: $asset->asset_tag.' · '.$type,
            entityType: 'asset',
            entityId: (string) $asset->id,
            entityLabel: (string) $asset->asset_tag,
            actor: $actor,
            metadata: $payload,
            reason: isset($payload['reason']) ? (string) $payload['reason'] : null,
        );
    }
}
