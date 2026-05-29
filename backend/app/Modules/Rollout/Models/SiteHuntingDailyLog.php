<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteHuntingDailyLog extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'rollout_program_id',
        'client_draft_id',
        'log_date',
        'hunter_user_id',
        'summary',
        'candidate_ids',
        'candidates_identified_count',
        'photo_links',
    ];

    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'candidate_ids' => 'array',
            'candidates_identified_count' => 'integer',
            'photo_links' => 'array',
        ];
    }

    /** @return BelongsTo<RolloutProgram, $this> */
    public function rolloutProgram(): BelongsTo
    {
        return $this->belongsTo(RolloutProgram::class);
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function hunter(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'hunter_user_id');
    }
}
