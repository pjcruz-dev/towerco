<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Platform\Models\AppMenuGroup;
use App\Modules\Platform\Services\AppMenuService;
use Illuminate\Http\JsonResponse;

final class CentralAppMenuGroupDestroyController extends AbstractApiController
{
    public function __invoke(AppMenuGroup $appMenuGroup, AppMenuService $service): JsonResponse
    {
        $service->destroyGroup($appMenuGroup);

        return $this->ok(['deleted' => true]);
    }
}
