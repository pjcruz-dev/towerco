<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Platform\Services\OperationalAcronymService;
use Illuminate\Http\JsonResponse;

class CentralOperationalAcronymPublicIndexController extends AbstractApiController
{
    public function __invoke(OperationalAcronymService $service): JsonResponse
    {
        return $this->ok($service->listActive());
    }
}
