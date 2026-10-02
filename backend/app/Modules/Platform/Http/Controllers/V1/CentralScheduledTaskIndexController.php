<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Platform\Services\PlatformScheduledTaskIndexService;
use Illuminate\Http\JsonResponse;

class CentralScheduledTaskIndexController extends AbstractApiController
{
    public function __invoke(PlatformScheduledTaskIndexService $schedules): JsonResponse
    {
        return $this->ok($schedules->index());
    }
}
