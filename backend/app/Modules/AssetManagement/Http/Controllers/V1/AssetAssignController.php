<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetManagement\Services\AssetRegistryService;
use App\Modules\AssetManagement\Services\AssetWorkflowService;
use App\Modules\AssetManagement\Support\AssetActor;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AssetAssignController extends AbstractApiController
{
    public function __invoke(Request $request, string $asset, AssetRegistryService $registry, AssetWorkflowService $workflow): JsonResponse
    {
        abort_unless($request->user()?->can('assets:assign'), 403);
        $data = $request->validate([
            'employee_id' => ['required', 'uuid'],
            'notes' => ['nullable', 'string'],
            'extra_labels' => ['sometimes', 'array'],
            'extra_labels.*' => ['string', 'max:180'],
        ]);
        $employee = TenantUser::query()->findOrFail($data['employee_id']);
        $record = $workflow->assign($registry->findByTagOrId($asset), $employee, AssetActor::from($request), $data['notes'] ?? null, $data['extra_labels'] ?? []);

        return $this->created($record->toArray());
    }
}
