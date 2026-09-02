<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Platform\Services\AppMenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CentralAppMenuGroupStoreController extends AbstractApiController
{
    public function __invoke(Request $request, AppMenuService $service): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'key' => ['nullable', 'string', 'max:128'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'is_visible' => ['sometimes', 'boolean'],
        ]);

        return $this->ok($service->createGroup($data), 201);
    }
}
