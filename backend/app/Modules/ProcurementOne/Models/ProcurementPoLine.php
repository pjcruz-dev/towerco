<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcurementPoLine extends Model
{
    use HasUuids;

    protected $table = 'procurement_po_lines';

    protected $fillable = [
        'po_id',
        'pr_id',
        'pr_line_id',
        'line_order',
        'item',
        'description',
        'uom',
        'quantity',
        'unit_price',
        'discount',
        'amount',
        'cost_center_id',
        'expense_type',
        'budget_line_id',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'amount' => 'decimal:2',
            'metadata_json' => 'array',
        ];
    }

    /** @return HasMany<ProcurementGrnLine, $this> */
    public function grnLines(): HasMany
    {
        return $this->hasMany(ProcurementGrnLine::class, 'po_line_id');
    }

    /** @return BelongsTo<ProcurementPo, $this> */
    public function po(): BelongsTo
    {
        return $this->belongsTo(ProcurementPo::class, 'po_id');
    }

    /** @return BelongsTo<ProcurementPr, $this> */
    public function purchaseRequisition(): BelongsTo
    {
        return $this->belongsTo(ProcurementPr::class, 'pr_id');
    }
}
