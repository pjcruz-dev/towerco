<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Platform\Models\AppMenuTile;
use App\Modules\Platform\Services\AppMenuIconAssetService;
use Illuminate\Http\JsonResponse;

final class CentralAppMenuIconDestroyController extends AbstractApiController
{
    public function __invoke(
        AppMenuTile $appMenuTile,
        AppMenuIconAssetService $icons,
    ): JsonResponse {
        $tile = $icons->clear($appMenuTile);

        return $this->ok($tile);
    }
}
