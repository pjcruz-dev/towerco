<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RolloutGateApprovalDelegation extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'delegator_id',
        'delegate_id',
        'role_key',
        'valid_from',
        'valid_until',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function delegator(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'delegator_id');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function delegate(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'delegate_id');
    }

    public function isEffectiveOn(\Carbon\Carbon $date): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->valid_from !== null && $date->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_until !== null && $date->gt($this->valid_until)) {
            return false;
        }

        return true;
    }
}
