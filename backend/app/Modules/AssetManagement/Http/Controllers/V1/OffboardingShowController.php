<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetManagement\Models\Offboarding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OffboardingShowController extends AbstractApiController
{
    public function __invoke(Request $request, string $offboarding): JsonResponse
    {
        abort_unless($request->user()?->can('assets:offboard') || $request->user()?->can('assets:view'), 403);
        $record = Offboarding::query()->with(['items.asset'])->findOrFail($offboarding);

        return $this->ok($record->toArray());
    }
}
