<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Core\Support\AllowlistedSort;
use App\Modules\ProcurementOne\Models\ProcurementRfq;
use App\Modules\ProcurementOne\Models\ProcurementRfqBid;
use App\Modules\ProcurementOne\Support\ProcurementDocumentType;
use App\Modules\ProcurementOne\Support\ProcurementQuoteBasis;
use App\Modules\ProcurementOne\Support\ProcurementRfqBidStatus;
use App\Modules\ProcurementOne\Support\ProcurementRfqStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ProcurementRfqRegistryService
{
    private const SORTABLE = [
        'document_no',
        'title',
        'status',
        'bidding_closes_at',
        'updated_at',
        'created_at',
    ];

    public function __construct(
        private readonly ProcurementLifecycleAuditService $audit,
        private readonly ProcurementRfqComparisonService $comparison,
    ) {}

    public function findActiveForPurchaseRequisition(string $prId): ?ProcurementRfq
    {
        return ProcurementRfq::query()
            ->where('pr_id', $prId)
            ->whereIn('status', ProcurementRfqStatus::activeForSourcing())
            ->orderByDesc('updated_at')
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function activeSummaryForPurchaseRequisition(string $prId): ?array
    {
        $rfq = $this->findActiveForPurchaseRequisition($prId);
        if ($rfq === null) {
            return null;
        }

        return [
            'id' => (string) $rfq->id,
            'document_no' => $rfq->document_no,
            'title' => $rfq->title,
            'status' => $rfq->status,
            'status_label' => ProcurementRfqStatus::label((string) $rfq->status),
            'updated_at' => $rfq->updated_at?->toIso8601String(),
        ];
    }

    public function paginate(
        int $page,
        int $perPage,
        ?string $search = null,
        ?string $status = null,
        ?string $prId = null,
        ?string $sort = null,
    ): LengthAwarePaginator {
        $query = ProcurementRfq::query()
            ->with(['purchaseRequisition:id,document_no,title', 'requestor:id,name', 'awardedVendor:id,company_name'])
            ->withCount(['invitedVendors', 'bids']);

        if ($status !== null && $status !== '' && $status !== 'all') {
            $query->where('status', $status);
        } else {
            $query->where('status', '!=', ProcurementRfqStatus::CANCELLED);
        }

        if ($prId !== null && $prId !== '') {
            $query->where('pr_id', $prId);
        }

        if ($search !== null && trim($search) !== '') {
            $like = '%'.addcslashes(trim($search), '%_\\').'%';
            $query->where(static function ($q) use ($like): void {
                $q->where('document_no', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhereHas('purchaseRequisition', static fn ($pq) => $pq
                        ->where('document_no', 'like', $like)
                        ->orWhere('title', 'like', $like));
            });
        }

        [$column, $direction] = AllowlistedSort::resolve(
            (string) ($sort ?? 'updated_at:desc'),
            self::SORTABLE,
            'updated_at',
            'desc',
        );
        $query->orderBy($column, $direction);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @return array<string, mixed>
     */
    public function toListPayload(ProcurementRfq $rfq): array
    {
        return [
            'id' => (string) $rfq->id,
            'document_no' => $rfq->document_no,
            'status' => $rfq->status,
            'status_label' => ProcurementRfqStatus::label((string) $rfq->status),
            'title' => $rfq->title,
            'pr_id' => $rfq->pr_id,
            'pr_document_no' => $rfq->purchaseRequisition?->document_no,
            'pr_title' => $rfq->purchaseRequisition?->title,
            'currency_code' => $rfq->currency_code,
            'estimated_total' => (float) $rfq->estimated_total,
            'bidding_closes_at' => $rfq->bidding_closes_at?->toIso8601String(),
            'invited_vendor_count' => (int) ($rfq->invited_vendors_count ?? $rfq->invitedVendors?->count() ?? 0),
            'bid_count' => (int) ($rfq->bids_count ?? $rfq->bids?->count() ?? 0),
            'awarded_vendor_name' => $rfq->awardedVendor?->company_name,
            'updated_at' => $rfq->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDetailPayload(ProcurementRfq $rfq): array
    {
        $matrix = $this->comparison->matrix($rfq);

        return $this->toListPayload($rfq) + [
            'description' => $rfq->description,
            'bidding_opens_at' => $rfq->bidding_opens_at?->toIso8601String(),
            'vendor_portal_enabled' => (bool) app(ProcurementRfqScoringPolicyService::class)->policy()['vendor_portal_enabled'],
            'awarded_at' => $rfq->awarded_at?->toIso8601String(),
            'award_notes' => $rfq->award_notes,
            'notes' => $rfq->notes,
            'metadata' => $rfq->metadata_json ?? [],
            'requestor' => $rfq->requestor ? [
                'id' => (string) $rfq->requestor->id,
                'name' => $rfq->requestor->name,
            ] : null,
            'lines_source' => $rfq->pr_id ? 'purchase_requisition' : 'rfq',
            'lines_synced_from_pr_at' => is_array($rfq->metadata_json)
                ? ($rfq->metadata_json['lines_synced_from_pr_at'] ?? null)
                : null,
            'lines' => $rfq->lines->map(static fn ($line) => [
                'id' => (string) $line->id,
                'line_order' => $line->line_order,
                'pr_line_id' => $line->pr_line_id,
                'description' => $line->description,
                'uom' => $line->uom,
                'quantity' => (float) $line->quantity,
                'target_unit_price' => $line->target_unit_price !== null ? (float) $line->target_unit_price : null,
                'quote_basis' => ProcurementQuoteBasis::fromMetadata(is_array($line->metadata_json) ? $line->metadata_json : null),
                'quote_basis_label' => ProcurementQuoteBasis::label(
                    ProcurementQuoteBasis::fromMetadata(is_array($line->metadata_json) ? $line->metadata_json : null),
                ),
            ])->values()->all(),
            'invited_vendors' => $rfq->invitedVendors->map(static fn ($row) => [
                'id' => (string) $row->id,
                'vendor_id' => (string) $row->vendor_id,
                'vendor_code' => $row->vendor?->vendor_code,
                'vendor_name' => $row->vendor?->company_name,
                'invitation_status' => $row->invitation_status,
                'invited_at' => $row->invited_at?->toIso8601String(),
                'responded_at' => $row->responded_at?->toIso8601String(),
                'invitation_email' => $row->invitation_email,
                'invitation_sent_at' => $row->invitation_sent_at?->toIso8601String(),
                'invitation_opened_at' => $row->invitation_opened_at?->toIso8601String(),
                'submitted_via' => $row->submitted_via,
                'portal_contact_name' => $row->portal_contact_name,
            ])->values()->all(),
            'bids' => $rfq->bids->map(fn (ProcurementRfqBid $bid) => $this->bidPayload($bid))->values()->all(),
            'comparison_matrix' => $matrix,
            'purchase_order' => $rfq->poLink?->purchaseOrder ? [
                'id' => (string) $rfq->poLink->purchaseOrder->id,
                'document_no' => $rfq->poLink->purchaseOrder->document_no,
                'status' => $rfq->poLink->purchaseOrder->status,
            ] : null,
            'audit_trail' => $this->audit->listForDocument(
                ProcurementDocumentType::REQUEST_FOR_QUOTATION,
                (string) $rfq->id,
                50,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function bidPayload(ProcurementRfqBid $bid): array
    {
        $bid->loadMissing(['vendor', 'lines', 'versions', 'attachments']);

        return [
            'id' => (string) $bid->id,
            'vendor_id' => (string) $bid->vendor_id,
            'vendor_code' => $bid->vendor?->vendor_code,
            'vendor_name' => $bid->vendor?->company_name,
            'status' => $bid->status,
            'status_label' => ProcurementRfqBidStatus::label((string) $bid->status),
            'total_amount' => (float) $bid->total_amount,
            'total_amount_monthly' => $bid->total_amount_monthly !== null ? (float) $bid->total_amount_monthly : null,
            'total_amount_yearly' => $bid->total_amount_yearly !== null ? (float) $bid->total_amount_yearly : null,
            'normalized_annual_amount' => $bid->normalized_annual_amount !== null
                ? (float) $bid->normalized_annual_amount
                : (float) $bid->total_amount,
            'currency_code' => $bid->currency_code,
            'validity_until' => $bid->validity_until?->format('Y-m-d'),
            'avg_lead_time_days' => $bid->avg_lead_time_days,
            'submitted_at' => $bid->submitted_at?->toIso8601String(),
            'notes' => $bid->notes,
            'version_count' => (int) $bid->versions->count(),
            'current_version_no' => (int) ($bid->versions->max('version_no') ?? 0),
            'attachments' => $bid->attachments->map(static fn ($attachment) => [
                'id' => (string) $attachment->id,
                'file_name' => $attachment->file_name,
                'mime_type' => $attachment->mime_type,
                'size_bytes' => $attachment->size_bytes,
                'uploaded_via' => $attachment->uploaded_via,
            ])->values()->all(),
            'lines' => $bid->lines->map(static fn ($line) => [
                'id' => (string) $line->id,
                'rfq_line_id' => (string) $line->rfq_line_id,
                'quantity' => (float) $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'monthly_unit_price' => $line->monthly_unit_price !== null ? (float) $line->monthly_unit_price : null,
                'yearly_unit_price' => $line->yearly_unit_price !== null ? (float) $line->yearly_unit_price : null,
                'amount' => (float) $line->amount,
                'amount_monthly' => $line->amount_monthly !== null ? (float) $line->amount_monthly : null,
                'amount_yearly' => $line->amount_yearly !== null ? (float) $line->amount_yearly : null,
                'normalized_annual_amount' => $line->normalized_annual_amount !== null
                    ? (float) $line->normalized_annual_amount
                    : (float) $line->amount,
                'quote_basis' => $line->quote_basis,
                'quote_basis_label' => ProcurementQuoteBasis::label((string) ($line->quote_basis ?? ProcurementQuoteBasis::ONE_TIME)),
                'lead_time_days' => $line->lead_time_days,
                'notes' => $line->notes,
            ])->values()->all(),
        ];
    }
}
