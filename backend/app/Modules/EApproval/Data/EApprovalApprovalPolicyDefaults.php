<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Data;

/**
 * Default tenant approval policy matrix and workflow profiles (Phase 1).
 */
final class EApprovalApprovalPolicyDefaults
{
    public const POLICY_KEY = 'tenant_default';

    public const THRESHOLD_PR_HIGH_VALUE = 500_000;

    public const THRESHOLD_PO_HIGH_VALUE = 100_000;

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
            'currency' => 'PHP',
            'workflow_profiles' => self::workflowProfiles(),
            'rules' => self::rules(),
            'default_profiles' => [
                'purchase_requisition' => 'pr_standard',
                'purchase_order' => 'po_standard',
            ],
        ];
    }

    /**
     * @return array<string, array{label: string, steps: list<array<string, mixed>>}>
     */
    public static function workflowProfiles(): array
    {
        return [
            'pr_standard' => [
                'label' => 'PR Standard',
                'steps' => [
                    ['type' => 'manager', 'step_order' => 1],
                    ['type' => 'role', 'approverId' => 'tenant_admin', 'step_order' => 2],
                ],
            ],
            'pr_capex' => [
                'label' => 'PR CapEx / High value',
                'steps' => [
                    ['type' => 'manager', 'step_order' => 1],
                    ['type' => 'role', 'approverId' => 'tenant_admin', 'step_order' => 2],
                    ['type' => 'role', 'approverId' => 'finance', 'step_order' => 3],
                ],
            ],
            'po_standard' => [
                'label' => 'PO Standard',
                'steps' => [
                    ['type' => 'manager', 'step_order' => 1],
                    ['type' => 'role', 'approverId' => 'tenant_admin', 'step_order' => 2],
                ],
            ],
            'po_high_value' => [
                'label' => 'PO High value',
                'steps' => [
                    ['type' => 'manager', 'step_order' => 1],
                    ['type' => 'role', 'approverId' => 'tenant_admin', 'step_order' => 2],
                    ['type' => 'role', 'approverId' => 'finance', 'step_order' => 3],
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function rules(): array
    {
        return [
            [
                'priority' => 100,
                'document_family' => 'purchase_requisition',
                'amount_field' => 'estimated_total',
                'amount_min' => self::THRESHOLD_PR_HIGH_VALUE + 0.01,
                'amount_max' => null,
                'department' => null,
                'category' => null,
                'urgency' => null,
                'workflow_profile' => 'pr_capex',
            ],
            [
                'priority' => 50,
                'document_family' => 'purchase_requisition',
                'amount_field' => 'estimated_total',
                'amount_min' => null,
                'amount_max' => self::THRESHOLD_PR_HIGH_VALUE,
                'department' => null,
                'category' => null,
                'urgency' => null,
                'workflow_profile' => 'pr_standard',
            ],
            [
                'priority' => 100,
                'document_family' => 'purchase_order',
                'amount_field' => 'total_amount',
                'amount_min' => self::THRESHOLD_PO_HIGH_VALUE + 0.01,
                'amount_max' => null,
                'department' => null,
                'category' => null,
                'urgency' => null,
                'workflow_profile' => 'po_high_value',
            ],
            [
                'priority' => 50,
                'document_family' => 'purchase_order',
                'amount_field' => 'total_amount',
                'amount_min' => null,
                'amount_max' => self::THRESHOLD_PO_HIGH_VALUE,
                'department' => null,
                'category' => null,
                'urgency' => null,
                'workflow_profile' => 'po_standard',
            ],
        ];
    }
}
