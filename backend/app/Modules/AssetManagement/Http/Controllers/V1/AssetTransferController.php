<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetManagement\Services\AssetWorkflowService;
use App\Modules\AssetManagement\Support\AssetActor;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AssetTransferController extends AbstractApiController
{
    public function __invoke(Request $request, AssetWorkflowService $workflow): JsonResponse
    {
        abort_unless($request->user()?->can('assets:transfer'), 403);
        $data = $request->validate([
            'asset_ids' => ['required', 'array', 'min:1'],
            'asset_ids.*' => ['uuid'],
            'employee_id' => ['required', 'uuid'],
            'confirm' => ['required', 'boolean'],
        ]);
        $employee = TenantUser::query()->findOrFail($data['employee_id']);
        $record = $workflow->transfer($data['asset_ids'], $employee, AssetActor::from($request), (bool) $data['confirm']);

        return $this->created($record->toArray());
    }
}
