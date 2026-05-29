<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Platform\Models\OperationalAcronym;
use App\Modules\Platform\Services\OperationalAcronymService;
use Illuminate\Http\JsonResponse;

class CentralOperationalAcronymDestroyController extends AbstractApiController
{
    public function __invoke(OperationalAcronym $operationalAcronym, OperationalAcronymService $service): JsonResponse
    {
        $service->delete($operationalAcronym);

        return $this->ok(['deleted' => true]);
    }
}
