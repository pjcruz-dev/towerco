<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Services\RolloutGeographyLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolloutGeographyLookupIndexController extends AbstractApiController
{
    public function __invoke(Request $request, RolloutGeographyLookupService $service): JsonResponse
    {
        abort_unless($request->user()?->can('project_one:view'), 403);

        $data = $request->validate([
            'kind' => ['sometimes', 'nullable', 'string', 'in:region,territory'],
            'active_only' => ['sometimes', 'boolean'],
        ]);

        $kind = $data['kind'] ?? null;
        $activeOnly = (bool) ($data['active_only'] ?? false);

        return $this->ok([
            'items' => $service->list($kind, $activeOnly),
        ]);
    }
}
