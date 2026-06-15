<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantBillingRfiCompletion extends Model
{
    use HasUuids;

    /** Central ledger — must not use the tenant connection when tenancy is initialized. */
    protected $connection = 'central';

    protected $fillable = [
        'tenant_id',
        'rollout_id',
        'site_id',
        'rfi_at',
    ];

    protected function casts(): array
    {
        return [
            'rfi_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
