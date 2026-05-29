<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TenantRolloutPlaybookConfig extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'tenant_rollout_playbook_config';

    protected $fillable = [
        'assigned_version',
        'latest_platform_version',
        'playbook_snapshot',
        'day_overrides',
        'gate_approval_policies',
        'email_notification_policies',
        'gate_approval_escalation_working_days',
        'assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'playbook_snapshot' => 'array',
            'day_overrides' => 'array',
            'gate_approval_policies' => 'array',
            'email_notification_policies' => 'array',
            'gate_approval_escalation_working_days' => 'integer',
            'assigned_at' => 'datetime',
        ];
    }
}
