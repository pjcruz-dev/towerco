<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Platform\Services\AppMenuService;
use Illuminate\Http\JsonResponse;

final class CentralAppMenuGroupIndexController extends AbstractApiController
{
    public function __invoke(AppMenuService $service): JsonResponse
    {
        return $this->ok(['groups' => $service->listGroups()]);
    }
}
