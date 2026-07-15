<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalWorkflowStep;
use Illuminate\Support\Collection;

final class EApprovalFormPolicySupport
{
    /** @var list<string> */
    public const POLICY_CAPABLE_FORM_FAMILIES = [
        'purchase_requisition',
        'purchase_order',
        'ap_invoice',
    ];

    public static function usesApprovalPolicy(EApprovalForm $form): bool
    {
        $metadata = is_array($form->metadata_json) ? $form->metadata_json : [];

        return (bool) ($metadata['use_approval_policy'] ?? false);
    }

    public static function isPolicyCapableForm(EApprovalForm $form): bool
    {
        $family = self::documentFamily($form);

        return $family !== null && in_array($family, self::POLICY_CAPABLE_FORM_FAMILIES, true);
    }

    /**
     * Resolved workflow mode for policy-capable procurement forms.
     * All other forms always use Workflow tab steps.
     */
    public static function effectiveWorkflowSource(EApprovalForm $form): string
    {
        if (! self::usesApprovalPolicy($form) || ! self::isPolicyCapableForm($form)) {
            return 'form';
        }

        $metadata = is_array($form->metadata_json) ? $form->metadata_json : [];
        $explicit = $metadata['workflow_source'] ?? null;
        if ($explicit === 'form' || $explicit === 'policy') {
            return $explicit;
        }

        return self::fixedUserWorkflowSteps($form)->isNotEmpty() ? 'form' : 'policy';
    }

    /**
     * When a PR/PO/AP form Workflow tab defines fixed-user steps, those steps take precedence over
     * the tenant DOA approval policy matrix at submit time.
     */
    public static function prefersFormWorkflowSteps(EApprovalForm $form): bool
    {
        if (! self::usesApprovalPolicy($form) || ! self::isPolicyCapableForm($form)) {
            return false;
        }

        return self::effectiveWorkflowSource($form) === 'form';
    }

    /**
     * @param  list<array<string, mixed>>  $stepsPayload
     */
    public static function inferWorkflowSourceFromStepsPayload(array $stepsPayload): string
    {
        foreach ($stepsPayload as $step) {
            if (! is_array($step)) {
                continue;
            }

            $type = self::normalizeApproverType((string) ($step['type'] ?? $step['approver_type'] ?? ''));
            $approverId = trim((string) ($step['approverId'] ?? $step['approver_id'] ?? ''));

            if ($type === 'user' && $approverId !== '') {
                return 'form';
            }
        }

        return 'policy';
    }

    /**
     * @return Collection<int, EApprovalWorkflowStep>
     */
    public static function fixedUserWorkflowSteps(EApprovalForm $form): Collection
    {
        $form->loadMissing('workflowTemplate.steps');

        return ($form->workflowTemplate?->steps ?? collect())
            ->filter(static function (EApprovalWorkflowStep $step): bool {
                if (self::normalizeApproverType((string) $step->approver_type) !== 'user') {
                    return false;
                }

                return trim((string) ($step->approver_id ?? '')) !== '';
            })
            ->sortBy('step_order')
            ->values();
    }

    public static function normalizeApproverType(string $type): string
    {
        return match (strtolower(trim($type))) {
            'fixed', 'fixed_user', 'fixeduser' => 'user',
            'approver_field', 'from_field', 'from_approver_field' => 'field',
            'direct_manager', 'entra_manager' => 'manager',
            'field_map', 'map_field', 'mapped_field' => 'field_map',
            default => strtolower(trim($type)),
        };
    }

    public static function documentFamily(EApprovalForm $form): ?string
    {
        $metadata = is_array($form->metadata_json) ? $form->metadata_json : [];
        $family = trim((string) ($metadata['form_family'] ?? ''));

        return $family !== '' ? $family : null;
    }
}
