<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EApprovalDelegation extends Model
{
    use HasUuids;

    protected $table = 'e_approval_delegations';

    protected $fillable = [
        'delegator_id',
        'delegate_id',
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
}
