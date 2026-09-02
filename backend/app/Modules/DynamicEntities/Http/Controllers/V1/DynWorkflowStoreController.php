<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Services\DynWorkflowService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynWorkflowStoreController extends AbstractApiController
{
    public function __invoke(Request $request, DynWorkflowService $service): JsonResponse
    {
        abort_unless($request->user()?->can('workflows:manage'), 403);

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'entity_slug' => ['required', 'string', 'max:160'],
            'trigger_mode' => ['nullable', 'string', 'in:manual,on_create,on_update'],
            'status_field' => ['nullable', 'string', 'max:120'],
            'status_matches' => ['nullable'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['string', 'max:64'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'definition_json' => ['nullable', 'array'],
        ]);

        return $this->ok($service->create($data, $actor), 201);
    }
}
