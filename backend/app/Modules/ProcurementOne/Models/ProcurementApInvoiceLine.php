<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementApInvoiceLine extends Model
{
    use HasUuids;

    protected $table = 'procurement_ap_invoice_lines';

    protected $fillable = [
        'ap_invoice_id',
        'po_line_id',
        'grn_line_id',
        'line_order',
        'description',
        'uom',
        'quantity_invoiced',
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
            'quantity_invoiced' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'discount' => 'decimal:2',
            'amount' => 'decimal:2',
            'metadata_json' => 'array',
        ];
    }

    /** @return BelongsTo<ProcurementApInvoice, $this> */
    public function apInvoice(): BelongsTo
    {
        return $this->belongsTo(ProcurementApInvoice::class, 'ap_invoice_id');
    }

    /** @return BelongsTo<ProcurementPoLine, $this> */
    public function poLine(): BelongsTo
    {
        return $this->belongsTo(ProcurementPoLine::class, 'po_line_id');
    }

    /** @return BelongsTo<ProcurementGrnLine, $this> */
    public function grnLine(): BelongsTo
    {
        return $this->belongsTo(ProcurementGrnLine::class, 'grn_line_id');
    }
}
