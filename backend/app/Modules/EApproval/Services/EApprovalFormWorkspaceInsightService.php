<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Workspace queue and money figures for cash advance and liquidation forms.
 */
final class EApprovalFormWorkspaceInsightService
{
    /**
     * @param  Builder<EApprovalSubmission>  $unfilteredByStatus
     * @return list<array<string, mixed>>
     */
    public function continueDrafts(Builder $unfilteredByStatus, TenantUser $viewer): array
    {
        $drafts = (clone $unfilteredByStatus)
            ->where('status', EApprovalSubmissionStatus::DRAFT)
            ->with(['requestor:id,name', 'form:id,name'])
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get();

        $continued = [];
        $rows = [];
        foreach ($drafts as $submission) {
            $own = (string) $submission->requestor_id === (string) $viewer->id;
            $bucket = (string) $submission->requestor_id.'|'.(string) $submission->form_id;
            $canContinue = $own && ! isset($continued[$bucket]);
            if ($canContinue) {
                $continued[$bucket] = true;
            }

            $rows[] = [
                'id' => (string) $submission->id,
                'document_no' => (string) $submission->document_no,
                'form_id' => (string) $submission->form_id,
                'form_name' => (string) ($submission->form?->name ?? ''),
                'requestor_name' => (string) ($submission->requestor?->name ?? ''),
                'created_at' => $submission->updated_at?->toIso8601String()
                    ?? $submission->created_at?->toIso8601String(),
                'can_continue' => $canContinue,
                'href' => $canContinue
                    ? '/e-approval/request/'.(string) $submission->form_id
                    : '/e-approval/submissions/'.(string) $submission->id,
            ];
        }

        return $rows;
    }

    /**
     * @param  Builder<EApprovalSubmission>  $scopedQuery
     * @return array{family: string, outstanding: float, forecast_total: float, actual_total: float}|null
     */
    public function moneySummary(EApprovalForm $form, Builder $scopedQuery): ?array
    {
        $family = $this->family($form);
        if ($family !== 'cash_advance' && $family !== 'liquidation') {
            return null;
        }

        $scopedIds = (clone $scopedQuery)->select('e_approval_submissions.id');

        if ($family === 'cash_advance') {
            $forecast = $this->sumField($scopedIds, 'requested_amount');
            $approvedIds = (clone $scopedQuery)
                ->where('status', EApprovalSubmissionStatus::APPROVED)
                ->select('e_approval_submissions.id');
            $reimbursedByParent = $this->reimbursedByParent($approvedIds);
            $requestedByParent = $this->fieldAmounts($approvedIds, 'requested_amount');
            $outstanding = 0.0;
            foreach ($requestedByParent as $parentId => $requested) {
                $reimbursed = $reimbursedByParent[$parentId] ?? 0.0;
                $outstanding += max(0, $requested - $reimbursed);
            }
            $actual = array_sum($this->reimbursedByParent($scopedIds));

            return [
                'family' => $family,
                'outstanding' => round($outstanding, 2),
                'forecast_total' => round($forecast, 2),
                'actual_total' => round($actual, 2),
            ];
        }

        $forecast = $this->sumField($scopedIds, 'cash_advance_amount');
        $actual = $this->sumField($scopedIds, 'total_reimbursement');
        $forecastById = $this->fieldAmounts($scopedIds, 'cash_advance_amount');
        $actualById = $this->fieldAmounts($scopedIds, 'total_reimbursement');
        $outstanding = 0.0;
        foreach ($forecastById as $id => $forecastAmount) {
            $outstanding += max(0, $forecastAmount - ($actualById[$id] ?? 0.0));
        }

        return [
            'family' => $family,
            'outstanding' => round($outstanding, 2),
            'forecast_total' => round($forecast, 2),
            'actual_total' => round($actual, 2),
        ];
    }

    /**
     * @param  Builder<EApprovalSubmission>  $scopedQuery
     * @return list<array{key: string, label: string, count: int, amount: float}>
     */
    public function subsidiaryAmounts(EApprovalForm $form, Builder $scopedQuery): array
    {
        $family = $this->family($form);
        $amountField = $family === 'liquidation' ? 'total_reimbursement' : 'requested_amount';
        if ($family !== 'cash_advance' && $family !== 'liquidation') {
            return [];
        }

        $scopedIds = (clone $scopedQuery)->select('e_approval_submissions.id');

        $rows = DB::table('e_approval_form_values as sub')
            ->join('e_approval_form_fields as sub_field', 'sub_field.id', '=', 'sub.field_id')
            ->join('e_approval_form_values as amt', 'amt.submission_id', '=', 'sub.submission_id')
            ->join('e_approval_form_fields as amt_field', 'amt_field.id', '=', 'amt.field_id')
            ->where('sub_field.name', 'subsidiary')
            ->where('amt_field.name', $amountField)
            ->whereIn('sub.submission_id', $scopedIds)
            ->where('sub.value', '!=', '')
            ->groupBy('sub.value')
            ->orderByDesc(DB::raw('SUM(CAST(REPLACE(amt.value, ",", "") AS REAL))'))
            ->limit(12)
            ->get([
                'sub.value as code',
                DB::raw('COUNT(DISTINCT sub.submission_id) as aggregate'),
                DB::raw('SUM(CAST(REPLACE(amt.value, ",", "") AS REAL)) as amount'),
            ]);

        $breakdown = [];
        foreach ($rows as $row) {
            $code = trim((string) ($row->code ?? ''));
            if ($code === '') {
                continue;
            }
            $breakdown[] = [
                'key' => $code,
                'label' => $code,
                'count' => (int) ($row->aggregate ?? 0),
                'amount' => round((float) ($row->amount ?? 0), 2),
            ];
        }

        return $breakdown;
    }

    /**
     * @param  array<string, mixed>  $workspace
     */
    public function queueHref(array $workspace, string $queue): ?string
    {
        $slug = trim((string) ($workspace['slug'] ?? ''));
        if ($slug === '') {
            return null;
        }

        return '/e-approval/w/'.$slug.'?queue='.$queue;
    }

    public static function formatMoney(float $amount): string
    {
        return number_format($amount, 2, '.', ',');
    }

    private function family(EApprovalForm $form): string
    {
        $metadata = is_array($form->metadata_json) ? $form->metadata_json : [];

        return strtolower(trim((string) ($metadata['form_family'] ?? '')));
    }

    /**
     * @param  Builder<EApprovalSubmission>|\Illuminate\Database\Query\Builder  $submissionIds
     */
    private function sumField(mixed $submissionIds, string $fieldName): float
    {
        $total = DB::table('e_approval_form_values as fv')
            ->join('e_approval_form_fields as ff', 'ff.id', '=', 'fv.field_id')
            ->where('ff.name', $fieldName)
            ->whereIn('fv.submission_id', $submissionIds)
            ->selectRaw('SUM(CAST(REPLACE(fv.value, ",", "") AS REAL)) as total')
            ->value('total');

        return (float) ($total ?? 0);
    }

    /**
     * @param  Builder<EApprovalSubmission>|\Illuminate\Database\Query\Builder  $submissionIds
     * @return array<string, float>
     */
    private function fieldAmounts(mixed $submissionIds, string $fieldName): array
    {
        $rows = DB::table('e_approval_form_values as fv')
            ->join('e_approval_form_fields as ff', 'ff.id', '=', 'fv.field_id')
            ->where('ff.name', $fieldName)
            ->whereIn('fv.submission_id', $submissionIds)
            ->get(['fv.submission_id', 'fv.value']);

        $amounts = [];
        foreach ($rows as $row) {
            $raw = str_replace(',', '', trim((string) ($row->value ?? '')));
            if ($raw === '' || ! is_numeric($raw)) {
                continue;
            }
            $amounts[(string) $row->submission_id] = (float) $raw;
        }

        return $amounts;
    }

    /**
     * @param  Builder<EApprovalSubmission>|\Illuminate\Database\Query\Builder  $parentIds
     * @return array<string, float>
     */
    private function reimbursedByParent(mixed $parentIds): array
    {
        $rows = DB::table('e_approval_submissions as c')
            ->join('e_approval_form_values as tr', 'tr.submission_id', '=', 'c.id')
            ->join('e_approval_form_fields as ff', 'ff.id', '=', 'tr.field_id')
            ->where('ff.name', 'total_reimbursement')
            ->whereIn('c.parent_submission_id', $parentIds)
            ->whereNotIn('c.status', [
                EApprovalSubmissionStatus::REJECTED,
                EApprovalSubmissionStatus::CANCELLED,
                EApprovalSubmissionStatus::DRAFT,
            ])
            ->groupBy('c.parent_submission_id')
            ->selectRaw('c.parent_submission_id as parent_id, SUM(CAST(REPLACE(tr.value, ",", "") AS REAL)) as total')
            ->get();

        $amounts = [];
        foreach ($rows as $row) {
            $amounts[(string) $row->parent_id] = (float) ($row->total ?? 0);
        }

        return $amounts;
    }
}
