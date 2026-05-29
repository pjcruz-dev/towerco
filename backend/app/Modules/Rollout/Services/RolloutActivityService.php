<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Rollout\Models\RolloutProgram;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

final class RolloutActivityService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listForRollout(RolloutProgram $program, int $limit = 40): array
    {
        if (! $this->canQuery()) {
            return [];
        }

        return Activity::query()
            ->where('log_name', 'rollout')
            ->where('subject_type', RolloutProgram::class)
            ->where('subject_id', $program->id)
            ->orderByDesc('created_at')
            ->limit(max(1, min(100, $limit)))
            ->get()
            ->map(fn (Activity $activity): array => $this->present($activity))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Activity $activity): array
    {
        $properties = $activity->properties?->toArray() ?? [];

        return [
            'id' => $activity->id,
            'event' => $activity->event,
            'description' => $activity->description,
            'properties' => $properties,
            'created_at' => $activity->created_at?->toIso8601String(),
            'causer' => $activity->causer !== null
                ? [
                    'id' => (string) $activity->causer->getKey(),
                    'name' => method_exists($activity->causer, 'getAttribute')
                        ? (string) ($activity->causer->name ?? $activity->causer->email ?? 'User')
                        : 'User',
                ]
                : null,
        ];
    }

    private function canQuery(): bool
    {
        $connection = (new RolloutProgram())->getConnectionName();

        return Schema::connection($connection)->hasTable('activity_log');
    }
}
