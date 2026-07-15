<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RolloutPermit extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'rollout_program_id',
        'permit_type',
        'applied_date',
        'secured_date',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'applied_date' => 'date',
            'secured_date' => 'date',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<RolloutProgram, $this> */
    public function rollout(): BelongsTo
    {
        return $this->belongsTo(RolloutProgram::class, 'rollout_program_id');
    }
}
