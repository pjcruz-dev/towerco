<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetManagement\Services\AssetWorkflowService;
use App\Modules\AssetManagement\Support\AssetActor;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OffboardingStoreController extends AbstractApiController
{
    public function __invoke(Request $request, AssetWorkflowService $workflow): JsonResponse
    {
        abort_unless($request->user()?->can('assets:offboard'), 403);
        $data = $request->validate([
            'employee_id' => ['required', 'uuid'],
            'last_working_day' => ['nullable', 'date'],
        ]);
        $employee = TenantUser::query()->findOrFail($data['employee_id']);

        return $this->created($workflow->startOffboarding($employee, AssetActor::from($request), $data['last_working_day'] ?? null)->toArray());
    }
}
