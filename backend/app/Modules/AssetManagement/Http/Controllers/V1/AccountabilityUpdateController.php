<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetManagement\Models\Accountability;
use App\Modules\AssetManagement\Services\AssetWorkflowService;
use App\Modules\AssetManagement\Support\AssetActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AccountabilityUpdateController extends AbstractApiController
{
    public function __invoke(Request $request, string $accountability, AssetWorkflowService $workflow): JsonResponse
    {
        abort_unless($request->user()?->can('assets:assign'), 403);
        $data = $request->validate([
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.asset_id' => ['nullable', 'uuid'],
            'items.*.label' => ['required', 'string', 'max:180'],
        ]);
        $record = Accountability::query()->findOrFail($accountability);

        return $this->ok($workflow->updateAccountability($record, $data['items'], $data['notes'] ?? null, AssetActor::from($request))->toArray());
    }
}
