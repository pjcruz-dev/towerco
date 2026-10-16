<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetManagement\Models\Asset;
use App\Modules\AssetManagement\Services\AssetWorkflowService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AssetTransferPreviewController extends AbstractApiController
{
    public function __invoke(Request $request, AssetWorkflowService $workflow): JsonResponse
    {
        abort_unless($request->user()?->can('assets:transfer'), 403);
        $data = $request->validate([
            'asset_ids' => ['required', 'array', 'min:1'],
            'asset_ids.*' => ['uuid'],
            'employee_id' => ['required', 'uuid'],
        ]);
        $assets = Asset::query()->whereIn('id', $data['asset_ids'])->get()->all();
        $employee = TenantUser::query()->findOrFail($data['employee_id']);

        return $this->ok($workflow->previewTransfer($assets, $employee));
    }
}
