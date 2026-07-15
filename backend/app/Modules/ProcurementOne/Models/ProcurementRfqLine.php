<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementRfqLine extends Model
{
    use HasUuids;

    protected $table = 'procurement_rfq_lines';

    protected $fillable = [
        'rfq_id',
        'line_order',
        'pr_line_id',
        'description',
        'uom',
        'quantity',
        'target_unit_price',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'target_unit_price' => 'decimal:2',
            'metadata_json' => 'array',
        ];
    }

    /** @return BelongsTo<ProcurementRfq, $this> */
    public function rfq(): BelongsTo
    {
        return $this->belongsTo(ProcurementRfq::class, 'rfq_id');
    }

    /** @return BelongsTo<ProcurementPrLine, $this> */
    public function prLine(): BelongsTo
    {
        return $this->belongsTo(ProcurementPrLine::class, 'pr_line_id');
    }
}
