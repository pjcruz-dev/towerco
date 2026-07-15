<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementRfqBidVersionLine extends Model
{
    use HasUuids;

    protected $table = 'procurement_rfq_bid_version_lines';

    protected $fillable = [
        'version_id',
        'rfq_line_id',
        'quantity',
        'unit_price',
        'monthly_unit_price',
        'yearly_unit_price',
        'amount',
        'amount_monthly',
        'amount_yearly',
        'normalized_annual_amount',
        'quote_basis',
        'lead_time_days',
        'notes',
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

    /** @return BelongsTo<ProcurementRfqBidVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ProcurementRfqBidVersion::class, 'version_id');
    }

    /** @return BelongsTo<ProcurementRfqLine, $this> */
    public function rfqLine(): BelongsTo
    {
        return $this->belongsTo(ProcurementRfqLine::class, 'rfq_line_id');
    }
}
