<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetManagement\Models\OffboardingItem;
use App\Modules\AssetManagement\Services\AssetWorkflowService;
use App\Modules\AssetManagement\Support\AssetActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OffboardingItemController extends AbstractApiController
{
    public function __invoke(Request $request, string $offboarding, string $item, AssetWorkflowService $workflow): JsonResponse
    {
        abort_unless($request->user()?->can('assets:offboard'), 403);
        $data = $request->validate([
            'scanned_tag' => ['required', 'string', 'max:32'],
            'result' => ['required', 'string', 'max:32'],
            'condition' => ['nullable', 'string', 'max:32'],
            'physical_damage' => ['nullable', 'boolean'],
            'accessories' => ['nullable', 'string', 'max:180'],
            'battery_condition' => ['nullable', 'string', 'max:64'],
            'hardware_condition' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string'],
        ]);
        $row = OffboardingItem::query()->where('offboarding_id', $offboarding)->findOrFail($item);

        return $this->ok($workflow->recordOffboardingItem($row, $data['scanned_tag'], $data, AssetActor::from($request))->toArray());
    }
}
