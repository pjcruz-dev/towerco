<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetManagement\Models\Accountability;
use App\Modules\AssetManagement\Support\AssetActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AccountabilityShowController extends AbstractApiController
{
    public function __invoke(Request $request, string $accountability): JsonResponse
    {
        $user = AssetActor::from($request);
        abort_unless($user->can('assets:view') || $user->can('assets:self:view') || $user->can('assets:accountability:sign'), 403);
        $record = Accountability::query()->with(['items', 'signatures'])->findOrFail($accountability);
        if (! $user->can('assets:view') && (string) $record->employee_id !== (string) $user->id) {
            abort(404);
        }

        return $this->ok($record->toArray());
    }
}
