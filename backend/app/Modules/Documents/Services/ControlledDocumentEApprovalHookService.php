<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Publishes approved E-Forms submissions into the controlled document registry.
 */
final class ControlledDocumentEApprovalHookService
{
    public function __construct(
        private readonly ControlledDocumentSyncService $sync,
    ) {}

    public function afterSubmissionOpened(EApprovalSubmission $submission, ?TenantUser $actor = null): void
    {
        if ((string) $submission->status !== EApprovalSubmissionStatus::PENDING) {
            return;
        }

        $this->runSync($submission, function () use ($submission, $actor): void {
            $submission->loadMissing(['form', 'values.field', 'attachments']);
            $this->sync->openPending($submission, $actor);
        });
    }

    public function afterAttachmentStored(EApprovalSubmission $submission): void
    {
        $this->runSync($submission, function () use ($submission): void {
            $this->sync->attachPendingFile($submission);
        });
    }

    public function afterSubmissionClosed(EApprovalSubmission $submission): void
    {
        $this->runSync($submission, function () use ($submission): void {
            $this->sync->releasePending($submission);
        });
    }

    public function afterSubmissionMutation(EApprovalSubmission $submission, ?TenantUser $actor = null): void
    {
        if ((string) $submission->status !== EApprovalSubmissionStatus::APPROVED) {
            return;
        }

        $this->runSync($submission, function () use ($submission, $actor): void {
            $submission->loadMissing(['form', 'values.field', 'attachments']);
            $this->sync->syncApprovedSubmission($submission, $actor);
        });
    }

    private function runSync(EApprovalSubmission $submission, \Closure $callback): void
    {
        if (! Schema::connection('tenant')->hasTable('controlled_document_revisions')
            || ! Schema::connection('tenant')->hasTable('controlled_documents')) {
            Log::warning('Controlled document sync skipped: registry tables missing', [
                'submission_id' => (string) $submission->id,
            ]);

            return;
        }

        try {
            $callback();
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Controlled document sync failed', [
                'submission_id' => (string) $submission->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
