<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DynScheduledTaskRun extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $connection = 'tenant';

    protected $table = 'dyn_scheduled_task_runs';

    protected $fillable = [
        'dyn_scheduled_task_id',
        'ran_at',
        'status',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'ran_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<DynScheduledTask, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(DynScheduledTask::class, 'dyn_scheduled_task_id');
    }
}
