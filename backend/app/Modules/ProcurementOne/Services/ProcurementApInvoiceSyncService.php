<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Models\ProcurementApInvoice;
use App\Modules\ProcurementOne\Support\ProcurementApInvoiceStatus;
use App\Modules\ProcurementOne\Support\ProcurementDocumentType;
use Illuminate\Support\Facades\DB;

final class ProcurementApInvoiceSyncService
{
    public function __construct(
        private readonly ProcurementDocumentEventDispatcher $events,
    ) {}

    public function syncFromSubmission(EApprovalSubmission $submission, ?TenantUser $actor = null): ?ProcurementApInvoice
    {
        $submission->loadMissing(['form', 'values.field', 'requestor']);

        if (! $this->isApInvoiceSubmission($submission)) {
            return null;
        }

        $existing = ProcurementApInvoice::query()
            ->where('e_approval_submission_id', (string) $submission->id)
            ->first();

        if ($existing !== null && in_array((string) $existing->status, [
            ProcurementApInvoiceStatus::VOIDED,
            ProcurementApInvoiceStatus::CANCELLED,
        ], true)) {
            return $existing->load(['lines', 'purchaseOrder', 'requestor']);
        }

        return DB::connection('tenant')->transaction(function () use ($submission, $existing, $actor): ?ProcurementApInvoice {
            if ($existing === null) {
                return null;
            }

            $wasApproved = (string) $existing->status === ProcurementApInvoiceStatus::APPROVED;
            $previousStatus = (string) $existing->status;
            $mappedStatus = ProcurementApInvoiceStatus::fromEApprovalStatus((string) $submission->status);

            $existing->document_no = $submission->document_no ?? $existing->document_no;
            $existing->status = $mappedStatus;

            if ($submission->status === EApprovalSubmissionStatus::PENDING && $existing->submitted_at === null) {
                $existing->submitted_at = $submission->created_at;
            }

            if ($mappedStatus === ProcurementApInvoiceStatus::APPROVED) {
                $existing->approved_at = $existing->approved_at ?? now();
            }

            if ($mappedStatus === ProcurementApInvoiceStatus::CANCELLED) {
                $existing->cancelled_at = now();
            }

            $existing->save();

            if ($mappedStatus === ProcurementApInvoiceStatus::APPROVED && ! $wasApproved) {
                $this->events->approved(
                    ProcurementDocumentType::AP_INVOICE,
                    (string) $existing->id,
                    $existing->document_no,
                    $actor !== null ? (string) $actor->id : null,
                    ['e_approval_submission_id' => $submission->id],
                );
            }

            return $existing->refresh()->load(['lines', 'purchaseOrder', 'requestor']);
        });
    }

    private function isApInvoiceSubmission(EApprovalSubmission $submission): bool
    {
        $metadata = is_array($submission->form?->metadata_json) ? $submission->form->metadata_json : [];

        return ($metadata['form_family'] ?? null) === 'ap_invoice';
    }
}
