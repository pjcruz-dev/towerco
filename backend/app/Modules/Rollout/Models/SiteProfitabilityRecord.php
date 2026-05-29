<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteProfitabilityRecord extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'rollout_program_id',
        'baseline',
        'actual',
        'vo_cost_cumulative',
        'ld_accrued_php',
        'variance_category',
        'profitability_status',
        'anchor_tenant_lease_fee_php',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'baseline' => 'array',
            'actual' => 'array',
            'vo_cost_cumulative' => 'decimal:2',
            'ld_accrued_php' => 'decimal:2',
            'anchor_tenant_lease_fee_php' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<RolloutProgram, $this> */
    public function rolloutProgram(): BelongsTo
    {
        return $this->belongsTo(RolloutProgram::class);
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'updated_by_id');
    }
}
