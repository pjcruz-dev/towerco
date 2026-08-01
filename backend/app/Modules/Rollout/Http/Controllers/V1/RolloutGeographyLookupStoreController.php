<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Services\RolloutGeographyLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolloutGeographyLookupStoreController extends AbstractApiController
{
    public function __invoke(Request $request, RolloutGeographyLookupService $service): JsonResponse
    {
        abort_unless($request->user()?->can('project_one:playbook:configure'), 403);

        $data = $request->validate([
            'kind' => ['required', 'string', 'in:region,territory'],
            'code' => ['required', 'string', 'max:32'],
            'label' => ['required', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $row = $service->create($data);

        return $this->created($service->present($row));
    }
}
