<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RolloutTimelinePhase extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'rollout_program_id',
        'phase_key',
        'label',
        'owner_role',
        'anchor',
        'working_day_start',
        'working_day_end',
        'target_working_day_end',
        'target_start_date',
        'target_end_date',
        'actual_start_date',
        'actual_end_date',
        'gate_status',
        'gate_label',
        'counts_toward_sla',
        'is_custom',
        'catalog_phase_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'working_day_start' => 'integer',
            'working_day_end' => 'integer',
            'target_working_day_end' => 'integer',
            'target_start_date' => 'date',
            'target_end_date' => 'date',
            'actual_start_date' => 'date',
            'actual_end_date' => 'date',
            'sort_order' => 'integer',
            'counts_toward_sla' => 'boolean',
            'is_custom' => 'boolean',
        ];
    }

    /** @return BelongsTo<RolloutProgram, $this> */
    public function rolloutProgram(): BelongsTo
    {
        return $this->belongsTo(RolloutProgram::class);
    }
}
