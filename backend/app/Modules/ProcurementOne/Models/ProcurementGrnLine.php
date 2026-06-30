<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementGrnLine extends Model
{
    use HasUuids;

    protected $table = 'procurement_grn_lines';

    protected $fillable = [
        'grn_id',
        'po_line_id',
        'line_order',
        'description',
        'uom',
        'quantity_ordered',
        'quantity_received',
        'line_notes',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'decimal:4',
            'quantity_received' => 'decimal:4',
            'metadata_json' => 'array',
        ];
    }

    /** @return BelongsTo<ProcurementGrn, $this> */
    public function grn(): BelongsTo
    {
        return $this->belongsTo(ProcurementGrn::class, 'grn_id');
    }

    /** @return BelongsTo<ProcurementPoLine, $this> */
    public function poLine(): BelongsTo
    {
        return $this->belongsTo(ProcurementPoLine::class, 'po_line_id');
    }
}
