<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementPoPrLink extends Model
{
    use HasUuids;

    protected $table = 'procurement_po_pr_links';

    protected $fillable = [
        'po_id',
        'pr_id',
        'allocated_amount',
    ];

    protected function casts(): array
    {
        return [
            'allocated_amount' => 'decimal:2',
        ];
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
