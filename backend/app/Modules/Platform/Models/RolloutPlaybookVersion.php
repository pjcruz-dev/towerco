<?php

declare(strict_types=1);

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RolloutPlaybookVersion extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'version',
        'name',
        'status',
        'sla_working_days_only',
        'delivery_periods',
        'timeline_templates',
        'milestone_cycle_targets',
        'form_schemas',
        'changelog',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'sla_working_days_only' => 'boolean',
            'delivery_periods' => 'array',
            'timeline_templates' => 'array',
            'milestone_cycle_targets' => 'array',
            'form_schemas' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /** @return HasMany<TenantPlaybookBinding, $this> */
    public function bindings(): HasMany
    {
        return $this->hasMany(TenantPlaybookBinding::class, 'playbook_version_id');
    }
}
