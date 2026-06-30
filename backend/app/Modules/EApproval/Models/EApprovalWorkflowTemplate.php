<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EApprovalWorkflowTemplate extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'e_approval_workflow_templates';

    protected $fillable = ['form_id'];

    /** @return BelongsTo<EApprovalForm, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(EApprovalForm::class, 'form_id');
    }

    /** @return HasMany<EApprovalWorkflowStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(EApprovalWorkflowStep::class, 'template_id')
            ->whereNull('compiled_for_submission_id')
            ->orderBy('step_order');
    }

    /** @return HasMany<EApprovalWorkflowStep, $this> */
    public function allSteps(): HasMany
    {
        return $this->hasMany(EApprovalWorkflowStep::class, 'template_id')->orderBy('step_order');
    }
}
