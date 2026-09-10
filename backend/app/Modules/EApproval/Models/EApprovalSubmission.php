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
        'id',
        'document_no',
        'form_id',
        'requestor_id',
        'submission_source',
        'external_submitter_name',
        'external_submitter_email',
        'public_link_id',
        'external_upload_token_hash',
        'external_upload_token_expires_at',
        'external_resubmit_token_hash',
        'external_resubmit_token_expires_at',
        'external_client_ip',
        'external_user_agent',
        'status',
        'current_step',
        'returned_from_step',
        'force_full_restart',
        'approval_cycle',
        'last_revision_routing',
        'last_revision_routing_reason',
        'parent_submission_id',
        'schema_snapshot_json',
        'workflow_snapshot_json',
        'workflow_version_id',
        'approval_policy_version_id',
        'approval_policy_label',
    ];

    protected function casts(): array
    {
        return [
            'current_step' => 'integer',
            'returned_from_step' => 'integer',
            'force_full_restart' => 'boolean',
            'approval_cycle' => 'integer',
            'external_upload_token_expires_at' => 'datetime',
            'external_resubmit_token_expires_at' => 'datetime',
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
        $this->loadMissing([
            'form.workflowTemplate.steps',
            'requestor:id,name,email',
            'approvals.step',
            'approvals.approver',
        ]);

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
            'step_count' => $this->resolvedStepCount(),
            'returned_from_step' => $this->returned_from_step,
            'force_full_restart' => (bool) $this->force_full_restart,
            'approval_cycle' => (int) ($this->approval_cycle ?: 1),
            'last_revision_routing' => $this->last_revision_routing,
            'last_revision_routing_reason' => $this->last_revision_routing_reason,
            'form_id' => (string) $this->form_id,
            'form_name' => $this->form?->name,
            'subsidiary' => $this->scalarFieldValue('subsidiary'),
            'department' => $this->scalarFieldValue('department'),
            'workflow_steps' => $this->workflowStepsSummary(),
            'submission_source' => $this->submission_source ?? 'internal',
            'requestor' => $requestorLabel,
            'external_submitter_name' => $this->external_submitter_name,
            'external_submitter_email' => $this->external_submitter_email,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Compact per-step approver trail for list/gallery Step Show.
     *
     * @return list<array{
     *     step_order: int,
     *     label: string,
     *     state: string,
     *     status_label: string,
     *     approver_name: string|null,
     *     approver_names: list<string>
     * }>
     */
    private function workflowStepsSummary(): array
    {
        $stepCount = $this->resolvedStepCount();
        $currentStep = max(0, (int) $this->current_step);
        $submissionStatus = strtolower(trim((string) $this->status));
        $total = max(
            $stepCount !== null && $stepCount > 0 ? $stepCount : 0,
            $currentStep > 0 ? $currentStep : 0,
            1,
        );

        /** @var array<int, list<array{name: string, status: string}>> $byOrder */
        $byOrder = [];
        /** @var array<int, true> $activeOrders */
        $activeOrders = [];
        /** @var array<int, array{approver_type: string, approver_id: string|null}> $snapshotStepMeta */
        $snapshotStepMeta = [];

        $snapshot = $this->decodedWorkflowSnapshot();
        $policyContext = is_array($snapshot['policy_context'] ?? null) ? $snapshot['policy_context'] : [];
        $snapshotOrders = [];
        foreach (is_array($snapshot['steps'] ?? null) ? $snapshot['steps'] : [] as $step) {
            if (! is_array($step)) {
                continue;
            }
            $order = (int) ($step['step_order'] ?? 0);
            if ($order <= 0) {
                continue;
            }
            $snapshotOrders[] = $order;
            $activeOrders[$order] = true;
            $snapshotStepMeta[$order] = [
                'approver_type' => strtolower(trim((string) ($step['approver_type'] ?? ''))),
                'approver_id' => isset($step['approver_id']) ? trim((string) $step['approver_id']) : null,
            ];
        }
        $snapshotOrders = array_values(array_unique($snapshotOrders));

        if ($this->relationLoaded('approvals')) {
            $cycle = (int) ($this->approval_cycle ?: 1);
            foreach ($this->approvals as $approval) {
                if ((int) ($approval->approval_cycle ?: 1) !== $cycle) {
                    continue;
                }
                if ((string) $approval->status === 'superseded') {
                    continue;
                }

                $order = (int) ($approval->relationLoaded('step') ? ($approval->step?->step_order ?? 0) : 0);
                if ($order <= 0) {
                    continue;
                }

                $name = $approval->relationLoaded('approver')
                    ? trim((string) ($approval->approver?->name ?? ''))
                    : '';
                $byOrder[$order][] = [
                    'name' => $name !== '' ? $name : 'Unknown approver',
                    'status' => strtolower(trim((string) $approval->status)),
                ];
                if ((string) $approval->status !== 'invalidated') {
                    $activeOrders[$order] = true;
                }
            }
        }

        if ($byOrder !== []) {
            $total = max($total, max(array_keys($byOrder)));
        }
        if ($snapshotOrders !== []) {
            $total = max($total, max($snapshotOrders));
        }

        $approvedAll = $submissionStatus === 'approved';
        $cancelled = $submissionStatus === 'cancelled';
        $hasSnapshotScope = $snapshotOrders !== [];

        $rows = [];
        for ($order = 1; $order <= $total; $order++) {
            $actors = $byOrder[$order] ?? [];
            $pendingNames = [];
            $approvedNames = [];
            $otherNames = [];
            $invalidatedOnly = $actors !== [];

            foreach ($actors as $actor) {
                if ($actor['status'] === 'pending') {
                    $pendingNames[] = $actor['name'];
                    $invalidatedOnly = false;
                } elseif ($actor['status'] === 'approved') {
                    $approvedNames[] = $actor['name'];
                    $invalidatedOnly = false;
                } elseif ($actor['status'] === 'invalidated') {
                    // keep invalidatedOnly unless other statuses appear
                } elseif (! in_array($actor['status'], ['superseded'], true)) {
                    $otherNames[] = $actor['name'];
                    $invalidatedOnly = false;
                }
            }

            $pendingNames = array_values(array_unique($pendingNames));
            $approvedNames = array_values(array_unique($approvedNames));

            $notInActivePath = $hasSnapshotScope && ! isset($activeOrders[$order]) && $pendingNames === [] && $approvedNames === [];
            $unresolvable = $actors === []
                && $pendingNames === []
                && $approvedNames === []
                && $this->snapshotStepIsUnresolvable($snapshotStepMeta[$order] ?? null, $policyContext);

            if ($invalidatedOnly || $notInActivePath || $unresolvable) {
                $state = 'skipped';
                $statusLabel = 'Skipped';
                $names = [];
            } elseif ($approvedAll || ($actors !== [] && $pendingNames === [] && $approvedNames !== [] && $otherNames === [])) {
                $state = 'completed';
                $statusLabel = 'Approved';
                $names = $approvedNames !== [] ? $approvedNames : $otherNames;
            } elseif ($cancelled && ($currentStep <= 0 || $order >= $currentStep) && $pendingNames === []) {
                $state = $order < $currentStep ? 'completed' : 'cancelled';
                $statusLabel = $state === 'completed' ? 'Approved' : 'Cancelled';
                $names = $approvedNames !== [] ? $approvedNames : $otherNames;
            } elseif ($pendingNames !== [] || ($currentStep > 0 && $order === $currentStep && ! $approvedAll && ! $cancelled)) {
                $state = 'current';
                $statusLabel = 'Pending';
                $names = $pendingNames !== [] ? $pendingNames : ($otherNames !== [] ? $otherNames : $approvedNames);
            } elseif ($order < $currentStep || $approvedNames !== []) {
                $state = 'completed';
                $statusLabel = 'Approved';
                $names = $approvedNames !== [] ? $approvedNames : $otherNames;
            } else {
                $state = 'upcoming';
                $statusLabel = 'Upcoming';
                $names = [];
            }

            $primary = $names[0] ?? null;
            $rows[] = [
                'step_order' => $order,
                'label' => 'Step '.$order,
                'state' => $state,
                'status_label' => $statusLabel,
                'approver_name' => $primary,
                'approver_names' => $names,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodedWorkflowSnapshot(): array
    {
        $raw = $this->workflow_snapshot_json;
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Empty dynamic approver fields can never activate — match detail path "Skipped".
     *
     * @param  array{approver_type: string, approver_id: string|null}|null  $meta
     * @param  array<string, mixed>  $policyContext
     */
    private function snapshotStepIsUnresolvable(?array $meta, array $policyContext): bool
    {
        if ($meta === null) {
            return false;
        }

        $type = $meta['approver_type'];
        if (! in_array($type, ['field', 'user_list', 'field_map'], true)) {
            return false;
        }

        $field = trim((string) ($meta['approver_id'] ?? ''));
        if ($field === '') {
            return true;
        }

        $raw = $policyContext[$field] ?? null;
        if ($raw === null) {
            return true;
        }
        if (is_string($raw) && trim($raw) === '') {
            return true;
        }
        if (is_array($raw) && $raw === []) {
            return true;
        }

        return false;
    }

    private function scalarFieldValue(string $fieldName): ?string
    {
        if (! $this->relationLoaded('values')) {
            return null;
        }

        foreach ($this->values as $value) {
            $name = $value->relationLoaded('field') ? $value->field?->name : null;
            if ($name === $fieldName) {
                $raw = trim((string) ($value->value ?? ''));

                return $raw !== '' ? $raw : null;
            }
        }

        return null;
    }

    private function resolvedStepCount(): ?int
    {
        if (! $this->relationLoaded('form')) {
            return null;
        }
        $template = $this->form?->workflowTemplate;
        if ($template === null || ! $template->relationLoaded('steps')) {
            return null;
        }
        $count = $template->steps->count();

        return $count > 0 ? $count : null;
    }
}