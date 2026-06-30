<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Identity\Models\TenantUser;

/**
 * Publishes approved E-Approval submissions into the controlled document registry.
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

        $submission->loadMissing(['form', 'values.field', 'attachments']);
        $this->sync->syncApprovedSubmission($submission, $actor);
    }
}
