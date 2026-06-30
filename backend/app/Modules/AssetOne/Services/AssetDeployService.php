<?php

declare(strict_types=1);

namespace App\Modules\AssetOne\Services;

use App\Modules\AssetOne\Models\Asset;
use App\Modules\AssetOne\Support\AssetStatus;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Models\ProcurementInventoryLocation;
use App\Modules\ProcurementOne\Models\ProcurementPoLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AssetDeployService
{
    public function __construct(
        private readonly AssetCodeAllocator $codes,
    ) {}

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function createFromDeploy(
        ProcurementPoLine $poLine,
        ProcurementInventoryLocation $destination,
        float $quantity,
        TenantUser $actor,
        array $overrides = [],
        ?string $movementId = null,
    ): Asset {
        return DB::connection('tenant')->transaction(function () use ($poLine, $destination, $overrides): Asset {
            $name = trim((string) ($overrides['name'] ?? $poLine->description));
            if ($name === '') {
                throw ValidationException::withMessages([
                    'asset.name' => [__('Asset name is required.')],
                ]);
            }

            $category = trim((string) ($overrides['category'] ?? 'equipment'));
            if ($category === '') {
                $category = 'equipment';
            }

            $locationType = match ((string) $destination->location_kind) {
                'site' => 'site',
                'tower' => 'tower',
                default => 'warehouse',
            };

            return Asset::query()->create([
                'asset_code' => $this->codes->allocate(),
                'name' => $name,
                'category' => $category,
                'status' => AssetStatus::DEPLOYED,
                'rfid_tag' => $overrides['rfid_tag'] ?? null,
                'location_type' => $locationType,
                'location_id' => $destination->site_id ?? $destination->id,
                'warranty_expiry' => $overrides['warranty_expiry'] ?? null,
                'purchase_value' => $overrides['purchase_value'] ?? $poLine->unit_price,
                'source_po_line_id' => (string) $poLine->id,
                'source_grn_line_id' => $overrides['source_grn_line_id'] ?? null,
            ]);
        });
    }
}
