<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Services\OfflineDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OfflineDataDownloadController extends AbstractApiController
{
    public function __invoke(Request $request, OfflineDataService $offline): JsonResponse
    {
        $user = $request->user();
        $canEntities = (bool) ($user?->can('dynamic_entities:view') || $user?->can('role:manage'));
        $canAssets = (bool) ($user?->can('assets:view') || $user?->can('assets:manage'));
        abort_unless($canEntities || $canAssets, 403);

        $data = $request->validate([
            'entities' => ['required', 'array', 'min:1', 'max:100'],
            'entities.*' => ['required', 'string', 'max:120'],
        ]);

        return $this->ok($offline->download($data['entities'], $canEntities, $canAssets, $user));
    }
}
