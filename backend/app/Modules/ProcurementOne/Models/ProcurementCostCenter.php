<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcurementCostCenter extends Model
{
    use HasUuids;

    protected $table = 'procurement_cost_centers';

    protected $fillable = [
        'code',
        'name',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<ProcurementBudgetLine, $this> */
    public function budgetLines(): HasMany
    {
        return $this->hasMany(ProcurementBudgetLine::class, 'cost_center_id');
    }
}
