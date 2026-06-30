<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalApprovalPolicyVersion;

final class EApprovalApprovalPolicyRuleMatcher
{
    /**
     * @param  array<string, mixed>  $context
     * @return array{profile_key: string, rule: array<string, mixed>}|null
     */
    public function match(EApprovalApprovalPolicyVersion $version, array $context): ?array
    {
        $config = $version->config();
        $rules = is_array($config['rules'] ?? null) ? $config['rules'] : [];
        if ($rules === []) {
            return $this->defaultProfile($config, $context);
        }

        usort($rules, static fn (array $a, array $b): int => (int) ($b['priority'] ?? 0) <=> (int) ($a['priority'] ?? 0));

        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            if ($this->ruleMatches($rule, $context)) {
                $profileKey = trim((string) ($rule['workflow_profile'] ?? ''));
                if ($profileKey === '') {
                    continue;
                }

                return ['profile_key' => $profileKey, 'rule' => $rule];
            }
        }

        return $this->defaultProfile($config, $context);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     * @return array{profile_key: string, rule: array<string, mixed>}|null
     */
    private function defaultProfile(array $config, array $context): ?array
    {
        $family = trim((string) ($context['document_family'] ?? ''));
        if ($family === '') {
            return null;
        }

        $defaults = is_array($config['default_profiles'] ?? null) ? $config['default_profiles'] : [];
        $profileKey = trim((string) ($defaults[$family] ?? ''));
        if ($profileKey === '') {
            return null;
        }

        return ['profile_key' => $profileKey, 'rule' => ['workflow_profile' => $profileKey, 'default' => true]];
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  array<string, mixed>  $context
     */
    private function ruleMatches(array $rule, array $context): bool
    {
        $family = trim((string) ($rule['document_family'] ?? ''));
        if ($family !== '' && $family !== (string) ($context['document_family'] ?? '')) {
            return false;
        }

        foreach (['department', 'category', 'urgency'] as $dimension) {
            $expected = $rule[$dimension] ?? null;
            if ($expected === null || $expected === '') {
                continue;
            }

            if ((string) $expected !== (string) ($context[$dimension] ?? '')) {
                return false;
            }
        }

        $amount = $context['amount'] ?? null;
        if ($amount === null || ! is_numeric($amount)) {
            return $rule['amount_min'] === null && $rule['amount_max'] === null;
        }

        $numericAmount = (float) $amount;
        if ($rule['amount_min'] !== null && $rule['amount_min'] !== '' && $numericAmount < (float) $rule['amount_min']) {
            return false;
        }

        if ($rule['amount_max'] !== null && $rule['amount_max'] !== '' && $numericAmount > (float) $rule['amount_max']) {
            return false;
        }

        return true;
    }
}
