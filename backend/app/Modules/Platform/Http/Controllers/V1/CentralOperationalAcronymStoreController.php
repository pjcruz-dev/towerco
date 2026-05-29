<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Platform\Services\OperationalAcronymService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CentralOperationalAcronymStoreController extends AbstractApiController
{
    public function __invoke(Request $request, OperationalAcronymService $service): JsonResponse
    {
        $data = $request->validate([
            'acronym' => ['required', 'string', 'max:96'],
            'definition' => ['required', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:64'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return $this->ok($service->create($data), 201);
    }
}
