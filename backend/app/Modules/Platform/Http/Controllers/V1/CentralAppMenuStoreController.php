<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Platform\Services\AppMenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CentralAppMenuStoreController extends AbstractApiController
{
    public function __invoke(Request $request, AppMenuService $service): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:64'],
            'accent' => ['nullable', 'string', 'max:32'],
            'href' => ['required', 'string', 'max:1024'],
            'group_id' => ['nullable', 'uuid'],
            'open_in_new_tab' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'is_visible' => ['sometimes', 'boolean'],
            'key' => ['nullable', 'string', 'max:128'],
        ]);

        return $this->ok($service->create($data), 201);
    }
}
