<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynWorkflow;
use App\Modules\DynamicEntities\Services\DynWorkflowService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynWorkflowUpdateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        DynWorkflow $workflow,
        DynWorkflowService $service,
    ): JsonResponse {
        abort_unless($request->user()?->can('workflows:manage'), 403);

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'entity_slug' => ['sometimes', 'string', 'max:160'],
            'trigger_mode' => ['sometimes', 'string', 'in:manual,on_create,on_update'],
            'status_field' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status_matches' => ['sometimes', 'nullable'],
            'role_ids' => ['sometimes', 'nullable', 'array'],
            'role_ids.*' => ['string', 'max:64'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'definition_json' => ['sometimes', 'nullable', 'array'],
        ]);

        return $this->ok($service->update($workflow, $data, $actor));
    }
}
