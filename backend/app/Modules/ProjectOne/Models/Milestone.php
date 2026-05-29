<?php

declare(strict_types=1);

namespace App\Modules\ProjectOne\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Milestone extends Model
{
    use HasUuids;

    protected $table = 'milestones';

    protected $fillable = [
        'project_id',
        'name',
        'due_date',
        'status',
        'order_index',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'order_index' => 'integer',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
