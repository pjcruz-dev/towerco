<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalWorkflowStep;
use App\Modules\EApproval\Support\EApprovalFormWorkflowRulesSupport;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class EApprovalFormWorkflowRulesCompilerService
{
    public function __construct(
        private readonly EApprovalFormWorkflowRulesMatcher $matcher,
        private readonly EApprovalApprovalPolicyStepPersister $stepPersister,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     * @return array{
     *   applied: bool,
     *   steps: Collection<int, EApprovalWorkflowStep>,
     *   snapshot: array<string, mixed>,
     *   approval_policy_version_id: string|null,
     *   approval_policy_label: string|null
     * }
     */
    public function compileForSubmit(EApprovalForm $form, array $values, string $submissionId): array
    {
        $rules = EApprovalFormWorkflowRulesSupport::rulesFromForm($form);
        $match = $this->matcher->match($rules, $values);
        $matchedRule = $match['rule'] ?? null;

        $stepDefinitions = is_array($matchedRule)
            ? (is_array($matchedRule['steps'] ?? null) ? $matchedRule['steps'] : [])
            : EApprovalFormWorkflowRulesSupport::defaultStepsFromForm($form);

        if ($stepDefinitions === []) {
            throw ValidationException::withMessages([
                'workflow' => [__('No workflow rule matched this submission and no default approval chain is configured.')],
            ]);
        }

        $templateId = $this->ensureWorkflowTemplateId($form);
        $steps = $this->stepPersister->persist($templateId, $submissionId, $stepDefinitions);

        $snapshot = [
            'template_id' => $templateId,
            'workflow_mode' => EApprovalFormWorkflowRulesSupport::MODE_RULES,
            'workflow_rule_id' => is_array($matchedRule) ? ($matchedRule['id'] ?? null) : null,
            'workflow_rule_label' => is_array($matchedRule) ? ($matchedRule['label'] ?? null) : __('Default chain'),
            'matched_rule' => $matchedRule,
            'policy_context' => $values,
            'steps' => $steps->map(static fn (EApprovalWorkflowStep $step) => [
                'id' => $step->id,
                'step_order' => $step->step_order,
                'approver_type' => $step->approver_type,
                'approver_id' => $step->approver_id,
                'condition' => $step->condition,
            ])->values()->all(),
        ];

        return [
            'applied' => true,
            'steps' => $steps,
            'snapshot' => $snapshot,
            'approval_policy_version_id' => null,
            'approval_policy_label' => is_array($matchedRule)
                ? (string) ($matchedRule['label'] ?? __('Form workflow rule'))
                : __('Default approval chain'),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function preview(EApprovalForm $form, array $values): array
    {
        $rules = EApprovalFormWorkflowRulesSupport::rulesFromForm($form);
        $match = $this->matcher->match($rules, $values);
        $matchedRule = $match['rule'] ?? null;
        $stepDefinitions = is_array($matchedRule)
            ? (is_array($matchedRule['steps'] ?? null) ? $matchedRule['steps'] : [])
            : EApprovalFormWorkflowRulesSupport::defaultStepsFromForm($form);

        return [
            'workflow_mode' => EApprovalFormWorkflowRulesSupport::MODE_RULES,
            'matched_rule_id' => is_array($matchedRule) ? ($matchedRule['id'] ?? null) : null,
            'matched_rule_label' => is_array($matchedRule)
                ? ($matchedRule['label'] ?? null)
                : ($stepDefinitions !== [] ? __('Default chain') : null),
            'step_definitions' => $stepDefinitions,
            'resolved_steps' => $this->describeSteps($stepDefinitions, $values),
        ];
    }

    private function ensureWorkflowTemplateId(EApprovalForm $form): string
    {
        $form->loadMissing('workflowTemplate');
        $templateId = (string) ($form->workflowTemplate?->id ?? '');
        if ($templateId !== '') {
            return $templateId;
        }

        throw ValidationException::withMessages([
            'form' => [__('Form workflow template is missing.')],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $stepDefinitions
     * @param  array<string, mixed>  $values
     * @return list<array<string, mixed>>
     */
    private function describeSteps(array $stepDefinitions, array $values): array
    {
        $resolver = app(EApprovalFormWorkflowStepPreviewResolver::class);
        $described = [];

        foreach (array_values($stepDefinitions) as $index => $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $described[] = $resolver->describe($definition, $values, $index + 1);
        }

        return $described;
    }
}
