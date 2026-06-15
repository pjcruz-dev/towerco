<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalSubmission;

final class EApprovalFinanceProcurementPolicyService
{
    public const MODE_BLOCK = 'block';

    public const MODE_WARN = 'warn';

    public function __construct(
        private readonly EApprovalSettingsService $settings,
        private readonly EApprovalPurchaseRequisitionService $purchaseRequisitions,
        private readonly EApprovalCashAdvanceService $cashAdvances,
    ) {}

    /**
     * @return array{
     *     liquidation_requires_parent: bool,
     *     liquidation_overspend_mode: string,
     *     liquidation_max_overspend_percent: int,
     *     po_overspend_mode: string,
     *     po_max_overspend_percent: int
     * }
     */
    public function snapshot(): array
    {
        return [
            'liquidation_requires_parent' => $this->liquidationRequiresParent(),
            'liquidation_overspend_mode' => $this->liquidationOverspendMode(),
            'liquidation_max_overspend_percent' => $this->liquidationMaxOverspendPercent(),
            'po_overspend_mode' => $this->overspendMode(),
            'po_max_overspend_percent' => $this->maxOverspendPercent(),
        ];
    }

    public function liquidationRequiresParent(): bool
    {
        return $this->settings->getBool(EApprovalSettingsService::LIQUIDATION_REQUIRES_PARENT, true);
    }

    public function liquidationOverspendMode(): string
    {
        $raw = strtolower(trim((string) $this->settings->getString(
            EApprovalSettingsService::LIQUIDATION_OVERSPEND_MODE,
            self::MODE_BLOCK,
        )));

        return $raw === self::MODE_WARN ? self::MODE_WARN : self::MODE_BLOCK;
    }

    public function liquidationMaxOverspendPercent(): int
    {
        if ($this->liquidationOverspendMode() === self::MODE_BLOCK) {
            return 0;
        }

        $percent = $this->settings->getInt(EApprovalSettingsService::LIQUIDATION_MAX_OVERSPEND_PERCENT, 0);

        return max(0, min(25, $percent));
    }

    public function overspendMode(): string
    {
        $raw = strtolower(trim((string) $this->settings->getString(EApprovalSettingsService::PO_OVERSPEND_MODE, self::MODE_BLOCK)));

        return $raw === self::MODE_WARN ? self::MODE_WARN : self::MODE_BLOCK;
    }

    public function maxOverspendPercent(): int
    {
        if ($this->overspendMode() === self::MODE_BLOCK) {
            return 0;
        }

        $percent = $this->settings->getInt(EApprovalSettingsService::PO_MAX_OVERSPEND_PERCENT, 0);

        return max(0, min(25, $percent));
    }

    /**
     * @return array{
     *     blocked: bool,
     *     warning: string|null,
     *     strict_open_balance: float|null,
     *     policy_max_amount: float|null
     * }
     */
    public function evaluateLiquidationAmount(
        string $parentSubmissionId,
        float $amount,
        ?string $excludeChildSubmissionId = null,
    ): array {
        /** @var EApprovalSubmission|null $parent */
        $parent = EApprovalSubmission::query()
            ->with(['form', 'values.field'])
            ->find($parentSubmissionId);

        if ($parent === null || ! $parent->form instanceof EApprovalForm) {
            return $this->emptyEvaluation();
        }

        if ($this->formFamily($parent->form) !== 'cash_advance') {
            return $this->emptyEvaluation();
        }

        $requested = $this->fieldAmount($parent, 'requested_amount');
        if ($requested === null) {
            return [
                'blocked' => true,
                'warning' => null,
                'strict_open_balance' => null,
                'policy_max_amount' => null,
            ];
        }

        $strictOpen = $this->cashAdvances->openBalanceForParent($parentSubmissionId, $excludeChildSubmissionId);
        if ($strictOpen === null) {
            $committedOthers = $this->sumCommittedLiquidations($parentSubmissionId, $excludeChildSubmissionId);
            $strictOpen = max(0, round($requested - $committedOthers, 2));
        }

        $policyMaxAmount = round($strictOpen + ($requested * $this->liquidationMaxOverspendPercent() / 100), 2);

        if ($amount <= $strictOpen + 0.0001) {
            return [
                'blocked' => false,
                'warning' => null,
                'strict_open_balance' => $strictOpen,
                'policy_max_amount' => $policyMaxAmount,
            ];
        }

        if ($amount > $policyMaxAmount + 0.0001) {
            return [
                'blocked' => true,
                'warning' => null,
                'strict_open_balance' => $strictOpen,
                'policy_max_amount' => $policyMaxAmount,
            ];
        }

        if ($this->liquidationOverspendMode() === self::MODE_WARN) {
            return [
                'blocked' => false,
                'warning' => __(
                    'Liquidation total exceeds the cash advance open balance of :balance. Tenant policy allows up to :max with approver review.',
                    [
                        'balance' => number_format($strictOpen, 2, '.', ''),
                        'max' => number_format($policyMaxAmount, 2, '.', ''),
                    ],
                ),
                'strict_open_balance' => $strictOpen,
                'policy_max_amount' => $policyMaxAmount,
            ];
        }

        return [
            'blocked' => true,
            'warning' => null,
            'strict_open_balance' => $strictOpen,
            'policy_max_amount' => $policyMaxAmount,
        ];
    }

    /**
     * @return array{
     *     blocked: bool,
     *     warning: string|null,
     *     strict_open_balance: float|null,
     *     policy_max_amount: float|null
     * }
     */
    public function evaluatePurchaseOrderAmount(
        string $parentSubmissionId,
        float $amount,
        ?string $excludeChildSubmissionId = null,
    ): array {
        /** @var EApprovalSubmission|null $parent */
        $parent = EApprovalSubmission::query()
            ->with(['form', 'values.field'])
            ->find($parentSubmissionId);

        if ($parent === null || ! $parent->form instanceof EApprovalForm) {
            return $this->emptyEvaluation();
        }

        if ($this->formFamily($parent->form) !== 'purchase_requisition') {
            return $this->emptyEvaluation();
        }

        $estimated = $this->fieldAmount($parent, 'estimated_total');
        if ($estimated === null) {
            return [
                'blocked' => true,
                'warning' => null,
                'strict_open_balance' => null,
                'policy_max_amount' => null,
            ];
        }

        $strictOpen = $this->purchaseRequisitions->openBalanceForParent($parentSubmissionId, $excludeChildSubmissionId);
        if ($strictOpen === null) {
            $committedOthers = $this->sumCommittedPurchaseOrders($parentSubmissionId, $excludeChildSubmissionId);
            $strictOpen = max(0, round($estimated - $committedOthers, 2));
        }

        $policyMaxAmount = round($strictOpen + ($estimated * $this->maxOverspendPercent() / 100), 2);

        if ($amount <= $strictOpen + 0.0001) {
            return [
                'blocked' => false,
                'warning' => null,
                'strict_open_balance' => $strictOpen,
                'policy_max_amount' => $policyMaxAmount,
            ];
        }

        if ($amount > $policyMaxAmount + 0.0001) {
            return [
                'blocked' => true,
                'warning' => null,
                'strict_open_balance' => $strictOpen,
                'policy_max_amount' => $policyMaxAmount,
            ];
        }

        if ($this->overspendMode() === self::MODE_WARN) {
            return [
                'blocked' => false,
                'warning' => __(
                    'PO total exceeds the purchase requisition open balance of :balance. Tenant policy allows up to :max with approver review.',
                    [
                        'balance' => number_format($strictOpen, 2, '.', ''),
                        'max' => number_format($policyMaxAmount, 2, '.', ''),
                    ],
                ),
                'strict_open_balance' => $strictOpen,
                'policy_max_amount' => $policyMaxAmount,
            ];
        }

        return [
            'blocked' => true,
            'warning' => null,
            'strict_open_balance' => $strictOpen,
            'policy_max_amount' => $policyMaxAmount,
        ];
    }

    /**
     * @return array{blocked: bool, warning: string|null, strict_open_balance: float|null, policy_max_amount: float|null}
     */
    private function emptyEvaluation(): array
    {
        return [
            'blocked' => false,
            'warning' => null,
            'strict_open_balance' => null,
            'policy_max_amount' => null,
        ];
    }

    private function sumCommittedLiquidations(string $parentSubmissionId, ?string $excludeChildSubmissionId): float
    {
        $query = EApprovalSubmission::query()
            ->with(['values.field'])
            ->where('parent_submission_id', $parentSubmissionId)
            ->where('status', '<>', 'rejected');

        if ($excludeChildSubmissionId !== null && $excludeChildSubmissionId !== '') {
            $query->where('id', '<>', $excludeChildSubmissionId);
        }

        $total = 0.0;
        foreach ($query->get() as $child) {
            $total += $this->fieldAmount($child, 'total_reimbursement') ?? 0.0;
        }

        return round($total, 2);
    }

    private function sumCommittedPurchaseOrders(string $parentSubmissionId, ?string $excludeChildSubmissionId): float
    {
        $query = EApprovalSubmission::query()
            ->with(['values.field'])
            ->where('parent_submission_id', $parentSubmissionId)
            ->where('status', '<>', 'rejected');

        if ($excludeChildSubmissionId !== null && $excludeChildSubmissionId !== '') {
            $query->where('id', '<>', $excludeChildSubmissionId);
        }

        $total = 0.0;
        foreach ($query->get() as $child) {
            $total += $this->fieldAmount($child, 'total_amount') ?? 0.0;
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

    private function formFamily(EApprovalForm $form): ?string
    {
        $metadata = is_array($form->metadata_json) ? $form->metadata_json : [];
        $family = $metadata['form_family'] ?? null;

        return is_string($family) && trim($family) !== '' ? trim($family) : null;
    }
}
