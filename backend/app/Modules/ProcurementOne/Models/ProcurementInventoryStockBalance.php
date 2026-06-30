<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementInventoryStockBalance extends Model
{
    use HasUuids;

    protected $table = 'procurement_inventory_stock_balances';

    protected $fillable = [
        'location_id',
        'po_line_id',
        'stock_key',
        'description',
        'uom',
        'quantity_on_hand',
    ];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<ProcurementInventoryLocation, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(ProcurementInventoryLocation::class, 'location_id');
    }

    /** @return BelongsTo<ProcurementPoLine, $this> */
    public function poLine(): BelongsTo
    {
        return $this->belongsTo(ProcurementPoLine::class, 'po_line_id');
    }
}
