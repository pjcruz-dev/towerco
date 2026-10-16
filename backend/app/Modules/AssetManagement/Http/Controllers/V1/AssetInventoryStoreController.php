<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetManagement\Services\AssetInventoryService;
use App\Modules\AssetManagement\Services\AssetQueryService;
use App\Modules\AssetManagement\Support\AssetActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AssetInventoryStoreController extends AbstractApiController
{
    public function __invoke(Request $request, AssetInventoryService $inventory, AssetQueryService $query): JsonResponse
    {
        abort_unless($request->user()?->can('assets:manage'), 403);
        $data = $request->validate([
            'client_key' => ['required', 'string', 'max:64'],
            'serial_number' => ['required', 'string', 'max:128'],
            'asset_tag' => ['nullable', 'string', 'max:32'],
            'asset_type' => ['nullable', 'string', 'max:32'],
            'manufacturer' => ['nullable', 'string', 'max:180'],
            'product_name' => ['nullable', 'string', 'max:180'],
            'product_version' => ['nullable', 'string', 'max:120'],
            'hostname' => ['nullable', 'string', 'max:64'],
            'processor' => ['nullable', 'string', 'max:255'],
            'installed_ram' => ['nullable', 'string', 'max:64'],
            'operating_system' => ['nullable', 'string', 'max:180'],
            'system_type' => ['nullable', 'string', 'max:64'],
            'bios_version' => ['nullable', 'string', 'max:120'],
            'windows_identity' => ['nullable', 'string', 'max:180'],
            'matched_user_id' => ['nullable', 'uuid'],
            'network_adapters' => ['sometimes', 'array'],
            'disks' => ['sometimes', 'array'],
            'video_cards' => ['sometimes', 'array'],
            'applications' => ['sometimes', 'array'],
        ]);
        $result = $inventory->submit($data, AssetActor::from($request));

        return $this->ok([
            'created' => $result['created'],
            'idempotent' => $result['idempotent'],
            'asset' => $query->present($result['asset']),
        ]);
    }
}
