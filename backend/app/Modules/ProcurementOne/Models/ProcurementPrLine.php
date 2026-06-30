<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementPrLine extends Model
{
    use HasUuids;

    protected $table = 'procurement_pr_lines';

    protected $fillable = [
        'pr_id',
        'line_order',
        'description',
        'quantity',
        'unit_price',
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
            'amount' => 'decimal:2',
            'metadata_json' => 'array',
        ];
    }

    /** @return BelongsTo<ProcurementPr, $this> */
    public function pr(): BelongsTo
    {
        return $this->belongsTo(ProcurementPr::class, 'pr_id');
    }
}
