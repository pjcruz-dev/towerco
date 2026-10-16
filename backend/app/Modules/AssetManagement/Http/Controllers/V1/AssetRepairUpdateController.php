<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetManagement\Models\AssetRepair;
use App\Modules\AssetManagement\Services\AssetWorkflowService;
use App\Modules\AssetManagement\Support\AssetActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AssetRepairUpdateController extends AbstractApiController
{
    public function __invoke(Request $request, string $repair, AssetWorkflowService $workflow): JsonResponse
    {
        abort_unless($request->user()?->can('assets:repair'), 403);
        $data = $request->validate([
            'result' => ['sometimes', 'string', 'max:32'],
            'vendor_email_subject' => ['sometimes', 'string', 'max:255'],
            'vendor_email_body' => ['sometimes', 'string'],
        ]);
        $record = AssetRepair::query()->findOrFail($repair);

        return $this->ok($workflow->updateRepair($record, $data, AssetActor::from($request))->toArray());
    }
}
