<?php

declare(strict_types=1);

namespace App\Modules\AssetOne\Services;

use App\Modules\AssetOne\Models\Asset;

final class AssetShowService
{
    /**
     * @return array<string, mixed>
     */
    public function asDetail(Asset $asset): array
    {
        return [
            'id' => $asset->id,
            'asset_code' => $asset->asset_code,
            'name' => $asset->name,
            'category' => $asset->category,
            'status' => $asset->status,
            'rfid_tag' => $asset->rfid_tag,
            'location_type' => $asset->location_type,
            'location_id' => $asset->location_id,
            'warranty_expiry' => $asset->warranty_expiry?->toDateString(),
            'purchase_value' => $asset->purchase_value,
            'source_po_line_id' => $asset->source_po_line_id,
            'source_grn_line_id' => $asset->source_grn_line_id,
            'created_at' => $asset->created_at?->toIso8601String(),
            'updated_at' => $asset->updated_at?->toIso8601String(),
        ];
    }
}
