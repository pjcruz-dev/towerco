<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Platform\Services\AppMenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CentralAppMenuPlaceController extends AbstractApiController
{
    public function __invoke(Request $request, AppMenuService $service): JsonResponse
    {
        $data = $request->validate([
            'group_id' => ['nullable', 'uuid'],
            'ordered_ids' => ['required', 'array'],
            'ordered_ids.*' => ['required', 'uuid'],
        ]);

        $service->placeInGroup(
            $data['group_id'] ?? null,
            $data['ordered_ids'],
        );

        return $this->ok($service->listAllAdmin());
    }
}
