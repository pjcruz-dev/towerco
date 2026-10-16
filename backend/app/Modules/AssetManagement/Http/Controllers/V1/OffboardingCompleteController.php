<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetManagement\Models\Offboarding;
use App\Modules\AssetManagement\Services\AssetWorkflowService;
use App\Modules\AssetManagement\Support\AssetActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OffboardingCompleteController extends AbstractApiController
{
    public function __invoke(Request $request, string $offboarding, AssetWorkflowService $workflow): JsonResponse
    {
        abort_unless($request->user()?->can('assets:offboard'), 403);
        $record = Offboarding::query()->findOrFail($offboarding);

        return $this->ok($workflow->completeOffboarding($record, AssetActor::from($request))->toArray());
    }
}
