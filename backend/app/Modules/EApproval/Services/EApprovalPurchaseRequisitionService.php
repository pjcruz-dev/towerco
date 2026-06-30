<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Models\ProcurementPo;
use App\Modules\ProcurementOne\Models\ProcurementPr;
use App\Modules\ProcurementOne\Services\ProcurementPoPrBalanceService;
use App\Modules\ProcurementOne\Support\ProcurementPoStatus;
use Illuminate\Support\Facades\DB;

final class EApprovalPurchaseRequisitionService
{
    public function __construct(
        private readonly ProcurementPoPrBalanceService $procurementBalances,
    ) {}
    /**
     * Approved purchase requisitions for the requestor with remaining PO budget.
     *
     * @return list<array<string, mixed>>
     */
    public function openForUser(TenantUser $user): array
    {
        $rows = $this->parentBalanceRows((string) $user->id, null, null, true);

        return array_map(static function ($row): array {
            $estimated = (float) ($row->estimated_total ?? 0);
            $committed = (float) ($row->committed_amount ?? 0);

            return [
                'id' => (string) $row->id,
                'document_no' => (string) $row->document_no,
                'created_at' => $row->created_at,
                'requestor_id' => (string) $row->requestor_id,
                'requestor_name' => $row->requestor_name,
                'requisition_title' => $row->requisition_title,
                'estimated_total' => $estimated,
                'committed_amount' => $committed,
                'open_balance' => $estimated - $committed,
            ];
        }, $rows);
    }

    /**
     * Approved purchase requisitions with remaining PO budget for procurement buyers.
     *
     * @return list<array<string, mixed>>
     */
    public function openForProcurement(): array
    {
        $rows = $this->parentBalanceRows(null, null, null, true);

        return array_map(static function ($row): array {
            $estimated = (float) ($row->estimated_total ?? 0);
            $committed = (float) ($row->committed_amount ?? 0);

            return [
                'id' => (string) $row->id,
                'document_no' => (string) $row->document_no,
                'created_at' => $row->created_at,
                'requestor_id' => (string) $row->requestor_id,
                'requestor_name' => $row->requestor_name,
                'requisition_title' => $row->requisition_title,
                'estimated_total' => $estimated,
                'committed_amount' => $committed,
                'open_balance' => $estimated - $committed,
            ];
        }, $rows);
    }

    /**
     * Remaining PO budget on an approved purchase-requisition parent submission.
     */
    public function countWithoutPoTenantWide(): int
    {
        $rows = $this->parentBalanceRows(null, null, null, false);

        return count(array_filter(
            $rows,
            static fn ($row): bool => (float) ($row->committed_amount ?? 0) <= 0,
        ));
    }

    public function openBalanceForParent(string $parentSubmissionId, ?string $excludeChildSubmissionId = null): ?float
    {
        $procurementPr = ProcurementPr::query()
            ->where('e_approval_submission_id', $parentSubmissionId)
            ->first();

        if ($procurementPr instanceof ProcurementPr) {
            return $this->openBalanceForProcurementPr($procurementPr, $excludeChildSubmissionId);
        }

        $rows = $this->parentBalanceRows(null, $parentSubmissionId, $excludeChildSubmissionId, false);

        if ($rows !== []) {
            $row = $rows[0];
            $estimated = (float) ($row->estimated_total ?? 0);
            $committed = (float) ($row->committed_amount ?? 0);

            return max(0, round($estimated - $committed, 2));
        }

        return $this->openBalanceForParentFromModels($parentSubmissionId, $excludeChildSubmissionId);
    }

    private function openBalanceForProcurementPr(
        ProcurementPr $procurementPr,
        ?string $excludeChildSubmissionId = null,
    ): ?float {
        $excludePoId = null;
        if ($excludeChildSubmissionId !== null && $excludeChildSubmissionId !== '') {
            $excludePoId = ProcurementPo::query()
                ->where('e_approval_submission_id', $excludeChildSubmissionId)
                ->value('id');
            $excludePoId = $excludePoId !== null ? (string) $excludePoId : null;
        }

        return $this->procurementBalances->openBalanceForPr($procurementPr, $excludePoId);
    }

    private function openBalanceForParentFromModels(
        string $parentSubmissionId,
        ?string $excludeChildSubmissionId = null,
    ): ?float {
        /** @var EApprovalSubmission|null $parent */
        $parent = EApprovalSubmission::query()
            ->with(['values.field', 'form'])
            ->find($parentSubmissionId);

        if ($parent === null || (string) $parent->status !== EApprovalSubmissionStatus::APPROVED) {
            return null;
        }

        if (! $this->isPurchaseRequisitionForm($parent)) {
            return null;
        }

        $documentNo = trim((string) ($parent->document_no ?? ''));
        if ($documentNo === '' || str_starts_with($documentNo, 'DRAFT-')) {
            return null;
        }

        $estimated = $this->submissionFieldAmount($parent, 'estimated_total');
        if ($estimated === null) {
            $procurementPr = ProcurementPr::query()
                ->where('e_approval_submission_id', $parentSubmissionId)
                ->first();
            $estimated = $procurementPr !== null ? (float) $procurementPr->estimated_total : null;
        }

        if ($estimated === null) {
            return null;
        }

        $childrenQuery = EApprovalSubmission::query()
            ->with(['values.field'])
            ->where('parent_submission_id', $parentSubmissionId)
            ->where('status', '<>', EApprovalSubmissionStatus::REJECTED);

        if ($excludeChildSubmissionId !== null && $excludeChildSubmissionId !== '') {
            $childrenQuery->where('id', '<>', $excludeChildSubmissionId);
        }

        $committed = 0.0;
        foreach ($childrenQuery->get() as $child) {
            $committed += $this->childPurchaseOrderCommittedAmount($child);
        }

        return max(0, round($estimated - $committed, 2));
    }

    private function childPurchaseOrderCommittedAmount(EApprovalSubmission $child): float
    {
        $procurementPo = ProcurementPo::query()->where('e_approval_submission_id', (string) $child->id)->first();
        if ($procurementPo instanceof ProcurementPo) {
            if (in_array((string) $procurementPo->status, [ProcurementPoStatus::CANCELLED, ProcurementPoStatus::VOIDED], true)) {
                return 0.0;
            }

            return (float) $procurementPo->grand_total;
        }

        return $this->submissionFieldAmount($child, 'grand_total')
            ?? $this->submissionFieldAmount($child, 'total_amount')
            ?? 0.0;
    }

    private function isPurchaseRequisitionForm(EApprovalSubmission $submission): bool
    {
        $metadata = is_array($submission->form?->metadata_json) ? $submission->form->metadata_json : [];

        return ($metadata['form_family'] ?? null) === 'purchase_requisition';
    }

    private function submissionFieldAmount(EApprovalSubmission $submission, string $fieldName): ?float
    {
        foreach ($submission->values as $value) {
            if ((string) ($value->field?->name ?? '') !== $fieldName) {
                continue;
            }

            $raw = trim(str_replace(',', '', (string) ($value->value ?? '')));
            if ($raw === '' || ! is_numeric($raw)) {
                return null;
            }

            return (float) $raw;
        }

        return null;
    }

    /**
     * @return list<object>
     */
    private function parentBalanceRows(
        ?string $requestorId,
        ?string $parentSubmissionId,
        ?string $excludeChildSubmissionId,
        bool $onlyPositiveBalance,
    ): array {
        $bindings = [];

        $filters = [
            'p.document_no IS NOT NULL',
            "p.document_no <> ''",
            "p.document_no NOT LIKE 'DRAFT-%'",
            "p.status = '".EApprovalSubmissionStatus::APPROVED."'",
            $this->purchaseRequisitionFormFamilySql('pf'),
        ];

        if ($requestorId !== null) {
            $filters[] = 'p.requestor_id = ?';
            $bindings[] = $requestorId;
        }

        if ($parentSubmissionId !== null) {
            $filters[] = 'p.id = ?';
            $bindings[] = $parentSubmissionId;
        }

        $childJoin = 'LEFT JOIN e_approval_submissions c ON c.parent_submission_id = p.id';
        if ($excludeChildSubmissionId !== null && $excludeChildSubmissionId !== '') {
            $childJoin .= ' AND c.id <> ?';
            $bindings[] = $excludeChildSubmissionId;
        }

        $estimatedSubquery = "CAST(COALESCE(
            (
                SELECT NULLIF(REPLACE(fv.value, ',', ''), '')
                FROM e_approval_form_values fv
                INNER JOIN e_approval_form_fields ff ON ff.id = fv.field_id AND ff.name = 'estimated_total'
                WHERE fv.submission_id = p.id
                LIMIT 1
            ),
            (
                SELECT ppr2.estimated_total
                FROM procurement_prs ppr2
                WHERE ppr2.e_approval_submission_id = p.id
                LIMIT 1
            )
        ) AS DECIMAL(14,2))";

        $titleSubquery = "COALESCE(
            (
                SELECT fv.value
                FROM e_approval_form_values fv
                INNER JOIN e_approval_form_fields ff ON ff.id = fv.field_id AND ff.name = 'requisition_title'
                WHERE fv.submission_id = p.id
                LIMIT 1
            ),
            (
                SELECT ppr3.title
                FROM procurement_prs ppr3
                WHERE ppr3.e_approval_submission_id = p.id
                LIMIT 1
            )
        )";

        $filters[] = "{$estimatedSubquery} IS NOT NULL";

        $where = implode(' AND ', $filters);

        $havingParts = [];

        if ($onlyPositiveBalance) {
            $havingParts[] = "{$estimatedSubquery} - IFNULL(SUM(
                    CASE
                        WHEN c.status <> 'rejected' AND po_field.name = 'total_amount'
                            THEN CAST(COALESCE(NULLIF(REPLACE(po.value, ',', ''), ''), '0') AS DECIMAL(14,2))
                        ELSE 0
                    END
                ), 0) > 0";
        }

        $having = $havingParts === [] ? '' : 'HAVING '.implode(' AND ', $havingParts);

        return DB::connection('tenant')->select(
            "
            SELECT
                p.id,
                p.document_no,
                p.created_at,
                p.requestor_id,
                u.name AS requestor_name,
                {$titleSubquery} AS requisition_title,
                {$estimatedSubquery} AS estimated_total,
                IFNULL(SUM(
                    CASE
                        WHEN c.status <> 'rejected' AND po_field.name = 'total_amount'
                            THEN CAST(COALESCE(NULLIF(REPLACE(po.value, ',', ''), ''), '0') AS DECIMAL(14,2))
                        ELSE 0
                    END
                ), 0) AS committed_amount
            FROM e_approval_submissions p
            INNER JOIN e_approval_forms pf ON pf.id = p.form_id
            LEFT JOIN users u ON u.id = p.requestor_id
            {$childJoin}
            LEFT JOIN e_approval_form_values po ON po.submission_id = c.id
            LEFT JOIN e_approval_form_fields po_field ON po_field.id = po.field_id AND po_field.name = 'total_amount'
            WHERE {$where}
            GROUP BY p.id, p.document_no, p.created_at, p.requestor_id, u.name
            {$having}
            ORDER BY p.created_at DESC
            LIMIT 200
            ",
            $bindings,
        );
    }

    private function purchaseRequisitionFormFamilySql(string $formAlias): string
    {
        $driver = DB::connection('tenant')->getDriverName();

        if ($driver === 'sqlite') {
            return "json_extract({$formAlias}.metadata_json, '$.form_family') = 'purchase_requisition'";
        }

        return "JSON_UNQUOTE(JSON_EXTRACT({$formAlias}.metadata_json, '$.form_family')) = 'purchase_requisition'";
    }
}
