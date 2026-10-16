<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetManagement\Services\AssetQueryService;
use App\Modules\AssetManagement\Services\AssetRegistryService;
use App\Modules\AssetManagement\Services\AssetWorkflowService;
use App\Modules\AssetManagement\Support\AssetActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AssetReturnController extends AbstractApiController
{
    public function __invoke(Request $request, string $asset, AssetRegistryService $registry, AssetWorkflowService $workflow, AssetQueryService $query): JsonResponse
    {
        abort_unless($request->user()?->can('assets:transfer') || $request->user()?->can('assets:offboard'), 403);
        $data = $request->validate([
            'status' => ['required', 'string', 'max:32'],
            'condition' => ['required', 'string', 'max:32'],
            'expected_tag' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string'],
        ]);
        $record = $workflow->returnAsset($registry->findByTagOrId($asset), AssetActor::from($request), $data['status'], $data, $data['expected_tag'] ?? null);

        return $this->ok($query->present($record));
    }
}
