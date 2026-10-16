<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetManagement\Models\Asset;
use App\Modules\AssetManagement\Models\AssetRepair;
use App\Modules\AssetManagement\Services\AssetWorkflowService;
use App\Modules\AssetManagement\Support\AssetActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AssetReplacementController extends AbstractApiController
{
    public function __invoke(Request $request, string $repair, AssetWorkflowService $workflow): JsonResponse
    {
        abort_unless($request->user()?->can('assets:repair'), 403);
        $data = $request->validate([
            'replacement_asset_id' => ['required', 'uuid'],
            'replacement_type' => ['required', 'string', 'in:TEMPORARY,PERMANENT,temporary,permanent'],
        ]);
        $record = AssetRepair::query()->findOrFail($repair);
        $replacement = Asset::query()->findOrFail($data['replacement_asset_id']);

        return $this->created($workflow->issueReplacement($record, $replacement, $data['replacement_type'], AssetActor::from($request))->toArray());
    }
}
