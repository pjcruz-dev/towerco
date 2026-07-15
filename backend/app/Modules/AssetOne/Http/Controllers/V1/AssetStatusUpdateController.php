<?php

declare(strict_types=1);

namespace App\Modules\AssetOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetOne\Models\Asset;
use App\Modules\AssetOne\Services\AssetLifecycleService;
use App\Modules\AssetOne\Services\AssetShowService;
use App\Modules\AssetOne\Support\AssetStatus;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AssetStatusUpdateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $asset,
        AssetLifecycleService $service,
        AssetShowService $presenter,
    ): JsonResponse {
        abort_unless($request->user()?->can('asset_one:assets:manage'), 403);

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $model = Asset::query()->find($asset);
        abort_if($model === null, 404);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', AssetStatus::all())],
            'location_type' => ['nullable', 'string', 'max:32'],
            'location_id' => ['nullable', 'uuid'],
        ]);

        $updated = $service->updateStatus($model, $data, $actor);

        return $this->ok($presenter->asDetail($updated));
    }
}
