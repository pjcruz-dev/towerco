<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Platform\Models\AppMenuGroup;
use App\Modules\Platform\Services\AppMenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CentralAppMenuGroupUpdateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        AppMenuGroup $appMenuGroup,
        AppMenuService $service,
    ): JsonResponse {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:120'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'is_visible' => ['sometimes', 'boolean'],
        ]);

        return $this->ok($service->updateGroup($appMenuGroup, $data));
    }
}
