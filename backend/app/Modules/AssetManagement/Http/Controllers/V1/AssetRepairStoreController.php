<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetManagement\Services\AssetRegistryService;
use App\Modules\AssetManagement\Services\AssetWorkflowService;
use App\Modules\AssetManagement\Support\AssetActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AssetRepairStoreController extends AbstractApiController
{
    public function __invoke(Request $request, string $asset, AssetRegistryService $registry, AssetWorkflowService $workflow): JsonResponse
    {
        abort_unless($request->user()?->can('assets:repair'), 403);
        $data = $request->validate([
            'usable' => ['nullable', 'boolean'],
            'replacement_required' => ['nullable', 'boolean'],
            'problem_type' => ['nullable', 'string', 'max:32'],
            'severity' => ['nullable', 'string', 'max:32'],
            'problem' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'diagnosis' => ['nullable', 'string'],
        ]);

        return $this->created($workflow->openRepair($registry->findByTagOrId($asset), AssetActor::from($request), $data)->toArray());
    }
}
