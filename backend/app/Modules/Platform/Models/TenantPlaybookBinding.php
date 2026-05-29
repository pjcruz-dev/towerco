<?php

declare(strict_types=1);

namespace App\Modules\Platform\Models;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantPlaybookBinding extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'playbook_version_id',
        'rollout_policy_bundle_id',
        'upgrade_policy',
        'assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /** @return BelongsTo<RolloutPlaybookVersion, $this> */
    public function playbookVersion(): BelongsTo
    {
        return $this->belongsTo(RolloutPlaybookVersion::class, 'playbook_version_id');
    }

    /** @return BelongsTo<RolloutPolicyBundle, $this> */
    public function rolloutPolicyBundle(): BelongsTo
    {
        return $this->belongsTo(RolloutPolicyBundle::class, 'rollout_policy_bundle_id');
    }
}
