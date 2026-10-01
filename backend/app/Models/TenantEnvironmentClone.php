<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantEnvironmentClone extends Model
{
    use HasUuids;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_COPYING_DATABASE = 'copying_database';

    public const STATUS_COPYING_FILES = 'copying_files';

    public const STATUS_VERIFYING = 'verifying';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_DISCARDED = 'discarded';

    /** @var list<string> */
    public const ACTIVE_STATUSES = [
        self::STATUS_QUEUED,
        self::STATUS_COPYING_DATABASE,
        self::STATUS_COPYING_FILES,
        self::STATUS_VERIFYING,
    ];

    protected $connection = 'central';

    protected $fillable = [
        'id',
        'source_tenant_id',
        'target_tenant_id',
        'status',
        'pause_source',
        'cancel_requested',
        'source_access_mode_before',
        'snapshot_counts',
        'result_counts',
        'files_total',
        'files_copied',
        'paused_schedules',
        'error_message',
        'actor_user_id',
        'actor_email',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'pause_source' => 'boolean',
            'cancel_requested' => 'boolean',
            'snapshot_counts' => 'array',
            'result_counts' => 'array',
            'files_total' => 'integer',
            'files_copied' => 'integer',
            'paused_schedules' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function sourceTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'source_tenant_id');
    }

    public function targetTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'target_tenant_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }
}
