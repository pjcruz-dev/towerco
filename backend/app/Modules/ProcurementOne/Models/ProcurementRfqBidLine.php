<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementRfqBidLine extends Model
{
    use HasUuids;

    protected $table = 'procurement_rfq_bid_lines';

    protected $fillable = [
        'bid_id',
        'rfq_line_id',
        'quantity',
        'unit_price',
        'monthly_unit_price',
        'yearly_unit_price',
        'amount',
        'amount_monthly',
        'amount_yearly',
        'normalized_annual_amount',
        'lead_time_days',
        'notes',
        'quote_basis',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'monthly_unit_price' => 'decimal:2',
            'yearly_unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
            'amount_monthly' => 'decimal:2',
            'amount_yearly' => 'decimal:2',
            'normalized_annual_amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<ProcurementRfqBid, $this> */
    public function bid(): BelongsTo
    {
        return $this->belongsTo(ProcurementRfqBid::class, 'bid_id');
    }

    /** @return BelongsTo<ProcurementRfqLine, $this> */
    public function rfqLine(): BelongsTo
    {
        return $this->belongsTo(ProcurementRfqLine::class, 'rfq_line_id');
    }
}
