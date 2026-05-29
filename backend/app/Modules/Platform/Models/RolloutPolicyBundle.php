<?php

declare(strict_types=1);

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RolloutPolicyBundle extends Model
{
    use HasUuids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'status',
        'playbook_version_id',
        'timeline_templates',
        'hidden_phases',
        'gate_approval_policies',
        'email_notification_policies',
        'delivery_periods',
        'changelog',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'timeline_templates' => 'array',
            'hidden_phases' => 'array',
            'gate_approval_policies' => 'array',
            'email_notification_policies' => 'array',
            'delivery_periods' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<RolloutPlaybookVersion, $this> */
    public function playbookVersion(): BelongsTo
    {
        return $this->belongsTo(RolloutPlaybookVersion::class, 'playbook_version_id');
    }

    /** @return HasMany<TenantPlaybookBinding, $this> */
    public function bindings(): HasMany
    {
        return $this->hasMany(TenantPlaybookBinding::class, 'rollout_policy_bundle_id');
    }
}
