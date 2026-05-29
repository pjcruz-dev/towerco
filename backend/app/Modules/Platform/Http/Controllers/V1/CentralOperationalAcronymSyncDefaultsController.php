<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Platform\Services\OperationalAcronymService;
use App\Modules\Platform\Support\OperationalAcronymDefaults;
use Illuminate\Http\JsonResponse;

class CentralOperationalAcronymSyncDefaultsController extends AbstractApiController
{
    public function __invoke(OperationalAcronymService $service): JsonResponse
    {
        $count = $service->syncDefaults(OperationalAcronymDefaults::all());

        return $this->ok([
            'synced' => $count,
            'message' => __('Default operational acronyms synced.'),
        ]);
    }
}
