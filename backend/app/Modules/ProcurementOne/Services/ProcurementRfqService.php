<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Models\ProcurementPr;
use App\Modules\ProcurementOne\Models\ProcurementRfq;
use App\Modules\ProcurementOne\Models\ProcurementRfqBid;
use App\Modules\ProcurementOne\Models\ProcurementRfqLine;
use App\Modules\ProcurementOne\Models\ProcurementRfqVendor;
use App\Modules\ProcurementOne\Models\ProcurementVendor;
use App\Modules\ProcurementOne\Support\ProcurementDocumentType;
use App\Modules\ProcurementOne\Support\ProcurementPrStatus;
use App\Modules\ProcurementOne\Support\ProcurementLineGridColumns;
use App\Modules\ProcurementOne\Support\ProcurementRfqBidStatus;
use App\Modules\ProcurementOne\Support\ProcurementRfqStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProcurementRfqService
{
    public function __construct(
        private readonly ProcurementDocumentNumberAllocator $numbers,
        private readonly ProcurementLifecycleAuditService $audit,
        private readonly ProcurementRfqVendorNotificationService $vendorNotifications,
        private readonly ProcurementRfqScoringPolicyService $scoringPolicy,
        private readonly ProcurementRfqPrLineSyncService $prLineSync,
        private readonly ProcurementRfqBuyerNotificationService $buyerNotifications,
        private readonly ProcurementRfqRegistryService $registry,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function createFromPurchaseRequisition(ProcurementPr $pr, array $input, TenantUser $actor): ProcurementRfq
    {
        abort_unless(in_array((string) $pr->status, [ProcurementPrStatus::APPROVED, ProcurementPrStatus::CONVERTED], true), 422, __('RFQs require an approved purchase requisition.'));

        $pr->loadMissing('lines');

        return DB::connection('tenant')->transaction(function () use ($pr, $input, $actor): ProcurementRfq {
            $existing = $this->registry->findActiveForPurchaseRequisition((string) $pr->id);
            if ($existing !== null) {
                $reference = $existing->document_no ?? $existing->title;

                throw ValidationException::withMessages([
                    'pr_id' => [__('This purchase requisition already has an active RFQ (:reference). Open the existing RFQ or cancel it before creating another.', [
                        'reference' => $reference,
                    ])],
                ]);
            }

            $rfq = ProcurementRfq::query()->create([
                'status' => ProcurementRfqStatus::DRAFT,
                'title' => trim((string) ($input['title'] ?? $pr->title)),
                'description' => $input['description'] ?? $pr->justification,
                'pr_id' => (string) $pr->id,
                'project_id' => $pr->project_id,
                'rollout_id' => $pr->rollout_id,
                'site_id' => $pr->site_id,
                'requestor_id' => (string) $actor->id,
                'currency_code' => (string) ($input['currency_code'] ?? $pr->currency ?? 'PHP'),
                'estimated_total' => (float) ($input['estimated_total'] ?? $pr->estimated_total),
                'notes' => $input['notes'] ?? null,
                'metadata_json' => [
                    'source_pr_document_no' => $pr->document_no,
                    'vendor_portal_ready' => (bool) $this->scoringPolicy->policy()['vendor_portal_enabled'],
                ],
            ]);

            $order = 1;
            foreach ($pr->lines as $line) {
                ProcurementRfqLine::query()->create([
                    'rfq_id' => (string) $rfq->id,
                    'line_order' => $order++,
                    'pr_line_id' => (string) $line->id,
                    'description' => $line->description,
                    'uom' => ProcurementLineGridColumns::resolveUom(is_array($line->metadata_json) ? $line->metadata_json : null),
                    'quantity' => (float) $line->quantity,
                    'target_unit_price' => (float) $line->unit_price,
                ]);
            }

            $vendorIds = array_values(array_unique(array_filter(array_map('strval', $input['vendor_ids'] ?? []))));
            if ($vendorIds !== []) {
                $this->syncInvitedVendors($rfq, $vendorIds);
            }

            $this->audit->record(
                ProcurementDocumentType::REQUEST_FOR_QUOTATION,
                (string) $rfq->id,
                null,
                'created',
                $actor,
                null,
                ['pr_id' => (string) $pr->id],
            );

            return $rfq->refresh()->load(['lines', 'invitedVendors.vendor', 'purchaseRequisition']);
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function publish(ProcurementRfq $rfq, array $input, TenantUser $actor): ProcurementRfq
    {
        abort_unless((string) $rfq->status === ProcurementRfqStatus::DRAFT, 422, __('Only draft RFQs can be published.'));

        $rfq->loadCount('invitedVendors');
        if ((int) $rfq->invited_vendors_count < 1) {
            throw ValidationException::withMessages([
                'vendor_ids' => [__('Invite at least one vendor before publishing.')],
            ]);
        }

        return DB::connection('tenant')->transaction(function () use ($rfq, $input, $actor): ProcurementRfq {
            if ($rfq->document_no === null) {
                $rfq->document_no = $this->numbers->allocate(ProcurementDocumentType::REQUEST_FOR_QUOTATION);
            }

            $rfq = $this->prLineSync->syncIfApplicable($rfq);

            $opensAt = isset($input['bidding_opens_at']) ? now()->parse($input['bidding_opens_at']) : now();
            $closesAt = isset($input['bidding_closes_at'])
                ? now()->parse($input['bidding_closes_at'])
                : $opensAt->copy()->addDays(14);

            $rfq->status = ProcurementRfqStatus::OPEN;
            $rfq->bidding_opens_at = $opensAt;
            $rfq->bidding_closes_at = $closesAt;
            $rfq->save();

            $this->audit->record(
                ProcurementDocumentType::REQUEST_FOR_QUOTATION,
                (string) $rfq->id,
                $rfq->document_no,
                'published',
                $actor,
            );

            $rfq = $rfq->refresh()->load(['lines', 'invitedVendors.vendor', 'bids.vendor']);
            $this->vendorNotifications->notifyAllInvited($rfq, 'rfq_published', $actor);

            return $rfq;
        });
    }

    /**
     * @param  list<string>  $vendorIds
     */
    public function inviteVendors(ProcurementRfq $rfq, array $vendorIds, TenantUser $actor): ProcurementRfq
    {
        abort_unless(in_array((string) $rfq->status, [ProcurementRfqStatus::DRAFT, ProcurementRfqStatus::OPEN], true), 422, __('Vendors can only be invited on draft or open RFQs.'));

        return DB::connection('tenant')->transaction(function () use ($rfq, $vendorIds, $actor): ProcurementRfq {
            $this->syncInvitedVendors($rfq, $vendorIds);

            $this->audit->record(
                ProcurementDocumentType::REQUEST_FOR_QUOTATION,
                (string) $rfq->id,
                $rfq->document_no,
                'vendors_invited',
                $actor,
                null,
                ['vendor_count' => count($vendorIds)],
            );

            $rfq = $rfq->refresh()->load(['invitedVendors.vendor']);

            foreach ($vendorIds as $vendorId) {
                $invitation = $rfq->invitedVendors->firstWhere('vendor_id', $vendorId);
                if ($invitation !== null) {
                    $this->vendorNotifications->maybeSendInvitation($rfq, $invitation, 'rfq_invited', $actor);
                }
            }

            return $rfq;
        });
    }

    public function resendVendorInvitation(ProcurementRfq $rfq, string $vendorId, TenantUser $actor): ProcurementRfq
    {
        abort_unless(in_array((string) $rfq->status, [ProcurementRfqStatus::DRAFT, ProcurementRfqStatus::OPEN], true), 422, __('Invitations can only be resent on draft or open RFQs.'));

        $invitation = ProcurementRfqVendor::query()
            ->with('vendor')
            ->where('rfq_id', $rfq->id)
            ->where('vendor_id', $vendorId)
            ->first();

        if ($invitation === null) {
            throw ValidationException::withMessages([
                'vendor_id' => [__('Vendor is not invited to this RFQ.')],
            ]);
        }

        $sent = $this->vendorNotifications->dispatch($rfq, $invitation, 'rfq_invited', $actor, true);
        if (! $sent) {
            throw ValidationException::withMessages([
                'vendor_id' => [__('Invitation email could not be sent. Check vendor contact email and mail settings.')],
            ]);
        }

        $this->audit->record(
            ProcurementDocumentType::REQUEST_FOR_QUOTATION,
            (string) $rfq->id,
            $rfq->document_no,
            'vendor_invitation_resent',
            $actor,
            null,
            ['vendor_id' => $vendorId],
        );

        return $rfq->refresh()->load(['invitedVendors.vendor']);
    }

    public function closeBidding(ProcurementRfq $rfq, ?TenantUser $actor = null, string $action = 'closed'): ProcurementRfq
    {
        abort_unless((string) $rfq->status === ProcurementRfqStatus::OPEN, 422, __('Only open RFQs can be closed.'));

        return $this->transition($rfq, ProcurementRfqStatus::CLOSED, $actor, $action, null, function (ProcurementRfq $closed) use ($actor, $action): void {
            $closed->load('invitedVendors.vendor');
            $this->vendorNotifications->notifyAllOnClose($closed, $actor);
            $this->buyerNotifications->notifyBiddingClosed($closed, $action === 'auto_closed');
        });
    }

    public function award(ProcurementRfq $rfq, ProcurementRfqBid $bid, array $input, TenantUser $actor): ProcurementRfq
    {
        abort_unless((string) $rfq->status === ProcurementRfqStatus::CLOSED, 422, __('Award requires a closed RFQ.'));
        abort_unless((string) $bid->rfq_id === (string) $rfq->id, 422, __('Bid does not belong to this RFQ.'));
        abort_unless((string) $bid->status === ProcurementRfqBidStatus::SUBMITTED, 422, __('Only submitted bids can be awarded.'));

        return DB::connection('tenant')->transaction(function () use ($rfq, $bid, $input, $actor): ProcurementRfq {
            ProcurementRfqBid::query()
                ->where('rfq_id', $rfq->id)
                ->where('id', '!=', $bid->id)
                ->where('status', ProcurementRfqBidStatus::SUBMITTED)
                ->update(['status' => ProcurementRfqBidStatus::REJECTED]);

            $bid->status = ProcurementRfqBidStatus::AWARDED;
            $bid->save();

            $rfq->status = ProcurementRfqStatus::AWARDED;
            $rfq->awarded_vendor_id = (string) $bid->vendor_id;
            $rfq->awarded_bid_id = (string) $bid->id;
            $rfq->awarded_at = now();
            $rfq->awarded_by_id = (string) $actor->id;
            $rfq->award_notes = $input['award_notes'] ?? null;
            $rfq->save();

            $this->audit->record(
                ProcurementDocumentType::REQUEST_FOR_QUOTATION,
                (string) $rfq->id,
                $rfq->document_no,
                'awarded',
                $actor,
                null,
                ['bid_id' => (string) $bid->id, 'vendor_id' => (string) $bid->vendor_id],
            );

            if ($rfq->pr_id !== null && $rfq->pr_id !== '') {
                $this->cancelSupersededDraftRfqsForPurchaseRequisition(
                    (string) $rfq->pr_id,
                    $actor,
                    __('Superseded duplicate draft RFQ.'),
                );
            }

            return $rfq->refresh()->load(['lines', 'bids.vendor', 'awardedVendor', 'awardedBid.lines']);
        });
    }

    public function markConverted(ProcurementRfq $rfq, string $poId, TenantUser $actor): ProcurementRfq
    {
        abort_unless((string) $rfq->status === ProcurementRfqStatus::AWARDED, 422, __('Only awarded RFQs can be converted to PO.'));

        return DB::connection('tenant')->transaction(function () use ($rfq, $poId, $actor): ProcurementRfq {
            $rfq->status = ProcurementRfqStatus::CONVERTED;
            $rfq->save();

            $this->audit->record(
                ProcurementDocumentType::REQUEST_FOR_QUOTATION,
                (string) $rfq->id,
                $rfq->document_no,
                'converted_to_po',
                $actor,
                null,
                ['po_id' => $poId],
            );

            return $rfq->refresh();
        });
    }

    public function cancel(ProcurementRfq $rfq, TenantUser $actor, ?string $reason = null): ProcurementRfq
    {
        abort_unless(in_array((string) $rfq->status, [ProcurementRfqStatus::DRAFT, ProcurementRfqStatus::OPEN, ProcurementRfqStatus::CLOSED], true), 422, __('This RFQ cannot be cancelled.'));

        return $this->transition($rfq, ProcurementRfqStatus::CANCELLED, $actor, 'cancelled', $reason);
    }

    public function cancelSupersededDraftRfqsForPurchaseRequisition(
        string $prId,
        ?TenantUser $actor = null,
        ?string $reason = null,
    ): int {
        if ($prId === '') {
            return 0;
        }

        $drafts = ProcurementRfq::query()
            ->where('pr_id', $prId)
            ->where('status', ProcurementRfqStatus::DRAFT)
            ->get();

        $cancelled = 0;
        foreach ($drafts as $draft) {
            $this->transition(
                $draft,
                ProcurementRfqStatus::CANCELLED,
                $actor,
                'cancelled',
                $reason ?? __('Superseded duplicate draft RFQ.'),
            );
            $cancelled++;
        }

        return $cancelled;
    }

    public function pruneDuplicateDraftRfqs(?TenantUser $actor = null): int
    {
        $prIds = ProcurementRfq::query()
            ->whereNotNull('pr_id')
            ->where('pr_id', '!=', '')
            ->whereIn('status', [
                ProcurementRfqStatus::OPEN,
                ProcurementRfqStatus::CLOSED,
                ProcurementRfqStatus::AWARDED,
                ProcurementRfqStatus::CONVERTED,
            ])
            ->distinct()
            ->pluck('pr_id');

        $cancelled = 0;
        foreach ($prIds as $prId) {
            $cancelled += $this->cancelSupersededDraftRfqsForPurchaseRequisition((string) $prId, $actor);
        }

        return $cancelled;
    }

    public function find(string $id): ?ProcurementRfq
    {
        $rfq = ProcurementRfq::query()
            ->with([
                'lines',
                'invitedVendors.vendor',
                'bids.vendor',
                'bids.lines.rfqLine',
                'bids.versions',
                'bids.attachments',
                'awardedVendor',
                'awardedBid.lines',
                'purchaseRequisition.lines',
                'requestor:id,name',
                'poLink.purchaseOrder',
            ])
            ->find($id);

        if ($rfq === null) {
            return null;
        }

        return $this->prLineSync->syncIfApplicable($rfq);
    }

    /**
     * @param  list<string>  $vendorIds
     */
    private function syncInvitedVendors(ProcurementRfq $rfq, array $vendorIds): void
    {
        $existing = ProcurementVendor::query()->whereIn('id', $vendorIds)->where('is_active', true)->pluck('id')->all();
        if (count($existing) !== count($vendorIds)) {
            throw ValidationException::withMessages([
                'vendor_ids' => [__('One or more vendors were not found.')],
            ]);
        }

        foreach ($vendorIds as $vendorId) {
            ProcurementRfqVendor::query()->firstOrCreate(
                ['rfq_id' => (string) $rfq->id, 'vendor_id' => $vendorId],
                ['invitation_status' => 'invited', 'invited_at' => now()],
            );
        }
    }

    private function transition(
        ProcurementRfq $rfq,
        string $status,
        ?TenantUser $actor,
        string $action,
        ?string $reason = null,
        ?callable $afterSave = null,
    ): ProcurementRfq {
        return DB::connection('tenant')->transaction(function () use ($rfq, $status, $actor, $action, $reason, $afterSave): ProcurementRfq {
            $rfq->status = $status;
            $rfq->save();

            $this->audit->record(
                ProcurementDocumentType::REQUEST_FOR_QUOTATION,
                (string) $rfq->id,
                $rfq->document_no,
                $action,
                $actor,
                $reason,
            );

            $rfq = $rfq->refresh();

            if ($afterSave !== null) {
                $afterSave($rfq);
            }

            return $rfq;
        });
    }
}

