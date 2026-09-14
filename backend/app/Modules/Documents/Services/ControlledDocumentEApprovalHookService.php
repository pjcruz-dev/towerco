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

    public function afterSubmissionMutation(EApprovalSubmission $submission, ?TenantUser $actor = null): void
    {
        if ((string) $submission->status !== EApprovalSubmissionStatus::APPROVED) {
            return;
        }

        if (! Schema::connection('tenant')->hasTable('controlled_document_revisions')
            || ! Schema::connection('tenant')->hasTable('controlled_documents')) {
            Log::warning('Controlled document sync skipped: registry tables missing', [
                'submission_id' => (string) $submission->id,
            ]);

            return;
        }

        try {
            $submission->loadMissing(['form', 'values.field', 'attachments']);
            $this->sync->syncApprovedSubmission($submission, $actor);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            // Never roll back a successful E-Forms approval for registry sync failures.
            Log::error('Controlled document sync failed after approval', [
                'submission_id' => (string) $submission->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
