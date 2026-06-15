<?php



declare(strict_types=1);



namespace App\Modules\EApproval\Services;



use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\DB;



final class EApprovalCashAdvanceService

{

    /**

     * Open cash advances for the requestor (approved parents with remaining balance).

     *

     * @return list<array<string, mixed>>

     */

    public function openForUser(TenantUser $user): array

    {

        $rows = $this->parentBalanceRows((string) $user->id, null, null, true);



        return array_map(static function ($row): array {

            $requested = (float) ($row->requested_amount ?? 0);

            $reimbursed = (float) ($row->reimbursed_amount ?? 0);



            return [

                'id' => (string) $row->id,

                'document_no' => (string) $row->document_no,

                'created_at' => $row->created_at,

                'requestor_id' => (string) $row->requestor_id,

                'requestor_name' => $row->requestor_name,

                'requested_amount' => $requested,

                'reimbursed_amount' => $reimbursed,

                'open_balance' => $requested - $reimbursed,

            ];

        }, $rows);

    }



    /**

     * Remaining balance on an approved cash-advance parent submission.

     */

    public function countOpenTenantWide(): int
    {
        return count($this->parentBalanceRows(null, null, null, true));
    }

    public function countUnliquidatedTenantWide(): int
    {
        $rows = $this->parentBalanceRows(null, null, null, true);

        return count(array_filter(
            $rows,
            static fn ($row): bool => (float) ($row->reimbursed_amount ?? 0) <= 0,
        ));
    }

    public function openBalanceForParent(string $parentSubmissionId, ?string $excludeChildSubmissionId = null): ?float
    {
        $rows = $this->parentBalanceRows(null, $parentSubmissionId, $excludeChildSubmissionId, false);

        if ($rows !== []) {
            $row = $rows[0];
            $requested = (float) ($row->requested_amount ?? 0);
            $reimbursed = (float) ($row->reimbursed_amount ?? 0);

            return max(0, round($requested - $reimbursed, 2));
        }

        return $this->openBalanceForParentFromModels($parentSubmissionId, $excludeChildSubmissionId);
    }

    private function openBalanceForParentFromModels(
        string $parentSubmissionId,
        ?string $excludeChildSubmissionId = null,
    ): ?float {
        /** @var EApprovalSubmission|null $parent */
        $parent = EApprovalSubmission::query()
            ->with(['values.field'])
            ->find($parentSubmissionId);

        if ($parent === null || (string) $parent->status !== EApprovalSubmissionStatus::APPROVED) {
            return null;
        }

        $documentNo = trim((string) ($parent->document_no ?? ''));
        if ($documentNo === '' || str_starts_with($documentNo, 'DRAFT-')) {
            return null;
        }

        $requested = $this->submissionFieldAmount($parent, 'requested_amount');
        if ($requested === null) {
            return null;
        }

        $childrenQuery = EApprovalSubmission::query()
            ->with(['values.field'])
            ->where('parent_submission_id', $parentSubmissionId)
            ->where('status', '<>', EApprovalSubmissionStatus::REJECTED);

        if ($excludeChildSubmissionId !== null && $excludeChildSubmissionId !== '') {
            $childrenQuery->where('id', '<>', $excludeChildSubmissionId);
        }

        $reimbursed = 0.0;
        foreach ($childrenQuery->get() as $child) {
            $reimbursed += $this->submissionFieldAmount($child, 'total_reimbursement') ?? 0.0;
        }

        return max(0, round($requested - $reimbursed, 2));
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

            'req.value IS NOT NULL',

            "p.status = '".EApprovalSubmissionStatus::APPROVED."'",

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



        $having = $onlyPositiveBalance

            ? "HAVING CAST(req.value AS DECIMAL(14,2)) - IFNULL(SUM(CASE WHEN c.status <> 'rejected' THEN CAST(tr.value AS DECIMAL(14,2)) ELSE 0 END), 0) > 0"

            : '';



        $where = implode(' AND ', $filters);



        return DB::connection('tenant')->select(

            "

            SELECT

                p.id,

                p.document_no,

                p.created_at,

                p.requestor_id,

                u.name AS requestor_name,

                req.value AS requested_amount,

                IFNULL(SUM(CASE WHEN c.status <> 'rejected' THEN CAST(tr.value AS DECIMAL(14,2)) ELSE 0 END), 0) AS reimbursed_amount

            FROM e_approval_submissions p

            LEFT JOIN users u ON u.id = p.requestor_id

            LEFT JOIN e_approval_form_values req ON req.submission_id = p.id

                AND req.field_id = (

                    SELECT ff.id FROM e_approval_form_fields ff

                    WHERE ff.form_id = p.form_id AND ff.name = 'requested_amount' LIMIT 1

                )

            {$childJoin}

            LEFT JOIN e_approval_form_values tr ON tr.submission_id = c.id

                AND tr.field_id = (

                    SELECT ff2.id FROM e_approval_form_fields ff2

                    WHERE ff2.form_id = c.form_id AND ff2.name = 'total_reimbursement' LIMIT 1

                )

            WHERE {$where}

            GROUP BY p.id, p.document_no, p.created_at, p.requestor_id, u.name, req.value

            {$having}

            ORDER BY p.created_at DESC

            LIMIT 200

            ",

            $bindings,

        );

    }

}

