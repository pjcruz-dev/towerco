<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteCandidate extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'rollout_program_id',
        'client_draft_id',
        'candidate_number',
        'status',
        'label',
        'latitude',
        'longitude',
        'coordinate_capture_method',
        'coordinate_accuracy_m',
        'coordinates_captured_at',
        'lessor_name',
        'lessor_contact',
        'proposed_lease_rate_php',
        'row_notes',
        'power_notes',
        'hazard_notes',
        'photo_links',
        'lease_package',
        'rejection_reason_code',
        'rejection_notes',
        'rejected_at',
        'rejected_by_id',
        'selected_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'coordinate_accuracy_m' => 'decimal:2',
            'coordinates_captured_at' => 'datetime',
            'proposed_lease_rate_php' => 'decimal:2',
            'photo_links' => 'array',
            'lease_package' => 'array',
            'rejected_at' => 'datetime',
            'selected_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<RolloutProgram, $this> */
    public function rolloutProgram(): BelongsTo
    {
        return $this->belongsTo(RolloutProgram::class);
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'rejected_by_id');
    }
}
