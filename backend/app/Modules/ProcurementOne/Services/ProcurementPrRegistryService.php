<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Core\Support\AllowlistedSort;
use App\Modules\EApproval\Services\EApprovalPurchaseRequisitionService;
use App\Modules\ProcurementOne\Models\ProcurementPr;
use App\Modules\ProcurementOne\Support\ProcurementDocumentType;
use App\Modules\ProcurementOne\Support\ProcurementPrStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ProcurementPrRegistryService
{
    private const SORTABLE = [
        'document_no',
        'title',
        'status',
        'estimated_total',
        'updated_at',
        'created_at',
    ];

    public function __construct(
        private readonly EApprovalPurchaseRequisitionService $purchaseRequisitions,
        private readonly ProcurementPrBudgetPolicyService $budgetPolicy,
        private readonly ProcurementBudgetUtilizationService $budgetUtilization,
        private readonly ProcurementLifecycleAuditService $lifecycleAudit,
        private readonly ProcurementComposeValuesResolver $composeValues,
        private readonly ProcurementPoPrBalanceService $poPrBalance,
        private readonly ProcurementRfqRegistryService $rfqRegistry,
    ) {}

    public function paginate(
        int $page,
        int $perPage,
        ?string $search = null,
        ?string $status = null,
        ?string $requestorId = null,
        ?string $sort = null,
    ): LengthAwarePaginator {
        $query = ProcurementPr::query()
            ->with(['requestor:id,name,email']);

        if ($status !== null && $status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($requestorId !== null && $requestorId !== '') {
            $query->where('requestor_id', $requestorId);
        }

        if ($search !== null && trim($search) !== '') {
            $like = '%'.addcslashes(trim($search), '%_\\').'%';
            $query->where(static function ($q) use ($like): void {
                $q->where('title', 'like', $like)
                    ->orWhere('document_no', 'like', $like)
                    ->orWhere('department', 'like', $like);
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

    public function find(string $id): ?ProcurementPr
    {
        return ProcurementPr::query()
            ->with(['lines', 'attachments', 'requestor:id,name,email'])
            ->find($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function toListPayload(ProcurementPr $pr): array
    {
        return [
            'id' => (string) $pr->id,
            'document_no' => $pr->document_no,
            'title' => $pr->title,
            'status' => $pr->status,
            'status_label' => ProcurementPrStatus::label((string) $pr->status),
            'department' => $pr->department,
            'urgency' => $pr->urgency,
            'estimated_total' => (float) $pr->estimated_total,
            'currency' => $pr->currency,
            'requestor' => $pr->requestor ? [
                'id' => (string) $pr->requestor->id,
                'name' => $pr->requestor->name,
            ] : null,
            'submitted_at' => $pr->submitted_at?->toIso8601String(),
            'approved_at' => $pr->approved_at?->toIso8601String(),
            'updated_at' => $pr->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDetailPayload(ProcurementPr $pr): array
    {
        $openBalance = $this->poPrBalance->openBalanceForPr($pr);

        $budget = $this->budgetPolicy->preview($pr);
        $utilization = $pr->rollout_id !== null
            ? $this->budgetUtilization->snapshotForRollout((string) $pr->rollout_id)
            : ($pr->project_id !== null
                ? $this->budgetUtilization->snapshotForProject((string) $pr->project_id)
                : null);

        return $this->toListPayload($pr) + [
            'justification' => $pr->justification,
            'project_id' => $pr->project_id,
            'rollout_id' => $pr->rollout_id,
            'site_id' => $pr->site_id,
            'boq_line_id' => $pr->boq_line_id,
            'e_approval_submission_id' => $pr->e_approval_submission_id,
            'e_approval_form_id' => $pr->e_approval_form_id,
            'compose_values' => $this->composeValues->forPurchaseRequisition($pr),
            'committed_po_amount' => (float) $pr->committed_po_amount,
            'open_po_balance' => $openBalance,
            'active_rfq' => $this->rfqRegistry->activeSummaryForPurchaseRequisition((string) $pr->id),
            'rejected_at' => $pr->rejected_at?->toIso8601String(),
            'cancelled_at' => $pr->cancelled_at?->toIso8601String(),
            'voided_at' => $pr->voided_at?->toIso8601String(),
            'void_reason' => $pr->void_reason,
            'lifecycle_reason' => $pr->lifecycle_reason,
            'lifecycle_events' => $this->lifecycleAudit->listForDocument(
                ProcurementDocumentType::PURCHASE_REQUISITION,
                (string) $pr->id,
            ),
            'budget_check' => [
                'policy_enabled' => $this->budgetPolicy->isEnforced(),
                'budget_total' => $budget['budget_total'],
                'committed' => $budget['committed'],
                'committed_pr' => $utilization['committed_pr'] ?? null,
                'committed_po' => $utilization['committed_po'] ?? null,
                'available' => $budget['available'],
                'utilization_percent' => $utilization['utilization_percent'] ?? null,
            ],
            'lines' => $pr->lines->map(static fn ($line) => [
                'id' => (string) $line->id,
                'line_order' => $line->line_order,
                'description' => $line->description,
                'quantity' => (float) $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'amount' => (float) $line->amount,
                'cost_center_id' => $line->cost_center_id,
                'expense_type' => $line->expense_type,
                'budget_line_id' => $line->budget_line_id,
            ])->values()->all(),
            'attachments' => $pr->attachments->map(static fn ($attachment) => [
                'id' => (string) $attachment->id,
                'field_name' => $attachment->field_name,
                'file_name' => $attachment->file_name,
                'mime_type' => $attachment->mime_type,
                'size_bytes' => $attachment->size_bytes,
                'e_approval_attachment_id' => $attachment->e_approval_attachment_id,
            ])->values()->all(),
        ];
    }
}
