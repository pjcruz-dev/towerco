<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Models\ProcurementRfqVendor;
use App\Modules\ProcurementOne\Models\ProcurementVendor;
use App\Modules\ProcurementOne\Support\ProcurementRfqStatus;

final class ProcurementVendorInboxService
{
    public function __construct(
        private readonly ProcurementVendorInboxTokenService $inboxTokens,
        private readonly ProcurementRfqVendorInvitationService $invitations,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function inboxPayload(ProcurementVendor $vendor): array
    {
        $this->inboxTokens->markOpened($vendor);

        $invitations = ProcurementRfqVendor::query()
            ->with(['rfq', 'vendor'])
            ->where('vendor_id', $vendor->id)
            ->whereHas('rfq', static fn ($query) => $query->whereIn('status', [
                ProcurementRfqStatus::DRAFT,
                ProcurementRfqStatus::OPEN,
                ProcurementRfqStatus::CLOSED,
            ]))
            ->orderByDesc('invited_at')
            ->get();

        return [
            'vendor' => [
                'id' => (string) $vendor->id,
                'company_name' => $vendor->company_name,
                'vendor_code' => $vendor->vendor_code,
            ],
            'items' => $invitations->map(function (ProcurementRfqVendor $invitation): array {
                $invitation->loadMissing('rfq');
                $rfq = $invitation->rfq;
                $quoteUrl = null;
                $canQuote = false;

                if ($rfq !== null && (string) $rfq->status === ProcurementRfqStatus::OPEN) {
                    try {
                        $this->invitations->assertAcceptingQuotes($invitation);
                        [, $quoteUrl] = $this->invitations->issueToken($invitation, $rfq);
                        $canQuote = true;
                    } catch (\Throwable) {
                        $canQuote = false;
                    }
                }

                return [
                    'invitation_id' => (string) $invitation->id,
                    'rfq_id' => (string) ($rfq?->id ?? ''),
                    'document_no' => $rfq?->document_no,
                    'title' => $rfq?->title,
                    'status' => $rfq?->status,
                    'status_label' => $rfq !== null ? ProcurementRfqStatus::label((string) $rfq->status) : null,
                    'invitation_status' => $invitation->invitation_status,
                    'invited_at' => $invitation->invited_at?->toIso8601String(),
                    'responded_at' => $invitation->responded_at?->toIso8601String(),
                    'bidding_closes_at' => $rfq?->bidding_closes_at?->toIso8601String(),
                    'can_quote' => $canQuote,
                    'quote_url' => $quoteUrl,
                ];
            })->values()->all(),
        ];
    }
}
