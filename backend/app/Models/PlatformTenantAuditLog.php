<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformTenantAuditLog extends Model
{
    use HasUuids;

    /** Central registry — must not use the tenant connection when tenancy is initialized. */
    protected $connection = 'central';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'event_type',
        'actor_user_id',
        'actor_email',
        'changes',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
