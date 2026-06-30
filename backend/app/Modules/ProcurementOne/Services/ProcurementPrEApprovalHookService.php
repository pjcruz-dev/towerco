<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\Identity\Models\TenantUser;

/**
 * Keeps procurement PR/PO projections aligned with E-Approval submission lifecycle events.
 */
final class ProcurementPrEApprovalHookService
{
    public function __construct(
        private readonly ProcurementPrSyncService $prSync,
        private readonly ProcurementPoSyncService $poSync,
        private readonly ProcurementApInvoiceSyncService $apInvoiceSync,
    ) {}

    public function afterSubmissionMutation(EApprovalSubmission $submission, ?TenantUser $actor = null): void
    {
        $submission->loadMissing(['form', 'values.field', 'requestor']);
        $this->apInvoiceSync->syncFromSubmission($submission, $actor);
        $this->poSync->syncFromSubmission($submission, $actor);
        $this->prSync->syncFromSubmission($submission, $actor);

        if ($submission->parent_submission_id !== null) {
            $parent = EApprovalSubmission::query()
                ->with(['form', 'values.field', 'requestor'])
                ->find($submission->parent_submission_id);

            if ($parent !== null) {
                $this->prSync->syncFromSubmission($parent, $actor);
            }
        }
    }
}
