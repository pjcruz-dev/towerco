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
        private readonly EApprovalCashAdvanceService $cashAdvances,
    ) {}

    /**
     * @return array{
     *     liquidation_requires_parent: bool,
     *     liquidation_overspend_mode: string,
     *     liquidation_max_overspend_percent: int
     * }
     */
    public function snapshot(): array
    {
        return [
            'liquidation_requires_parent' => $this->liquidationRequiresParent(),
            'liquidation_overspend_mode' => 'allow',
            'liquidation_max_overspend_percent' => 0,
            'liquidation_due_days' => $this->settings->getIntAllowingZero(EApprovalSettingsService::LIQUIDATION_DUE_DAYS, 0),
            'liquidation_remind_days' => $this->settings->getIntAllowingZero(EApprovalSettingsService::LIQUIDATION_REMIND_DAYS, 0),
            'block_new_cash_advance' => $this->settings->getBool(EApprovalSettingsService::BLOCK_NEW_CASH_ADVANCE, false),
            'vat_rate_percent' => $this->settings->getIntAllowingZero(EApprovalSettingsService::VAT_RATE_PERCENT, 12),
            'vat_amount_includes_vat' => $this->settings->getBool(EApprovalSettingsService::VAT_AMOUNT_INCLUDES_VAT, true),
            'per_diem_max_per_day' => $this->settings->getOptionalAmount(EApprovalSettingsService::PER_DIEM_MAX_PER_DAY),
            'hotel_accommodation_max' => $this->settings->getOptionalAmount(EApprovalSettingsService::HOTEL_ACCOMMODATION_MAX),
        ];
    }

    public function releaseOffice(): string
    {
        $raw = strtolower(trim((string) $this->settings->getString(EApprovalSettingsService::RELEASE_OFFICE, 'finance')));

        return in_array($raw, ['finance', 'treasury', 'either'], true) ? $raw : 'finance';
    }

    public function roleName(string $key, string $default): string
    {
        $raw = trim((string) $this->settings->getString($key, $default));

        return $raw !== '' ? $raw : $default;
    }

    public function vatRatePercent(): int
    {
        return max(0, min(100, $this->settings->getIntAllowingZero(EApprovalSettingsService::VAT_RATE_PERCENT, 12)));
    }

    public function amountIncludesVat(): bool
    {
        return $this->settings->getBool(EApprovalSettingsService::VAT_AMOUNT_INCLUDES_VAT, true);
    }

    public function perDiemMaxPerDay(): ?float
    {
        return $this->settings->getOptionalAmount(EApprovalSettingsService::PER_DIEM_MAX_PER_DAY);
    }

    public function hotelAccommodationMax(): ?float
    {
        return $this->settings->getOptionalAmount(EApprovalSettingsService::HOTEL_ACCOMMODATION_MAX);
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

        $excess = round($amount - $strictOpen, 2);

        return [
            'blocked' => false,
            'warning' => __(
                'Amount to pay the employee: :amount. The CFO approves this difference on the liquidation.',
                ['amount' => number_format($excess, 2, '.', '')],
            ),
            'strict_open_balance' => $strictOpen,
            'policy_max_amount' => $amount,
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
