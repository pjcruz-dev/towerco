<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalApprovalPolicyVersion;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalWorkflowStep;
use App\Modules\EApproval\Support\EApprovalFormPolicySupport;
use App\Modules\EApproval\Support\EApprovalFormWorkflowRulesSupport;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class EApprovalApprovalPolicyCompilerService
{
    public function __construct(
        private readonly EApprovalApprovalPolicyService $policies,
        private readonly EApprovalApprovalPolicyRuleMatcher $matcher,
        private readonly EApprovalApprovalPolicyContextExtractor $contextExtractor,
        private readonly EApprovalApprovalPolicyStepPersister $stepPersister,
        private readonly EApprovalFormWorkflowRulesCompilerService $formRulesCompiler,
        private readonly EApprovalConditionalWorkflowCompilerService $conditionalCompiler,
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
        if (EApprovalFormWorkflowRulesSupport::usesRulesMode($form)) {
            return $this->conditionalCompiler->compileForSubmit($form, $values, $submissionId);
        }

        $baseSnapshot = $this->baseWorkflowSnapshot($form);

        if (
            ! EApprovalFormPolicySupport::usesApprovalPolicy($form)
            || EApprovalFormPolicySupport::prefersFormWorkflowSteps($form)
        ) {
            return $this->conditionalCompiler->compileForSubmit($form, $values, $submissionId);
        }

        $version = $this->policies->publishedVersion();
        if (! $version instanceof EApprovalApprovalPolicyVersion) {
            throw ValidationException::withMessages([
                'approval_policy' => [__('No published approval policy is configured for this tenant.')],
            ]);
        }

        $context = $this->contextExtractor->extract($form, $values);
        $match = $this->matcher->match($version, $context);
        if ($match === null) {
            throw ValidationException::withMessages([
                'approval_policy' => [__('No approval policy rule matched this submission.')],
            ]);
        }

        $config = $version->config();
        $profiles = is_array($config['workflow_profiles'] ?? null) ? $config['workflow_profiles'] : [];
        $profileKey = $match['profile_key'];
        $profile = is_array($profiles[$profileKey] ?? null) ? $profiles[$profileKey] : null;
        if ($profile === null) {
            throw ValidationException::withMessages([
                'approval_policy' => [__('Workflow profile :profile is not defined.', ['profile' => $profileKey])],
            ]);
        }

        $templateId = (string) ($form->workflowTemplate?->id ?? '');
        if ($templateId === '') {
            throw ValidationException::withMessages([
                'form' => [__('Form workflow template is missing.')],
            ]);
        }

        $stepDefinitions = is_array($profile['steps'] ?? null) ? $profile['steps'] : [];
        $steps = $this->stepPersister->persist($templateId, $submissionId, $stepDefinitions);

        $snapshot = [
            'template_id' => $templateId,
            'approval_policy_version_id' => (string) $version->id,
            'approval_policy_label' => $version->label(),
            'workflow_profile_key' => $profileKey,
            'workflow_profile_label' => (string) ($profile['label'] ?? $profileKey),
            'policy_context' => $context,
            'matched_rule' => $match['rule'],
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
            'approval_policy_version_id' => (string) $version->id,
            'approval_policy_label' => $version->label(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function baseWorkflowSnapshot(EApprovalForm $form): array
    {
        $form->loadMissing('workflowTemplate.steps');
        $steps = $form->workflowTemplate?->steps ?? collect();

        return [
            'template_id' => $form->workflowTemplate?->id,
            'steps' => $steps->map(static fn (EApprovalWorkflowStep $step) => [
                'id' => $step->id,
                'step_order' => $step->step_order,
                'approver_type' => $step->approver_type,
                'approver_id' => $step->approver_id,
                'condition' => $step->condition,
            ])->values()->all(),
        ];
    }
}
