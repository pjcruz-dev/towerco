<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\ProcurementOne\Models\ProcurementPo;

final class ProcurementPoMigrationService
{
    public function __construct(
        private readonly ProcurementPoSyncService $sync,
    ) {}

    /**
     * @return array{created: int, updated: int, total: int}
     */
    public function migrateFromEApprovalSubmissions(): array
    {
        $created = 0;
        $updated = 0;

        $submissions = EApprovalSubmission::query()
            ->with(['form', 'values.field'])
            ->whereIn('status', [
                EApprovalSubmissionStatus::DRAFT,
                EApprovalSubmissionStatus::PENDING,
                EApprovalSubmissionStatus::APPROVED,
                EApprovalSubmissionStatus::REJECTED,
                EApprovalSubmissionStatus::CANCELLED,
            ])
            ->orderBy('created_at')
            ->get()
            ->filter(fn (EApprovalSubmission $submission) => $this->isPurchaseOrder($submission));

        foreach ($submissions as $submission) {
            $existed = ProcurementPo::query()
                ->where('e_approval_submission_id', $submission->id)
                ->exists();

            $this->sync->syncFromSubmission($submission);

            if ($existed) {
                $updated++;
            } else {
                $created++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'total' => $created + $updated,
        ];
    }

    private function isPurchaseOrder(EApprovalSubmission $submission): bool
    {
        $metadata = is_array($submission->form?->metadata_json) ? $submission->form->metadata_json : [];

        return ($metadata['form_family'] ?? null) === 'purchase_order';
    }
}
