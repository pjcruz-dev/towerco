<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Platform\Models\AppMenuTile;
use App\Modules\Platform\Services\AppMenuIconAssetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CentralAppMenuIconStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        AppMenuTile $appMenuTile,
        AppMenuIconAssetService $icons,
    ): JsonResponse {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:512'],
        ]);

        $tile = $icons->store($appMenuTile, $data['file']);

        return $this->ok($tile);
    }
}
