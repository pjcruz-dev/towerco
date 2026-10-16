<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetManagement\Models\Accountability;
use App\Modules\AssetManagement\Services\AssetWorkflowService;
use App\Modules\AssetManagement\Support\AssetActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AccountabilitySignController extends AbstractApiController
{
    public function __invoke(Request $request, string $accountability, AssetWorkflowService $workflow): JsonResponse
    {
        abort_unless($request->user()?->can('assets:accountability:sign') || $request->user()?->can('assets:self:view'), 403);
        $data = $request->validate(['signature' => ['required', 'string', 'max:500000']]);
        $record = Accountability::query()->findOrFail($accountability);

        return $this->ok($workflow->signAccountability($record, AssetActor::from($request), $data['signature'])->toArray());
    }
}
