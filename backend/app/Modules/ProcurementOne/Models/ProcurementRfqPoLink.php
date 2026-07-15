<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementRfqPoLink extends Model
{
    use HasUuids;

    protected $table = 'procurement_rfq_po_links';

    protected $fillable = [
        'rfq_id',
        'po_id',
        'bid_id',
        'allocated_amount',
    ];

    protected function casts(): array
    {
        return [
            'allocated_amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<ProcurementRfq, $this> */
    public function rfq(): BelongsTo
    {
        return $this->belongsTo(ProcurementRfq::class, 'rfq_id');
    }

    /** @return BelongsTo<ProcurementPo, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(ProcurementPo::class, 'po_id');
    }

    /** @return BelongsTo<ProcurementRfqBid, $this> */
    public function bid(): BelongsTo
    {
        return $this->belongsTo(ProcurementRfqBid::class, 'bid_id');
    }
}
