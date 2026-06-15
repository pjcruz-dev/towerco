<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalDocumentLink;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalFormValue;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Models\EApprovalSubmissionFollowup;
use App\Modules\EApproval\Support\EApprovalApprovalStatus;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class EApprovalSubmissionLifecycleService
{
    public function __construct(
        private readonly SubmissionWorkflowService $workflow,
        private readonly EApprovalDocumentControlService $documentControl,
        private readonly EApprovalDelegationService $delegations,
        private readonly EApprovalSettingsService $settings,
        private readonly EApprovalAuditLogger $audit,
        private readonly EApprovalInAppNotificationService $inApp,
        private readonly EApprovalNotificationDispatcher $mail,
        private readonly EApprovalCommentService $comments,
        private readonly EApprovalDocumentLinkService $documentLinks,
    ) {}

    public function requestRevision(EApprovalSubmission $submission, string $remarks, TenantUser $actor): void
    {
        $remarks = trim($remarks);
        if (strlen($remarks) < 5) {
            throw ValidationException::withMessages([
                'remarks' => [__('A detailed remark is mandatory for returning a protocol for revision.')],
            ]);
        }

        if (! $this->canApproverActOnSubmission($submission, $actor)) {
            throw ValidationException::withMessages([
                'submission' => [__('You are not authorized to request revision for this submission.')],
            ]);
        }

        DB::connection('tenant')->transaction(function () use ($submission, $remarks, $actor): void {
            EApprovalRequestApproval::query()->where('submission_id', $submission->id)->delete();
            $submission->status = EApprovalSubmissionStatus::RETURNED;
            $submission->current_step = 0;
            $submission->save();

            $this->audit->log('revision_requested', $submission->id, $remarks, $actor);
            $this->inApp->notify(
                (string) $submission->requestor_id,
                'returned',
                $submission->id,
                __('Your request :doc was returned for revision.', ['doc' => $submission->document_no]),
                submission: $submission,
                actor: $actor,
                bodyPreview: $remarks !== '' ? $remarks : null,
            );
            $this->mail->dispatchToRequestor($submission, 'returned', $actor->name);
        });
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function dcfResubmit(EApprovalSubmission $submission, array $values, TenantUser $actor): EApprovalSubmission
    {
        if ((string) $submission->requestor_id !== (string) $actor->id && ! $actor->can('e_approval:forms:manage')) {
            throw ValidationException::withMessages([
                'submission' => [__('You can only submit document control on your own submission.')],
            ]);
        }

        if ($submission->status !== EApprovalSubmissionStatus::AWAITING_DCF) {
            throw ValidationException::withMessages([
                'status' => [__('Submission is not awaiting document control.')],
            ]);
        }

        $form = EApprovalForm::query()->with('fields')->findOrFail($submission->form_id);
        $gate = $this->documentControl->assertConfiguredForDcfResubmit($form);

        return DB::connection('tenant')->transaction(function () use ($submission, $form, $values, $actor, $gate) {
            $this->mergeValues($submission, $form, $values);

            $submission->status = EApprovalSubmissionStatus::PENDING;
            $submission->current_step = $gate->afterStepOrder;
            $submission->save();

            $hasMore = $this->workflow->triggerNextStep($submission, $gate->afterStepOrder);
            $submission->refresh();

            if (! $hasMore) {
                $stillPending = $submission->approvals()->where('status', EApprovalApprovalStatus::PENDING)->exists();
                if (! $stillPending) {
                    $submission->status = EApprovalSubmissionStatus::APPROVED;
                    $submission->save();
                    $this->inApp->notify(
                        (string) $submission->requestor_id,
                        'approved',
                        $submission->id,
                        __('Your request :doc was approved.', ['doc' => $submission->document_no]),
                        submission: $submission,
                        actor: $actor,
                    );
                    $this->mail->dispatchToRequestor($submission, 'approved', $actor->name);
                }
            }

            $this->audit->log('dcf_submitted', $submission->id, 'Document control submitted', $actor);

            return $submission->fresh(['form', 'requestor', 'values.field', 'approvals.step', 'approvals.approver']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function manualFollowUp(EApprovalSubmission $submission, ?string $note, TenantUser $actor): array
    {
        if ((string) $submission->requestor_id !== (string) $actor->id) {
            throw ValidationException::withMessages([
                'submission' => [__('Only the requestor can send a manual follow-up.')],
            ]);
        }

        if ($submission->status !== EApprovalSubmissionStatus::PENDING) {
            throw ValidationException::withMessages([
                'status' => [__('Manual follow-up is available only while request is pending.')],
            ]);
        }

        $pending = EApprovalRequestApproval::query()
            ->where('submission_id', $submission->id)
            ->where('status', EApprovalApprovalStatus::PENDING)
            ->orderBy('created_at')
            ->first();

        if ($pending === null || $pending->approver_id === null) {
            throw ValidationException::withMessages([
                'submission' => [__('No pending approver found.')],
            ]);
        }

        $cooldown = $this->settings->getInt(EApprovalSettingsService::MANUAL_FOLLOW_UP_COOLDOWN_MINUTES, 720);

        $latest = EApprovalSubmissionFollowup::query()
            ->where('submission_id', $submission->id)
            ->orderByDesc('created_at')
            ->first();

        if ($latest?->created_at !== null) {
            $nextAllowed = $latest->created_at->copy()->addMinutes($cooldown);
            if (now()->lt($nextAllowed)) {
                throw ValidationException::withMessages([
                    'cooldown' => [__('Please wait before sending another follow-up.')],
                ]);
            }
        }

        $note = $note !== null ? trim(mb_substr($note, 0, 2000)) : '';

        EApprovalSubmissionFollowup::query()->create([
            'id' => (string) Str::uuid(),
            'submission_id' => $submission->id,
            'requestor_id' => $actor->id,
            'approver_id' => $pending->approver_id,
            'message' => $note !== '' ? $note : null,
        ]);

        $commentText = $note !== ''
            ? "[Manual follow-up] {$note}"
            : '[Manual follow-up] Requestor sent a reminder to the current approver.';
        $this->comments->add($submission, $commentText, $actor, notifyStakeholders: false);

        $this->inApp->notify(
            (string) $pending->approver_id,
            'manual_follow_up',
            $submission->id,
            __(':name sent a manual follow-up for :doc.', ['name' => $actor->name, 'doc' => $submission->document_no]),
            submission: $submission,
            actor: $actor,
            bodyPreview: $note !== '' ? $note : null,
        );

        $this->audit->log('submission_manual_follow_up', $submission->id, "To approver {$pending->approver_id}", $actor);

        return ['ok' => true, 'cooldown_minutes' => $cooldown];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listDocumentLinks(EApprovalSubmission $submission): array
    {
        return $this->documentLinks->listOutgoing($submission);
    }

    public function createDocumentLink(
        EApprovalSubmission $source,
        string $targetSubmissionId,
        string $linkType,
        TenantUser $actor,
    ): EApprovalDocumentLink {
        return $this->documentLinks->create($source, $targetSubmissionId, $linkType, $actor);
    }

    public function deleteDocumentLink(EApprovalDocumentLink $link, TenantUser $actor): void
    {
        $this->documentLinks->delete($link, $actor);
    }

    /**
     * @return array<string, mixed>
     */
    public function manualFollowUpMeta(EApprovalSubmission $submission): array
    {
        $cooldown = $this->settings->getInt(EApprovalSettingsService::MANUAL_FOLLOW_UP_COOLDOWN_MINUTES, 720);
        $latest = EApprovalSubmissionFollowup::query()
            ->where('submission_id', $submission->id)
            ->orderByDesc('created_at')
            ->first();

        $nextAllowed = null;
        if ($latest?->created_at !== null) {
            $nextAllowed = $latest->created_at->copy()->addMinutes($cooldown);
        }

        return [
            'manual_follow_up_cooldown_minutes' => $cooldown,
            'manual_follow_up_last_at' => $latest?->created_at?->toIso8601String(),
            'manual_follow_up_next_allowed_at' => $nextAllowed?->isFuture() ? $nextAllowed->toIso8601String() : null,
        ];
    }

    private function canApproverActOnSubmission(EApprovalSubmission $submission, TenantUser $actor): bool
    {
        if ($actor->can('e_approval:forms:manage')) {
            return true;
        }

        $pendingApprovers = EApprovalRequestApproval::query()
            ->where('submission_id', $submission->id)
            ->where('status', EApprovalApprovalStatus::PENDING)
            ->pluck('approver_id');

        foreach ($pendingApprovers as $approverId) {
            if ($approverId && $this->delegations->canActForApprover($actor, (string) $approverId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function mergeValues(EApprovalSubmission $submission, EApprovalForm $form, array $values): void
    {
        $fieldsByName = $form->fields->keyBy('name');

        foreach ($values as $key => $raw) {
            $field = $fieldsByName->get($key) ?? $form->fields->firstWhere('id', $key);
            if ($field === null) {
                continue;
            }

            $stored = is_scalar($raw) ? (string) $raw : json_encode($raw, JSON_THROW_ON_ERROR);

            EApprovalFormValue::query()
                ->where('submission_id', $submission->id)
                ->where('field_id', $field->id)
                ->delete();

            EApprovalFormValue::query()->create([
                'id' => (string) Str::uuid(),
                'submission_id' => $submission->id,
                'field_id' => $field->id,
                'value' => $stored,
            ]);
        }
    }
}
