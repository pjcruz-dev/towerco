<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Models;

use App\Modules\EApproval\Support\EApprovalSubmissionSource;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EApprovalSubmission extends Model
{
    use HasUuids;

    protected $table = 'e_approval_submissions';

    protected $fillable = [
        'document_no',
        'form_id',
        'requestor_id',
        'submission_source',
        'external_submitter_name',
        'external_submitter_email',
        'public_link_id',
        'external_upload_token_hash',
        'external_upload_token_expires_at',
        'external_client_ip',
        'external_user_agent',
        'status',
        'current_step',
        'parent_submission_id',
        'schema_snapshot_json',
        'workflow_snapshot_json',
        'workflow_version_id',
    ];

    protected function casts(): array
    {
        return [
            'current_step' => 'integer',
        ];
    }

    /** @return BelongsTo<EApprovalForm, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(EApprovalForm::class, 'form_id');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function requestor(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'requestor_id');
    }

    /** @return BelongsTo<EApprovalPublicFormLink, $this> */
    public function publicLink(): BelongsTo
    {
        return $this->belongsTo(EApprovalPublicFormLink::class, 'public_link_id');
    }

    /** @return HasMany<EApprovalFormValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(EApprovalFormValue::class, 'submission_id');
    }

    /** @return HasMany<EApprovalAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(EApprovalAttachment::class, 'submission_id');
    }

    /** @return HasMany<EApprovalRequestApproval, $this> */
    public function approvals(): HasMany
    {
        return $this->hasMany(EApprovalRequestApproval::class, 'submission_id');
    }

    public function isExternalSubmission(): bool
    {
        return ($this->submission_source ?? EApprovalSubmissionSource::INTERNAL) === EApprovalSubmissionSource::EXTERNAL;
    }

    /**
     * Submitter labels for mail and UI (external party vs internal requestor).
     *
     * @return array{name: string, email: string|null, internal_sponsor: string|null}
     */
    public function mailSubmitterContext(): array
    {
        $this->loadMissing('requestor:id,name,email');

        if ($this->isExternalSubmission()) {
            $name = trim((string) $this->external_submitter_name);

            return [
                'name' => $name !== '' ? $name : __('External submitter'),
                'email' => ($email = trim((string) $this->external_submitter_email)) !== '' ? $email : null,
                'internal_sponsor' => $this->requestor?->name,
            ];
        }

        return [
            'name' => $this->requestor?->name ?? __('Requestor'),
            'email' => $this->requestor?->email,
            'internal_sponsor' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toListRow(): array
    {
        $this->loadMissing(['form:id,name', 'requestor:id,name,email']);

        $context = $this->mailSubmitterContext();
        if ($this->isExternalSubmission()) {
            $requestorLabel = [
                'id' => null,
                'name' => $context['name'],
                'email' => $context['email'],
            ];
        } else {
            $requestor = $this->requestor;
            $requestorLabel = $requestor ? [
                'id' => (string) $requestor->id,
                'name' => $requestor->name,
                'email' => $requestor->email,
            ] : null;
        }

        return [
            'id' => (string) $this->id,
            'document_no' => $this->document_no,
            'status' => $this->status,
            'current_step' => $this->current_step,
            'form_id' => (string) $this->form_id,
            'form_name' => $this->form?->name,
            'submission_source' => $this->submission_source ?? 'internal',
            'requestor' => $requestorLabel,
            'external_submitter_name' => $this->external_submitter_name,
            'external_submitter_email' => $this->external_submitter_email,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
