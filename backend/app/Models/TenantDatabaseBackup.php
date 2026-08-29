<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantDatabaseBackup extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_RESTORING = 'restoring';

    public const STATUS_EXPIRED = 'expired';

    public const TRIGGER_PLATFORM = 'platform';

    public const TRIGGER_SCHEDULER = 'scheduler';

    /** Central registry — must not use the tenant connection when tenancy is initialized. */
    protected $connection = 'central';

    protected $fillable = [
        'id',
        'tenant_id',
        'status',
        'storage_path',
        'byte_size',
        'checksum',
        'database_name',
        'triggered_by',
        'actor_user_id',
        'actor_email',
        'reason',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'byte_size' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isInFlight(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_RUNNING,
            self::STATUS_RESTORING,
        ], true);
    }
}
