<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetManagement\Services\AssetQueryService;
use App\Modules\AssetManagement\Services\AssetRegistryService;
use App\Modules\AssetManagement\Support\AssetActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AssetUpdateController extends AbstractApiController
{
    public function __invoke(Request $request, string $asset, AssetRegistryService $registry, AssetQueryService $query): JsonResponse
    {
        abort_unless($request->user()?->can('assets:manage'), 403);
        $data = $request->validate([
            'serial_number' => ['sometimes', 'string', 'max:128'],
            'asset_type' => ['sometimes', 'string', 'max:32'],
            'status' => ['sometimes', 'string', 'max:32'],
            'manufacturer' => ['nullable', 'string', 'max:180'],
            'product_name' => ['nullable', 'string', 'max:180'],
            'product_version' => ['nullable', 'string', 'max:120'],
            'hostname' => ['nullable', 'string', 'max:64'],
            'processor' => ['nullable', 'string', 'max:255'],
            'installed_ram' => ['nullable', 'string', 'max:64'],
            'operating_system' => ['nullable', 'string', 'max:180'],
            'system_type' => ['nullable', 'string', 'max:64'],
            'bios_version' => ['nullable', 'string', 'max:120'],
            'purchase_date' => ['nullable', 'date'],
            'warranty_end' => ['nullable', 'date'],
            'commission_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'custom_values' => ['sometimes', 'array'],
        ]);
        $record = $registry->update($registry->findByTagOrId($asset), $data, AssetActor::from($request));

        return $this->ok($query->present($record));
    }
}
