<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Platform\Services\AppMenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CentralAppMenuGroupReorderController extends AbstractApiController
{
    public function __invoke(Request $request, AppMenuService $service): JsonResponse
    {
        $data = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['required', 'uuid'],
        ]);

        $service->reorderGroups($data['ordered_ids']);

        return $this->ok(['groups' => $service->listGroups()]);
    }
}
