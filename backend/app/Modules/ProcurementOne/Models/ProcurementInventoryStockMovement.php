<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Models;

use App\Modules\AssetOne\Models\Asset;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementInventoryStockMovement extends Model
{
    use HasUuids;

    protected $table = 'procurement_inventory_stock_movements';

    protected $fillable = [
        'movement_type',
        'transfer_batch_id',
        'location_id',
        'counterparty_location_id',
        'grn_id',
        'grn_line_id',
        'po_line_id',
        'asset_id',
        'stock_key',
        'description',
        'uom',
        'quantity',
        'notes',
        'metadata_json',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'metadata_json' => 'array',
        ];
    }

    /** @return BelongsTo<ProcurementInventoryLocation, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(ProcurementInventoryLocation::class, 'location_id');
    }

    /** @return BelongsTo<ProcurementInventoryLocation, $this> */
    public function counterpartyLocation(): BelongsTo
    {
        return $this->belongsTo(ProcurementInventoryLocation::class, 'counterparty_location_id');
    }

    /** @return BelongsTo<ProcurementGrn, $this> */
    public function grn(): BelongsTo
    {
        return $this->belongsTo(ProcurementGrn::class, 'grn_id');
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'created_by_id');
    }
}
