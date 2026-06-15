<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalSubmission;
use Illuminate\Support\Collection;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;

final class EApprovalSubmissionRelatedService
{
    public function __construct(
        private readonly EApprovalCashAdvanceService $cashAdvances,
        private readonly EApprovalPurchaseRequisitionService $purchaseRequisitions,
    ) {}

    /**
     * @return array{
     *     parent: array<string, mixed>|null,
     *     children: list<array<string, mixed>>,
     *     context_form_family: string|null,
     *     summary: array<string, mixed>|null
     * }
     */
    /**
     * Published related forms from the submission form's related_form_ids (D2 navigation hints).
     *
     * @return list<array{form_id: string, form_name: string, form_family: string|null, href: string}>
     */
    public function relatedFormNavigation(?EApprovalForm $form): array
    {
        if (! $form instanceof EApprovalForm) {
            return [];
        }

        $relatedIds = $form->related_form_ids;
        if (! is_array($relatedIds) || $relatedIds === []) {
            return [];
        }

        /** @var Collection<int, EApprovalForm> $relatedForms */
        $relatedForms = EApprovalForm::query()
            ->whereIn('id', $relatedIds)
            ->where('status', 'published')
            ->orderBy('name')
            ->get();

        return $relatedForms
            ->map(function (EApprovalForm $relatedForm): array {
                $metadata = is_array($relatedForm->metadata_json) ? $relatedForm->metadata_json : [];

                return [
                    'form_id' => (string) $relatedForm->id,
                    'form_name' => (string) $relatedForm->name,
                    'form_family' => isset($metadata['form_family']) ? (string) $metadata['form_family'] : null,
                    'href' => '/e-approval/request/'.(string) $relatedForm->id,
                ];
            })
            ->values()
            ->all();
    }

    public function listForSubmission(EApprovalSubmission $submission): array
    {
        $submission->loadMissing(['form', 'values.field']);

        $contextFamily = $this->formFamily($submission->form);

        $parent = null;
        if ($submission->parent_submission_id !== null && $submission->parent_submission_id !== '') {
            /** @var EApprovalSubmission|null $parentSubmission */
            $parentSubmission = EApprovalSubmission::query()
                ->with(['form:id,name,metadata_json', 'values.field'])
                ->find($submission->parent_submission_id);

            if ($parentSubmission !== null) {
                $parent = $this->toRelatedRow($parentSubmission, 'parent');
            }
        }

        $children = EApprovalSubmission::query()
            ->with(['form:id,name,metadata_json', 'values.field'])
            ->where('parent_submission_id', $submission->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (EApprovalSubmission $child): array => $this->toRelatedRow($child, 'child'))
            ->values()
            ->all();

        return [
            'parent' => $parent,
            'children' => $children,
            'context_form_family' => $contextFamily,
            'summary' => $this->buildChainSummary($submission, $contextFamily),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toRelatedRow(EApprovalSubmission $submission, string $relationship): array
    {
        $amount = $this->relatedAmount($submission);

        return [
            'id' => (string) $submission->id,
            'document_no' => (string) $submission->document_no,
            'status' => (string) $submission->status,
            'form_id' => (string) $submission->form_id,
            'form_name' => $submission->form?->name,
            'form_family' => $this->formFamily($submission->form),
            'relationship' => $relationship,
            'amount_label' => $amount['label'],
            'amount_value' => $amount['value'],
            'created_at' => $submission->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{label: string|null, value: string|null}
     */
    private function relatedAmount(EApprovalSubmission $submission): array
    {
        $submission->loadMissing(['values.field']);

        $preferredFields = [
            'requested_amount' => 'Requested',
            'total_reimbursement' => 'Liquidation amount',
            'estimated_total' => 'Estimated total',
            'total_amount' => 'PO total',
        ];

        foreach ($preferredFields as $fieldName => $label) {
            foreach ($submission->values as $value) {
                if ((string) ($value->field?->name ?? '') !== $fieldName) {
                    continue;
                }

                $raw = trim((string) ($value->value ?? ''));
                if ($raw === '') {
                    continue;
                }

                return ['label' => $label, 'value' => $raw];
            }
        }

        return ['label' => null, 'value' => null];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildChainSummary(EApprovalSubmission $submission, ?string $contextFamily): ?array
    {
        if ($contextFamily === 'purchase_requisition') {
            return $this->buildPurchaseRequisitionSummary($submission);
        }

        if ($contextFamily === 'cash_advance') {
            return $this->buildCashAdvanceSummary($submission);
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildPurchaseRequisitionSummary(EApprovalSubmission $submission): ?array
    {
        $estimated = $this->fieldAmount($submission, 'estimated_total');
        if ($estimated === null) {
            return null;
        }

        $openBalance = $this->purchaseRequisitions->openBalanceForParent((string) $submission->id);
        if ($openBalance === null) {
            $committed = $this->sumChildAmounts($submission, 'total_amount');
            $openBalance = max(0, round($estimated - $committed, 2));
        } else {
            $committed = max(0, round($estimated - $openBalance, 2));
        }

        return [
            'kind' => 'purchase_requisition_budget',
            'total_label' => 'Estimated total',
            'total_amount' => $estimated,
            'committed_label' => 'Committed on POs',
            'committed_amount' => $committed,
            'open_balance' => $openBalance,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildCashAdvanceSummary(EApprovalSubmission $submission): ?array
    {
        $requested = $this->fieldAmount($submission, 'requested_amount');
        if ($requested === null) {
            return null;
        }

        $openBalance = $this->cashAdvances->openBalanceForParent((string) $submission->id);
        if ($openBalance === null) {
            $committed = $this->sumChildAmounts($submission, 'total_reimbursement');
            $openBalance = max(0, round($requested - $committed, 2));
        } else {
            $committed = max(0, round($requested - $openBalance, 2));
        }

        return [
            'kind' => 'cash_advance_balance',
            'total_label' => 'Requested amount',
            'total_amount' => $requested,
            'committed_label' => 'Liquidated',
            'committed_amount' => $committed,
            'open_balance' => $openBalance,
        ];
    }

    private function sumChildAmounts(EApprovalSubmission $parent, string $fieldName): float
    {
        $children = EApprovalSubmission::query()
            ->with(['values.field'])
            ->where('parent_submission_id', $parent->id)
            ->where('status', '<>', EApprovalSubmissionStatus::REJECTED)
            ->get();

        $total = 0.0;
        foreach ($children as $child) {
            $total += $this->fieldAmount($child, $fieldName) ?? 0.0;
        }

        return round($total, 2);
    }

    private function fieldAmount(EApprovalSubmission $submission, string $fieldName): ?float
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

    private function formFamily(?EApprovalForm $form): ?string
    {
        if ($form === null) {
            return null;
        }

        $metadata = is_array($form->metadata_json) ? $form->metadata_json : [];
        $family = $metadata['form_family'] ?? null;

        return is_string($family) && trim($family) !== '' ? trim($family) : null;
    }
}
