<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Platform\Services\RolloutPlaybookCatalogService;
use App\Modules\Rollout\Data\RolloutPlaybookDefinitionRegistry;
use Illuminate\Http\JsonResponse;

class CentralRolloutPlaybookIndexController extends AbstractApiController
{
    public function __invoke(RolloutPlaybookCatalogService $catalog): JsonResponse
    {
        $versions = $catalog->listPublished();

        return $this->ok([
            'registry_versions' => RolloutPlaybookDefinitionRegistry::supportedVersions(),
            'versions' => collect($versions)->map(static fn ($v) => [
                'id' => $v->id,
                'version' => $v->version,
                'name' => $v->name,
                'sla_working_days_only' => $v->sla_working_days_only,
                'published_at' => $v->published_at?->toIso8601String(),
            ])->values()->all(),
        ]);
    }
}
