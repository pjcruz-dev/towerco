<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\Models\ControlledDocument;
use App\Modules\Documents\Support\ControlledDocumentStatus;
use App\Modules\Documents\Support\ControlledDocumentSyncConfig;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalFileStorageService;
use App\Modules\EApproval\Services\EApprovalSubmissionService;
use App\Modules\EApproval\Support\EApprovalApprovalStatus;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Identity\Models\TenantUser;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Opens one ISO Approval for a pending import row and leaves published rows alone.
 */
final class ControlledDocumentPendingImportService
{
    public function __construct(
        private readonly ControlledDocumentFormResolverService $forms,
        private readonly EApprovalSubmissionService $submissions,
        private readonly EApprovalFileStorageService $files,
    ) {}

    /**
     * @param  array<string, string|null>  $data
     */
    public function open(array $data, TenantUser $actor, ?UploadedFile $file): string
    {
        $code = trim((string) ($data['document_code'] ?? ''));
        $approver = $this->approver($data);
        $form = $this->forms->resolvePublishedDefaultFormFor($actor);
        if (! $form instanceof EApprovalForm) {
            throw ValidationException::withMessages([
                'form' => [__('Publish an ISO Approval form you are allowed to start before importing pending rows.')],
            ]);
        }

        $form->loadMissing(['fields', 'workflowTemplate.steps']);
        $sync = is_array($form->metadata_json)
            ? ControlledDocumentSyncConfig::parse($form->metadata_json)
            : null;
        if ($sync === null) {
            throw ValidationException::withMessages([
                'form' => [__('The ISO Approval form is not set to update the register.')],
            ]);
        }

        $existingSubmission = EApprovalSubmission::query()
            ->where('status', EApprovalSubmissionStatus::PENDING)
            ->where(function ($query) use ($code): void {
                $query->where('document_no', $code)
                    ->orWhere('document_no', 'like', $code.'-R%');
            })
            ->exists();
        if ($existingSubmission) {
            return 'skipped';
        }

        $document = ControlledDocument::query()->where('document_code', $code)->first();
        if ($document instanceof ControlledDocument && $document->status === ControlledDocumentStatus::OBSOLETE) {
            throw ValidationException::withMessages([
                'document_code' => [__('This document is obsolete.')],
            ]);
        }
        if ($document instanceof ControlledDocument && $document->status === ControlledDocumentStatus::PENDING) {
            return 'skipped';
        }

        $values = $this->values($data, $sync, $approver, $form);
        $counts = [];
        if ($file instanceof UploadedFile) {
            $counts[$sync->attachmentField] = 1;
        }

        $submission = $this->submissions->create(
            (string) $form->id,
            $values,
            $actor,
            null,
            (string) $approver->id,
            $counts,
        );

        $this->backdate($submission, $data);

        if ($file instanceof UploadedFile) {
            $this->files->store($submission, $file, $sync->attachmentField);
        }

        return $document instanceof ControlledDocument ? 'updated' : 'created';
    }

    /**
     * @param  array<string, string|null>  $data
     */
    public function problem(array $data): ?string
    {
        if (! $this->isPending($data)) {
            return null;
        }

        $email = strtolower(trim((string) ($data['approver_email'] ?? '')));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return __('approver_email is required for a pending row.');
        }

        if ($this->findApprover($email) === null) {
            return __('No active user matches :email.', ['email' => $email]);
        }

        $since = trim((string) ($data['pending_since'] ?? ''));
        if ($since !== '') {
            try {
                $date = Carbon::parse($since)->startOfDay();
            } catch (\Throwable) {
                return __('pending_since must be a date.');
            }
            if ($date->isFuture()) {
                return __('pending_since cannot be in the future.');
            }
        }

        return null;
    }

    /**
     * @param  array<string, string|null>  $data
     */
    public function isPending(array $data): bool
    {
        return strtolower(trim((string) ($data['status'] ?? ''))) === 'pending';
    }

    /**
     * @param  array<string, string|null>  $data
     */
    private function approver(array $data): TenantUser
    {
        $email = strtolower(trim((string) ($data['approver_email'] ?? '')));
        $approver = $this->findApprover($email);
        if (! $approver instanceof TenantUser) {
            throw ValidationException::withMessages([
                'approver_email' => [__('No active user matches :email.', ['email' => $email])],
            ]);
        }

        return $approver;
    }

    private function findApprover(string $email): ?TenantUser
    {
        if ($email === '') {
            return null;
        }

        return TenantUser::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('is_active', true)
            ->first();
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array<string, string>
     */
    private function values(array $data, ControlledDocumentSyncConfig $sync, TenantUser $approver, EApprovalForm $form): array
    {
        $map = $sync->fieldMap;
        $values = [];
        $codeField = $sync->documentCodeField ?? 'document_code';
        $values[$codeField] = trim((string) ($data['document_code'] ?? ''));
        $this->put($values, $map['title'] ?? 'title', $data['title'] ?? null);
        $this->put($values, $map['document_type'] ?? 'document_type', $data['document_type'] ?? null);
        $this->put($values, $map['department'] ?? 'department', $data['department'] ?? null);
        $this->put($values, $map['revision_number'] ?? 'revision_number', $data['revision_number'] ?? null);
        $this->put($values, $map['effective_date'] ?? 'effective_date', $data['effective_date'] ?? null);
        $this->put($values, $map['next_review_date'] ?? 'next_review_date', $data['next_review_date'] ?? null);
        $this->put($values, $map['change_summary'] ?? 'change_summary', $data['change_summary'] ?? null);

        foreach ($form->workflowTemplate?->steps ?? [] as $step) {
            if ((string) $step->approver_type === 'field' && trim((string) $step->approver_id) !== '') {
                $values[(string) $step->approver_id] = (string) $approver->id;
            }
        }

        return $values;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function put(array &$values, string $field, ?string $value): void
    {
        $trimmed = trim((string) $value);
        if ($trimmed !== '') {
            $values[$field] = $trimmed;
        }
    }

    /**
     * @param  array<string, string|null>  $data
     */
    private function backdate(EApprovalSubmission $submission, array $data): void
    {
        $since = trim((string) ($data['pending_since'] ?? ''));
        if ($since === '') {
            return;
        }

        try {
            $date = Carbon::parse($since)->startOfDay();
        } catch (\Throwable) {
            return;
        }

        EApprovalRequestApproval::query()
            ->where('submission_id', $submission->id)
            ->where('status', EApprovalApprovalStatus::PENDING)
            ->get()
            ->each(function (EApprovalRequestApproval $approval) use ($date): void {
                $approval->created_at = $date;
                $approval->save();
            });
    }
}
