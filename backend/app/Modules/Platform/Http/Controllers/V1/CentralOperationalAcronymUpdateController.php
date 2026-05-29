<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Platform\Models\OperationalAcronym;
use App\Modules\Platform\Services\OperationalAcronymService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CentralOperationalAcronymUpdateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        OperationalAcronym $operationalAcronym,
        OperationalAcronymService $service,
    ): JsonResponse {
        $data = $request->validate([
            'acronym' => ['sometimes', 'string', 'max:96'],
            'definition' => ['sometimes', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:64'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return $this->ok($service->update($operationalAcronym, $data));
    }
}
