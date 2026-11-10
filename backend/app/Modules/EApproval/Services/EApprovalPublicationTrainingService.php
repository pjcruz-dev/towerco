<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\Documents\Services\ControlledDocumentSyncService;
use App\Modules\Documents\Support\ControlledDocumentMasterLabel;
use App\Modules\Documents\Support\ControlledDocumentPublicationRules;
use App\Modules\EApproval\Models\EApprovalPublicationTraining;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalFormWorkflowOptions;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class EApprovalPublicationTrainingService
{
    public function __construct(
        private readonly EApprovalNotificationDispatcher $mail,
        private readonly EApprovalInAppNotificationService $inApp,
        private readonly EApprovalAuditLogger $audit,
        private readonly ControlledDocumentMasterLabel $labels,
    ) {}

    /**
     * Final approval reached a type that must train before the file is published.
     * Returns true when publication and the file email must wait.
     */
    public function holdIfRequired(EApprovalSubmission $submission): bool
    {
        if ((string) $submission->status !== EApprovalSubmissionStatus::APPROVED) {
            return false;
        }
        if ($this->isReleased($submission)) {
            return false;
        }
        if (! $this->needsTraining($submission)) {
            return false;
        }

        $already = $this->isHolding($submission);
        if (! $this->writeGate($submission, true, null)) {
            return false;
        }
        if (! $already) {
            $this->mail->dispatchToRequestor($submission, 'training_required');
            $requestorId = (string) ($submission->requestor_id ?? '');
            if ($requestorId !== '') {
                $this->inApp->notify(
                    $requestorId,
                    'training_required',
                    (string) $submission->id,
                    __('Approvals are finished. Invite people for mandatory training before :doc is published.', [
                        'doc' => $submission->document_no,
                    ]),
                    submission: $submission,
                );
            }
            $this->audit->log(
                'publication_training_opened',
                (string) $submission->id,
                'Publication waits for mandatory training.',
                entityType: 'e_approval_submission',
                entityLabel: (string) $submission->document_no,
            );
        }

        return true;
    }

    public function isHolding(EApprovalSubmission $submission): bool
    {
        $gate = $this->gate($submission);

        return ($gate['required'] ?? false) === true && empty($gate['released_at']);
    }

    public function blocksSpread(EApprovalSubmission $submission): bool
    {
        if ($this->isReleased($submission)) {
            return false;
        }

        return $this->isHolding($submission) || $this->needsTraining($submission);
    }

    public function awaitsTraining(EApprovalSubmission $submission): bool
    {
        if ($this->isReleased($submission)) {
            return false;
        }

        return $this->isHolding($submission) || $this->needsTraining($submission);
    }

    public function isInvitee(EApprovalSubmission $submission, TenantUser $viewer): bool
    {
        if (! $this->tableReady()) {
            return false;
        }

        return EApprovalPublicationTraining::query()
            ->where('submission_id', $submission->id)
            ->where('user_id', $viewer->id)
            ->exists();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function detail(EApprovalSubmission $submission, ?TenantUser $viewer, bool $canManage = false): ?array
    {
        $this->finishIfReady($submission, $viewer);
        $submission->refresh();

        if (! $this->isHolding($submission)) {
            return null;
        }

        $rows = $this->tableReady()
            ? EApprovalPublicationTraining::query()
                ->with('user:id,name,email')
                ->where('submission_id', $submission->id)
                ->orderBy('created_at')
                ->get()
            : collect();

        $viewerId = $viewer !== null ? (string) $viewer->id : '';
        $canInvite = $viewer !== null && $this->canInvite($submission, $viewer, $canManage);
        $completed = $rows->where('status', 'completed')->count();

        return [
            'holding' => true,
            'can_invite' => $canInvite,
            'completed' => $completed,
            'total' => $rows->count(),
            'invites' => $rows->map(function (EApprovalPublicationTraining $row) use ($viewerId, $canInvite): array {
                $user = $row->user;

                return [
                    'id' => (string) $row->id,
                    'user_id' => (string) $row->user_id,
                    'name' => (string) ($user?->name ?? ''),
                    'email' => (string) ($user?->email ?? ''),
                    'status' => (string) $row->status,
                    'completed_at' => $row->completed_at?->toIso8601String(),
                    'can_complete' => $viewerId !== '' && $viewerId === (string) $row->user_id && $row->status !== 'completed',
                    'can_mark' => $canInvite && $viewerId !== (string) $row->user_id && $row->status !== 'completed',
                    'can_remove' => $canInvite && $row->status !== 'completed',
                ];
            })->values()->all(),
        ];
    }

    /**
     * @param  list<string>  $userIds
     * @return array<string, mixed>|null
     */
    public function invite(EApprovalSubmission $submission, TenantUser $actor, array $userIds, bool $canManage): ?array
    {
        $this->assertHolding($submission);
        if (! $this->canInvite($submission, $actor, $canManage)) {
            throw ValidationException::withMessages([
                'user_ids' => [__('Only the requestor can invite people for this training.')],
            ]);
        }
        if (! $this->tableReady()) {
            throw ValidationException::withMessages([
                'user_ids' => [__('Training is not ready yet. Reload and try again.')],
            ]);
        }

        $ids = array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): string => trim((string) $id),
            $userIds,
        ))));
        $users = TenantUser::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get();
        if ($users->isEmpty()) {
            throw ValidationException::withMessages([
                'user_ids' => [__('Choose at least one active person.')],
            ]);
        }

        $existing = EApprovalPublicationTraining::query()
            ->where('submission_id', $submission->id)
            ->pluck('user_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
        $room = 50 - count($existing);
        $freshIds = [];
        foreach ($users as $user) {
            $id = (string) $user->id;
            if (in_array($id, $existing, true)) {
                continue;
            }
            if ($room <= 0) {
                break;
            }
            EApprovalPublicationTraining::query()->create([
                'submission_id' => $submission->id,
                'user_id' => $id,
                'invited_by' => $actor->id,
                'status' => 'invited',
            ]);
            $freshIds[] = $id;
            $room--;
        }

        if ($freshIds !== []) {
            $this->mail->dispatchTrainingInvites($submission, $freshIds, $actor->name);
            foreach ($freshIds as $id) {
                $this->inApp->notify(
                    $id,
                    'training_invited',
                    (string) $submission->id,
                    __(':name invited you to finish training before :doc is published.', [
                        'name' => $actor->name,
                        'doc' => $submission->document_no,
                    ]),
                    submission: $submission,
                    actor: $actor,
                );
            }
            $this->audit->log(
                'publication_training_invited',
                (string) $submission->id,
                'Invited '.count($freshIds).' people for mandatory training.',
                $actor,
                entityType: 'e_approval_submission',
                entityLabel: (string) $submission->document_no,
            );
        }

        return $this->detail($submission, $actor, $canManage);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function complete(EApprovalSubmission $submission, TenantUser $actor, bool $canManage = false, ?string $inviteId = null): ?array
    {
        $this->assertHolding($submission);
        if (! $this->tableReady()) {
            throw ValidationException::withMessages([
                'training' => [__('Training is not ready yet. Reload and try again.')],
            ]);
        }

        $query = EApprovalPublicationTraining::query()
            ->with('user:id,name')
            ->where('submission_id', $submission->id);
        if ($inviteId !== null && $inviteId !== '') {
            if (! $this->canInvite($submission, $actor, $canManage)) {
                throw ValidationException::withMessages([
                    'training' => [__('Only the requestor or an administrator can record training for someone else.')],
                ]);
            }
            $query->whereKey($inviteId);
        } else {
            $query->where('user_id', $actor->id);
        }

        $row = $query->first();
        if (! $row instanceof EApprovalPublicationTraining) {
            throw ValidationException::withMessages([
                'training' => [__('You are not invited to this training.')],
            ]);
        }
        if ($row->status !== 'completed') {
            $row->status = 'completed';
            $row->completed_at = now();
            $row->save();
            $person = trim((string) ($row->user?->name ?? ''));
            $forSomeoneElse = (string) $row->user_id !== (string) $actor->id;
            $this->audit->log(
                'publication_training_completed',
                (string) $submission->id,
                $forSomeoneElse
                    ? $actor->name.' recorded training complete for '.($person !== '' ? $person : 'an invited person').'.'
                    : $actor->name.' completed mandatory training.',
                $actor,
                entityType: 'e_approval_submission',
                entityLabel: (string) $submission->document_no,
            );
        }
        $this->releaseIfReady($submission, $actor);

        return $this->detail($submission->fresh() ?? $submission, $actor, $canManage);
    }

    public function finishIfReady(EApprovalSubmission $submission, ?TenantUser $actor = null): void
    {
        if (! $this->isHolding($submission)) {
            return;
        }

        $actor ??= TenantUser::query()->find($submission->requestor_id);
        if (! $actor instanceof TenantUser) {
            return;
        }

        try {
            $this->releaseIfReady($submission, $actor);
        } catch (\Throwable $exception) {
            Log::error('e_approval.publication_training_release_failed', [
                'submission_id' => (string) $submission->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function remove(EApprovalSubmission $submission, TenantUser $actor, string $inviteId, bool $canManage): ?array
    {
        $this->assertHolding($submission);
        if (! $this->canInvite($submission, $actor, $canManage)) {
            throw ValidationException::withMessages([
                'training' => [__('Only the requestor can change this training list.')],
            ]);
        }

        $row = EApprovalPublicationTraining::query()
            ->where('submission_id', $submission->id)
            ->whereKey($inviteId)
            ->first();
        if (! $row instanceof EApprovalPublicationTraining || $row->status === 'completed') {
            throw ValidationException::withMessages([
                'training' => [__('That person has already finished, or is not on the list.')],
            ]);
        }
        $row->delete();
        $this->releaseIfReady($submission, $actor);

        return $this->detail($submission->fresh() ?? $submission, $actor, $canManage);
    }

    private function releaseIfReady(EApprovalSubmission $submission, TenantUser $actor): void
    {
        if (! $this->isHolding($submission) || ! $this->tableReady()) {
            return;
        }

        $rows = EApprovalPublicationTraining::query()
            ->where('submission_id', $submission->id)
            ->get();
        if ($rows->isEmpty() || $rows->contains(static fn (EApprovalPublicationTraining $row): bool => $row->status !== 'completed')) {
            return;
        }

        $fresh = $submission->fresh() ?? $submission;
        $this->rememberTrainingRecipients($fresh, $rows->pluck('user_id')->map(
            static fn (mixed $id): string => trim((string) $id),
        )->filter(static fn (string $id): bool => $id !== '')->values()->all());
        app(ControlledDocumentSyncService::class)->syncApprovedSubmission($fresh, $actor);
        if (! $this->writeGate($fresh, true, now()->toIso8601String())) {
            return;
        }
        $released = $fresh->fresh() ?? $fresh;
        app(EApprovalApprovedFileDistributionService::class)->send($released);
        $this->mail->dispatchToRequestor($released, 'publication_released', $actor->name);
        $requestorId = (string) ($released->requestor_id ?? '');
        if ($requestorId !== '') {
            $this->inApp->notify(
                $requestorId,
                'publication_released',
                (string) $released->id,
                __('Training is complete. :doc is published.', ['doc' => $released->document_no]),
                submission: $released,
                actor: $actor,
            );
        }
        $this->audit->log(
            'publication_released',
            (string) $released->id,
            'Mandatory training finished. The document is published.',
            $actor,
            entityType: 'e_approval_submission',
            entityLabel: (string) $released->document_no,
        );
    }

    private function assertHolding(EApprovalSubmission $submission): void
    {
        if (! $this->isHolding($submission)) {
            throw ValidationException::withMessages([
                'training' => [__('Training is not open on this request.')],
            ]);
        }
    }

    private function canInvite(EApprovalSubmission $submission, TenantUser $actor, bool $canManage): bool
    {
        return $canManage || (string) $submission->requestor_id === (string) $actor->id;
    }

    private function needsTraining(EApprovalSubmission $submission): bool
    {
        $submission->loadMissing(['form', 'values.field']);
        $metadata = is_array($submission->form?->metadata_json) ? $submission->form->metadata_json : [];
        if (! EApprovalFormWorkflowOptions::mandatoryTrainingEnabled($metadata)) {
            return false;
        }

        $field = EApprovalFormWorkflowOptions::mandatoryTrainingField($metadata);
        $values = EApprovalFormWorkflowOptions::mandatoryTrainingValues($metadata);
        if ($field === '' || $values === []) {
            return true;
        }

        $raw = $this->valueByFieldName($submission, $field);
        $label = $this->choiceLabel($submission, $field, $raw);

        return ControlledDocumentPublicationRules::matchesListedType($raw, $label, $values);
    }

    private function valueByFieldName(EApprovalSubmission $submission, string $fieldName): string
    {
        foreach ($submission->values as $row) {
            if ((string) ($row->field?->name ?? '') !== $fieldName) {
                continue;
            }
            $text = trim((string) ($row->value ?? ''));
            if ($text !== '' && str_starts_with($text, '"')) {
                $decoded = json_decode($text, true);
                if (is_string($decoded)) {
                    return trim($decoded);
                }
            }

            return $text;
        }

        return '';
    }

    private function choiceLabel(EApprovalSubmission $submission, string $fieldName, string $raw): ?string
    {
        if ($raw === '') {
            return null;
        }

        $field = $submission->values
            ->first(static fn ($row): bool => (string) ($row->field?->name ?? '') === $fieldName)
            ?->field;
        $options = is_array($field?->options) ? $field->options : [];
        $key = trim((string) ($options['master_data_key'] ?? ''));
        if ($key === '') {
            return $fieldName === 'document_type' ? $this->labels->documentType($raw) : null;
        }

        return $this->labels->expand($key, $raw);
    }

    private function isReleased(EApprovalSubmission $submission): bool
    {
        return ! empty($this->gate($submission)['released_at']);
    }

    /**
     * @return array{required?: bool, released_at?: string|null}
     */
    private function gate(EApprovalSubmission $submission): array
    {
        $decoded = $this->snapshot($submission);
        $gate = $decoded['publication_training'] ?? null;

        return is_array($gate) ? $gate : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(EApprovalSubmission $submission): array
    {
        $raw = $submission->workflow_snapshot_json;
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

    private function writeGate(EApprovalSubmission $submission, bool $required, ?string $releasedAt): bool
    {
        $raw = $submission->workflow_snapshot_json;
        if (is_string($raw) && trim($raw) !== '') {
            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                return false;
            }
            if (! is_array($decoded)) {
                return false;
            }
        } else {
            $decoded = [];
        }

        $decoded['publication_training'] = [
            'required' => $required,
            'released_at' => $releasedAt,
        ];
        $submission->workflow_snapshot_json = json_encode($decoded, JSON_THROW_ON_ERROR);
        $submission->save();

        return true;
    }

    /**
     * Training invitees are who receive the current-file link.
     *
     * @param  list<string>  $userIds
     */
    private function rememberTrainingRecipients(EApprovalSubmission $submission, array $userIds): void
    {
        if ($userIds === []) {
            return;
        }

        $decoded = $this->snapshot($submission);
        $decoded['approved_file_audience'] = 'selected_users';
        $decoded['approved_file_recipient_ids'] = array_values(array_unique($userIds));
        $submission->workflow_snapshot_json = json_encode($decoded, JSON_THROW_ON_ERROR);
        $submission->save();
    }

    private function tableReady(): bool
    {
        return Schema::connection('tenant')->hasTable('e_approval_publication_trainings');
    }
}
