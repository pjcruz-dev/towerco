<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DynScheduledTask extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'dyn_scheduled_tasks';

    protected $fillable = [
        'number',
        'name',
        'description',
        'command_key',
        'schedule',
        'cron_expression',
        'is_system',
        'is_active',
        'last_run_at',
        'next_run_at',
        'last_status',
        'last_error',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
            'number' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
