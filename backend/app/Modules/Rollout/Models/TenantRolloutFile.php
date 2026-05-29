<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantRolloutFile extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'tenant_rollout_files';

    protected $fillable = [
        'rollout_program_id',
        'context',
        'original_filename',
        'stored_path',
        'mime_type',
        'size_bytes',
        'uploaded_by_id',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    /** @return BelongsTo<RolloutProgram, $this> */
    public function rolloutProgram(): BelongsTo
    {
        return $this->belongsTo(RolloutProgram::class, 'rollout_program_id');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'uploaded_by_id');
    }
}
