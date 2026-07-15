<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementBudgetLine extends Model
{
    use HasUuids;

    protected $table = 'procurement_budget_lines';

    protected $fillable = [
        'project_id',
        'rollout_id',
        'cost_center_id',
        'line_code',
        'description',
        'expense_type',
        'budget_amount',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'budget_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<ProcurementCostCenter, $this> */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(ProcurementCostCenter::class, 'cost_center_id');
    }
}
