<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Platform\Services\AppMenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CentralAppMenuSettingsUpdateController extends AbstractApiController
{
    public function __invoke(Request $request, AppMenuService $service): JsonResponse
    {
        $data = $request->validate([
            'grid_columns' => ['required', 'integer', 'min:3', 'max:6'],
        ]);

        return $this->ok(['settings' => $service->updateSettings($data)]);
    }
}
