<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalAttachment;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalFormValue;
use App\Modules\EApproval\Models\EApprovalPublicFormLink;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalSubmissionSource;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class EApprovalPublicSubmissionService
{
    public function __construct(
        private readonly EApprovalPublicFormLinkService $links,
        private readonly EApprovalDocumentSequenceService $documentNumbers,
        private readonly EApprovalSubmissionSnapshotService $snapshots,
        private readonly SubmissionWorkflowService $workflow,
        private readonly EApprovalSubmissionValuesValidator $valuesValidator,
        private readonly EApprovalAuditLogger $audit,
        private readonly EApprovalFileStorageService $files,
        private readonly EApprovalSubmissionAttachmentValidator $attachmentValidator,
        private readonly EApprovalPlanFeaturesService $planFeatures,
        private readonly EApprovalInAppNotificationService $inApp,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     * @return array{
     *     submission_id: string,
     *     document_no: string,
     *     upload_token: string,
     *     upload_token_expires_at: string
     * }
     */
    public function create(
        EApprovalPublicFormLink $link,
        array $values,
        string $submitterName,
        string $submitterEmail,
        ?string $clientIp,
        ?string $userAgent,
    ): array {
        $form = $link->form;
        if ($form === null) {
            throw ValidationException::withMessages(['form' => [__('Form not found.')]]);
        }

        if ($form->accepts_new_submissions === false) {
            throw ValidationException::withMessages(['form' => [__('This form is retired and no longer accepts new submissions.')]]);
        }

        /** @var TenantUser $sponsor */
        $sponsor = $link->sponsor ?? TenantUser::query()->findOrFail($link->sponsor_user_id);

        $this->valuesValidator->validate($form, $values);

        $uploadPlain = Str::random(40);
        $uploadMinutes = (int) config('e_approval.public_links.upload_token_minutes', 60);

        return DB::connection('tenant')->transaction(function () use (
            $link,
            $form,
            $values,
            $submitterName,
            $submitterEmail,
            $sponsor,
            $clientIp,
            $userAgent,
            $uploadPlain,
            $uploadMinutes,
        ): array {
            $snapshot = $this->snapshots->capture($form);
            $documentNo = $this->documentNumbers->nextDocumentNumber($form, $values);

            $submission = EApprovalSubmission::query()->create([
                'id' => (string) Str::uuid(),
                'document_no' => $documentNo,
                'form_id' => $form->id,
                'requestor_id' => $sponsor->id,
                'submission_source' => EApprovalSubmissionSource::EXTERNAL,
                'external_submitter_name' => $submitterName,
                'external_submitter_email' => $submitterEmail,
                'public_link_id' => $link->id,
                'external_upload_token_hash' => hash('sha256', $uploadPlain),
                'external_upload_token_expires_at' => now()->addMinutes($uploadMinutes),
                'external_client_ip' => $clientIp,
                'external_user_agent' => $userAgent !== null ? Str::limit($userAgent, 512, '') : null,
                'status' => EApprovalSubmissionStatus::PENDING,
                'current_step' => 1,
                'schema_snapshot_json' => $snapshot['schema_snapshot_json'],
                'workflow_snapshot_json' => $snapshot['workflow_snapshot_json'],
                'workflow_version_id' => $snapshot['workflow_version_id'],
            ]);

            $this->persistValues($submission, $form, $values);
            $this->workflow->initiateWorkflow($submission, $form, $values);
            $this->audit->log(
                'public_submission_created',
                $submission->id,
                $documentNo.' · '.$submitterEmail,
                $sponsor,
            );

            $this->inApp->notify(
                (string) $sponsor->id,
                'public_submission_received',
                $submission->id,
                __('External submission :doc from :name.', [
                    'doc' => $documentNo,
                    'name' => $submitterName,
                ]),
                submission: $submission->fresh(),
            );

            $this->links->incrementSubmissions($link);

            return [
                'submission_id' => (string) $submission->id,
                'document_no' => $documentNo,
                'upload_token' => $uploadPlain,
                'upload_token_expires_at' => $submission->external_upload_token_expires_at?->toIso8601String() ?? '',
            ];
        });
    }

    public function storeAttachment(
        EApprovalPublicFormLink $link,
        EApprovalSubmission $submission,
        string $uploadToken,
        UploadedFile $file,
        ?string $fieldName,
    ): EApprovalAttachment {
        $this->assertUploadSession($link, $submission, $uploadToken);
        $this->planFeatures->assertCanUploadAttachment();
        $this->attachmentValidator->assertCanStore($submission, $file, $fieldName);

        return $this->files->store($submission, $file, $fieldName);
    }

    public function assertUploadSession(
        EApprovalPublicFormLink $link,
        EApprovalSubmission $submission,
        string $uploadToken,
    ): void {
        if ($submission->submission_source !== EApprovalSubmissionSource::EXTERNAL
            || (string) $submission->public_link_id !== (string) $link->id) {
            throw ValidationException::withMessages([
                'submission' => [__('Invalid upload session.')],
            ]);
        }

        $hash = $submission->external_upload_token_hash;
        $expires = $submission->external_upload_token_expires_at;

        if ($hash === null || $expires === null || $expires->isPast()) {
            throw ValidationException::withMessages([
                'upload_token' => [__('The upload window has expired. Submit the form again if needed.')],
            ]);
        }

        if (! hash_equals($hash, hash('sha256', $uploadToken))) {
            throw ValidationException::withMessages([
                'upload_token' => [__('Invalid upload token.')],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function persistValues(EApprovalSubmission $submission, EApprovalForm $form, array $values): void
    {
        $form->loadMissing('fields');
        $byName = $form->fields->keyBy('name');

        foreach ($values as $name => $raw) {
            $field = $byName->get($name);
            if ($field === null) {
                continue;
            }

            EApprovalFormValue::query()->create([
                'id' => (string) Str::uuid(),
                'submission_id' => $submission->id,
                'field_id' => $field->id,
                'value' => is_scalar($raw) || $raw === null ? (string) $raw : json_encode($raw, JSON_THROW_ON_ERROR),
            ]);
        }
    }
}
