<?php

declare(strict_types=1);

namespace App\Modules\AssetOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetOne\Models\Asset;
use App\Modules\AssetOne\Services\AssetShowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetShowController extends AbstractApiController
{
    public function __invoke(Request $request, Asset $asset, AssetShowService $service): JsonResponse
    {
        abort_unless($request->user()?->can('asset_one:view'), 403);

        return $this->ok($service->asDetail($asset));
    }
}
