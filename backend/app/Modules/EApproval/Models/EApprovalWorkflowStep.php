<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EApprovalWorkflowStep extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'e_approval_workflow_steps';

    protected $fillable = [
        'template_id',
        'step_order',
        'approver_type',
        'approver_id',
        'condition',
    ];

    protected function casts(): array
    {
        return [
            'step_order' => 'integer',
            'condition' => 'array',
        ];
    }

    /** @return BelongsTo<EApprovalWorkflowTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(EApprovalWorkflowTemplate::class, 'template_id');
    }

    /** @return HasMany<EApprovalRequestApproval, $this> */
    public function approvals(): HasMany
    {
        return $this->hasMany(EApprovalRequestApproval::class, 'step_id');
    }
}
