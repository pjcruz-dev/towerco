<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Support\EApprovalFormPolicySupport;
use App\Modules\EApproval\Support\EApprovalFormWorkflowRulesSupport;

final class EApprovalApprovalPolicyRequiredApproverFields
{
    public function __construct(
        private readonly EApprovalApprovalPolicyService $policies,
        private readonly EApprovalApprovalPolicyRuleMatcher $matcher,
        private readonly EApprovalApprovalPolicyContextExtractor $contextExtractor,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     * @return list<string>|null Null = use each field's validation.required flag.
     */
    public function fieldNamesForValidation(EApprovalForm $form, array $values): ?array
    {
        if (EApprovalFormWorkflowRulesSupport::usesRulesMode($form)) {
            return $this->fieldNamesFromFormRules($form, $values);
        }

        if (! EApprovalFormPolicySupport::usesApprovalPolicy($form)) {
            return null;
        }

        if (EApprovalFormPolicySupport::prefersFormWorkflowSteps($form)) {
            return null;
        }

        $version = $this->policies->publishedVersion();
        if ($version === null) {
            return null;
        }

        $context = $this->contextExtractor->extract($form, $values);
        $match = $this->matcher->match($version, $context);
        if ($match === null) {
            return [];
        }

        $config = $version->config();
        $profiles = is_array($config['workflow_profiles'] ?? null) ? $config['workflow_profiles'] : [];
        $profile = is_array($profiles[$match['profile_key']] ?? null) ? $profiles[$match['profile_key']] : null;
        if ($profile === null) {
            return [];
        }

        $names = [];
        foreach ($profile['steps'] ?? [] as $step) {
            if (! is_array($step)) {
                continue;
            }

            if (($step['type'] ?? null) === 'field' && ! empty($step['approverId'])) {
                $names[] = (string) $step['approverId'];
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @param  array<string, mixed>  $values
     * @return list<string>|null
     */
    private function fieldNamesFromFormRules(EApprovalForm $form, array $values): ?array
    {
        $matcher = app(EApprovalFormWorkflowRulesMatcher::class);
        $rules = EApprovalFormWorkflowRulesSupport::rulesFromForm($form);
        $match = $matcher->match($rules, $values);
        $stepDefinitions = is_array($match['rule'] ?? null)
            ? (is_array($match['rule']['steps'] ?? null) ? $match['rule']['steps'] : [])
            : EApprovalFormWorkflowRulesSupport::defaultStepsFromForm($form);

        $names = [];
        foreach ($stepDefinitions as $step) {
            if (! is_array($step)) {
                continue;
            }

            if (($step['type'] ?? null) === 'field' && ! empty($step['approverId'])) {
                $names[] = (string) $step['approverId'];
            }
        }

        return array_values(array_unique($names));
    }
}
