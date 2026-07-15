<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\EApproval\Services\EApprovalFinanceProcurementPolicyService;

final class ProcurementFinancialPolicyService
{
    public const SETTINGS_KEY = 'procurement_policy';

    public function __construct(
        private readonly ProcurementOneSettingsService $settings,
        private readonly ProcurementPrBudgetPolicyService $prBudgetPolicy,
        private readonly EApprovalFinanceProcurementPolicyService $eApprovalFinancePolicy,
    ) {}

    /**
     * Unified procurement financial policy for UI and metadata.
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $stored = $this->settings->getJson(self::SETTINGS_KEY);
        $prBudget = $this->prBudgetPolicy->policy();
        $finance = $this->eApprovalFinancePolicy->snapshot();

        return [
            'budget' => [
                'enabled' => (bool) ($stored['budget']['enabled'] ?? $prBudget['enabled']),
                'mode' => (string) ($stored['budget']['mode'] ?? $prBudget['mode']),
            ],
            'po_overspend' => [
                'mode' => (string) ($stored['po_overspend']['mode'] ?? $finance['po_overspend_mode']),
                'max_overspend_percent' => (int) ($stored['po_overspend']['max_overspend_percent'] ?? $finance['po_max_overspend_percent']),
            ],
            'liquidation' => [
                'requires_parent' => (bool) ($stored['liquidation']['requires_parent'] ?? $finance['liquidation_requires_parent']),
                'overspend_mode' => (string) ($stored['liquidation']['overspend_mode'] ?? $finance['liquidation_overspend_mode']),
                'max_overspend_percent' => (int) ($stored['liquidation']['max_overspend_percent'] ?? $finance['liquidation_max_overspend_percent']),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function validateAndNormalize(array $input): array
    {
        $budget = is_array($input['budget'] ?? null) ? $input['budget'] : [];
        $poOverspend = is_array($input['po_overspend'] ?? null) ? $input['po_overspend'] : [];
        $liquidation = is_array($input['liquidation'] ?? null) ? $input['liquidation'] : [];

        $normalizedBudget = $this->prBudgetPolicy->validateAndNormalize([
            'enabled' => $budget['enabled'] ?? false,
            'mode' => $budget['mode'] ?? 'warn',
        ]);

        $poMode = (string) ($poOverspend['mode'] ?? 'block');
        $liqMode = (string) ($liquidation['overspend_mode'] ?? 'block');

        return [
            'budget' => $normalizedBudget,
            'po_overspend' => [
                'mode' => in_array($poMode, ['warn', 'block'], true) ? $poMode : 'block',
                'max_overspend_percent' => max(0, min(25, (int) ($poOverspend['max_overspend_percent'] ?? 0))),
            ],
            'liquidation' => [
                'requires_parent' => (bool) ($liquidation['requires_parent'] ?? true),
                'overspend_mode' => in_array($liqMode, ['warn', 'block'], true) ? $liqMode : 'block',
                'max_overspend_percent' => max(0, min(25, (int) ($liquidation['max_overspend_percent'] ?? 0))),
            ],
        ];
    }

    public function persist(array $normalized): void
    {
        $this->settings->setJson(self::SETTINGS_KEY, $normalized);
        $this->settings->setJson(ProcurementPrBudgetPolicyService::SETTINGS_KEY, $normalized['budget']);
    }
}
