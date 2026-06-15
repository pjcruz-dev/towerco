<?php

declare(strict_types=1);

namespace App\Modules\Sites\Services;

use App\Modules\Sites\Models\Site;

final class SiteShowService
{
    /**
     * @return array<string, mixed>
     */
    public function asDetail(Site $site): array
    {
        $site->loadCount(['towers', 'projects']);

        return [
            'id' => $site->id,
            'site_code' => $site->site_code,
            'name' => $site->name,
            'latitude' => $site->latitude,
            'longitude' => $site->longitude,
            'type' => $site->type,
            'status' => $site->status,
            'towers_count' => (int) $site->towers_count,
            'projects_count' => (int) $site->projects_count,
            'created_at' => $site->created_at?->toIso8601String(),
            'updated_at' => $site->updated_at?->toIso8601String(),
        ];
    }
}
