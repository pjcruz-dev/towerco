<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetManagement\Services\AssetRegistryService;
use App\Modules\AssetManagement\Services\AssetWorkflowService;
use App\Modules\AssetManagement\Support\AssetActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AssetHostnameStoreController extends AbstractApiController
{
    public function __invoke(Request $request, string $asset, AssetRegistryService $registry, AssetWorkflowService $workflow): JsonResponse
    {
        abort_unless($request->user()?->can('assets:manage'), 403);
        $data = $request->validate([
            'hostname' => ['required', 'string', 'max:64'],
            'reason' => ['required', 'string', 'max:180'],
            'os_confirmed' => ['required', 'boolean'],
        ]);
        $history = $workflow->recordHostname($registry->findByTagOrId($asset), $data['hostname'], $data['reason'], AssetActor::from($request), (bool) $data['os_confirmed']);

        return $this->ok($history->toArray());
    }
}
