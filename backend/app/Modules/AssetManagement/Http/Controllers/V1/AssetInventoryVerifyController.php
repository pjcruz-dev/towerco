<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetManagement\Services\AssetInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AssetInventoryVerifyController extends AbstractApiController
{
    public function __invoke(Request $request, AssetInventoryService $inventory): JsonResponse
    {
        abort_unless($request->user()?->can('assets:manage'), 403);
        $data = $request->validate([
            'asset_tag' => ['required', 'string', 'max:32'],
            'detected_serial' => ['required', 'string', 'max:128'],
        ]);

        return $this->ok($inventory->verify($data));
    }
}
