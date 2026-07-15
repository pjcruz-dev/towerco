<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalWorkflowStep;
use App\Modules\EApproval\Support\EApprovalWorkflowConditionEvaluator;
use App\Modules\EApproval\Support\EApprovalWorkflowStepDefinitionSupport;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class EApprovalConditionalWorkflowCompilerService
{
    public function __construct(
        private readonly EApprovalWorkflowConditionEvaluator $evaluator,
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
        $definitions = EApprovalWorkflowStepDefinitionSupport::definitionsFromForm($form);
        $activeDefinitions = $this->filterActiveDefinitions($definitions, $values);

        if ($activeDefinitions === []) {
            throw ValidationException::withMessages([
                'workflow' => [__('No approval steps matched this submission. Review workflow conditions or add an always-on step.')],
            ]);
        }

        $templateId = $this->ensureWorkflowTemplateId($form);
        $steps = $this->stepPersister->persist($templateId, $submissionId, $activeDefinitions);

        $snapshot = [
            'template_id' => $templateId,
            'workflow_mode' => 'conditional_steps',
            'policy_context' => $values,
            'step_definitions' => $definitions,
            'active_step_definitions' => $activeDefinitions,
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
            'approval_policy_label' => __('Form workflow'),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function preview(EApprovalForm $form, array $values): array
    {
        $definitions = EApprovalWorkflowStepDefinitionSupport::definitionsFromForm($form);
        $activeDefinitions = $this->filterActiveDefinitions($definitions, $values);

        return [
            'workflow_mode' => 'conditional_steps',
            'matched_rule_id' => null,
            'matched_rule_label' => $activeDefinitions === []
                ? null
                : __('Matched :count of :total approval steps', [
                    'count' => count($activeDefinitions),
                    'total' => count($definitions),
                ]),
            'step_definitions' => $definitions,
            'active_step_definitions' => $activeDefinitions,
            'resolved_steps' => $this->describeSteps($activeDefinitions, $values, $form),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $definitions
     * @param  array<string, mixed>  $values
     * @return list<array<string, mixed>>
     */
    public function filterActiveDefinitions(array $definitions, array $values): array
    {
        $active = [];

        foreach (array_values($definitions) as $index => $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $when = EApprovalWorkflowStepDefinitionSupport::whenFromDefinition(
                $definition,
                is_array($definition['condition'] ?? null) ? $definition['condition'] : [],
            );

            if (! $this->evaluator->matchesAll($when, $values)) {
                continue;
            }

            $active[] = [
                ...$definition,
                'step_order' => count($active) + 1,
            ];
        }

        return $active;
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
    private function describeSteps(array $stepDefinitions, array $values, EApprovalForm $form): array
    {
        $resolver = app(EApprovalFormWorkflowStepPreviewResolver::class);
        $described = [];

        foreach (array_values($stepDefinitions) as $index => $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $described[] = $resolver->describe($definition, $values, $index + 1, $form);
        }

        return $described;
    }
}
