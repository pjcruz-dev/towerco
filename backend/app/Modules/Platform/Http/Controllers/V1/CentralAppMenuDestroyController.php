<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Platform\Models\AppMenuTile;
use App\Modules\Platform\Services\AppMenuService;
use Illuminate\Http\JsonResponse;

class CentralAppMenuDestroyController extends AbstractApiController
{
    public function __invoke(AppMenuTile $appMenuTile, AppMenuService $service): JsonResponse
    {
        $service->destroy($appMenuTile);

        return $this->ok(['deleted' => true]);
    }
}
