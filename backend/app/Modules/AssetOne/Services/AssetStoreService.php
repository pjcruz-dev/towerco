<?php

declare(strict_types=1);

namespace App\Modules\AssetOne\Services;

use App\Modules\AssetOne\Models\Asset;
use App\Modules\AssetOne\Support\AssetStatus;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Validation\ValidationException;

final class AssetStoreService
{
    public function __construct(
        private readonly AssetCodeAllocator $codes,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input, TenantUser $actor): Asset
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages(['name' => [__('Asset name is required.')]]);
        }

        $category = trim((string) ($input['category'] ?? 'equipment'));
        $status = (string) ($input['status'] ?? AssetStatus::IN_WAREHOUSE);
        if (! AssetStatus::isValid($status)) {
            throw ValidationException::withMessages(['status' => [__('Asset status is invalid.')]]);
        }

        $assetCode = trim((string) ($input['asset_code'] ?? ''));
        if ($assetCode === '') {
            $assetCode = $this->codes->allocate();
        } elseif (Asset::query()->where('asset_code', $assetCode)->exists()) {
            throw ValidationException::withMessages(['asset_code' => [__('Asset code is already in use.')]]);
        }

        return Asset::query()->create([
            'asset_code' => $assetCode,
            'name' => $name,
            'category' => $category !== '' ? $category : 'equipment',
            'status' => $status,
            'rfid_tag' => $input['rfid_tag'] ?? null,
            'location_type' => $input['location_type'] ?? null,
            'location_id' => $input['location_id'] ?? null,
            'warranty_expiry' => $input['warranty_expiry'] ?? null,
            'purchase_value' => $input['purchase_value'] ?? null,
            'source_po_line_id' => $input['source_po_line_id'] ?? null,
            'source_grn_line_id' => $input['source_grn_line_id'] ?? null,
        ]);
    }
}
