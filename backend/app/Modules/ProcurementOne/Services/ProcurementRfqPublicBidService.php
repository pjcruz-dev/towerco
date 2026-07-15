<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Models\ProcurementRfq;
use App\Modules\ProcurementOne\Models\ProcurementRfqBid;
use App\Modules\ProcurementOne\Models\ProcurementRfqVendor;
use App\Modules\ProcurementOne\Support\ProcurementRfqBidStatus;
use App\Modules\ProcurementOne\Support\ProcurementRfqStatus;
use App\Modules\ProcurementOne\Support\ProcurementQuoteBasis;
use Illuminate\Validation\ValidationException;

final class ProcurementRfqPublicBidService
{
    public function __construct(
        private readonly ProcurementRfqVendorInvitationService $invitations,
        private readonly ProcurementRfqBidService $bids,
        private readonly ProcurementRfqRegistryService $registry,
        private readonly ProcurementRfqPrLineSyncService $prLineSync,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function quotePayload(ProcurementRfqVendor $invitation): array
    {
        $rfq = $this->invitations->resolveRfq($invitation);
        $this->invitations->markOpened($invitation);

        $rfq = $this->prLineSync->syncIfApplicable($rfq);

        $invitation->loadMissing(['vendor', 'rfq.lines']);
        $rfq->loadMissing('lines');

        $existingBid = $rfq->bids()
            ->where('vendor_id', $invitation->vendor_id)
            ->first();

        $submissionBlockedReason = $this->submissionBlockedReason($rfq, $existingBid);
        $canSubmit = $submissionBlockedReason === null;

        return [
            'rfq' => [
                'id' => (string) $rfq->id,
                'document_no' => $rfq->document_no,
                'title' => $rfq->title,
                'description' => $rfq->description,
                'status' => $rfq->status,
                'status_label' => ProcurementRfqStatus::label((string) $rfq->status),
                'currency_code' => $rfq->currency_code,
                'bidding_opens_at' => $rfq->bidding_opens_at?->toIso8601String(),
                'bidding_closes_at' => $rfq->bidding_closes_at?->toIso8601String(),
                'lines_source' => $rfq->pr_id ? 'purchase_requisition' : 'rfq',
                'lines' => $rfq->lines->map(static fn ($line) => [
                    'id' => (string) $line->id,
                    'line_order' => $line->line_order,
                    'description' => $line->description,
                    'uom' => $line->uom,
                    'quantity' => (float) $line->quantity,
                    'quote_basis' => ProcurementQuoteBasis::fromMetadata(is_array($line->metadata_json) ? $line->metadata_json : null),
                    'quote_basis_label' => ProcurementQuoteBasis::label(
                        ProcurementQuoteBasis::fromMetadata(is_array($line->metadata_json) ? $line->metadata_json : null),
                    ),
                ])->values()->all(),
            ],
            'vendor' => [
                'id' => (string) $invitation->vendor_id,
                'company_name' => $invitation->vendor?->company_name,
                'vendor_code' => $invitation->vendor?->vendor_code,
            ],
            'invitation' => [
                'status' => $invitation->invitation_status,
                'invited_at' => $invitation->invited_at?->toIso8601String(),
                'responded_at' => $invitation->responded_at?->toIso8601String(),
                'portal_contact_name' => $invitation->portal_contact_name,
            ],
            'existing_bid' => $existingBid instanceof ProcurementRfqBid
                ? $this->registry->bidPayload($existingBid)
                : null,
            'has_existing_bid' => $existingBid instanceof ProcurementRfqBid,
            'can_submit' => $canSubmit,
            'can_revise' => $canSubmit && $existingBid instanceof ProcurementRfqBid,
            'submission_blocked_reason' => $submissionBlockedReason,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{bid: array<string, mixed>, message: string}
     */
    public function submit(ProcurementRfqVendor $invitation, array $input, array $attachmentFiles = []): array
    {
        $rfq = $this->invitations->resolveRfq($invitation);
        $rfq = $this->prLineSync->syncIfApplicable($rfq);

        $existingBid = $rfq->bids()
            ->where('vendor_id', $invitation->vendor_id)
            ->first();

        $blockedReason = $this->submissionBlockedReason($rfq, $existingBid);
        if ($blockedReason !== null) {
            throw ValidationException::withMessages([
                'bid' => [$blockedReason],
            ]);
        }

        $contactName = trim((string) ($input['contact_name'] ?? ''));
        if ($contactName === '') {
            throw ValidationException::withMessages([
                'contact_name' => [__('Your name is required.')],
            ]);
        }

        $isRevision = $existingBid instanceof ProcurementRfqBid;

        $bid = $this->bids->captureFromPortal(
            $rfq,
            (string) $invitation->vendor_id,
            $input,
            $contactName,
            $attachmentFiles,
        );

        return [
            'bid' => $this->registry->bidPayload($bid),
            'message' => $isRevision
                ? __('Your quotation was updated successfully.')
                : __('Your quotation was submitted successfully.'),
        ];
    }

    private function submissionBlockedReason(ProcurementRfq $rfq, ?ProcurementRfqBid $existingBid): ?string
    {
        $blockedReason = $this->invitations->quoteSubmissionBlockedReason($rfq);
        if ($blockedReason !== null) {
            return $blockedReason;
        }

        if ($existingBid !== null && (string) $existingBid->status === ProcurementRfqBidStatus::AWARDED) {
            return __('This quotation can no longer be changed.');
        }

        return null;
    }
}
