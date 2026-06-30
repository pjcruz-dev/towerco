<?php

declare(strict_types=1);

namespace App\Modules\AssetOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetOne\Services\AssetShowService;
use App\Modules\AssetOne\Services\AssetStoreService;
use App\Modules\AssetOne\Support\AssetStatus;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AssetStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        AssetStoreService $service,
        AssetShowService $presenter,
    ): JsonResponse {
        abort_unless($request->user()?->can('asset_one:assets:manage'), 403);

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $data = $request->validate([
            'asset_code' => ['nullable', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:200'],
            'category' => ['sometimes', 'string', 'max:64'],
            'status' => ['sometimes', 'string', 'in:'.implode(',', AssetStatus::all())],
            'rfid_tag' => ['nullable', 'string', 'max:120'],
            'location_type' => ['nullable', 'string', 'max:32'],
            'location_id' => ['nullable', 'uuid'],
            'warranty_expiry' => ['nullable', 'date'],
            'purchase_value' => ['nullable', 'numeric', 'min:0'],
            'source_po_line_id' => ['nullable', 'uuid'],
            'source_grn_line_id' => ['nullable', 'uuid'],
        ]);

        $asset = $service->create($data, $actor);

        return $this->created($presenter->asDetail($asset));
    }
}
