<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Rollout\Data\RolloutGateApprovalChainRoles;
use App\Modules\Rollout\Data\RolloutGateApprovalPolicyDefaults;
use App\Modules\Rollout\Models\TenantRolloutPlaybookConfig;

final class RolloutGateApprovalPolicyService
{
    /**
     * @return array<string, array<string, array{enabled: bool, chain: list<string>}>>
     */
    public function mergedPolicies(?TenantRolloutPlaybookConfig $config = null): array
    {
        $defaults = RolloutGateApprovalPolicyDefaults::all();
        $overrides = $config?->gate_approval_policies ?? [];

        if (! is_array($overrides) || $overrides === []) {
            return $defaults;
        }

        $merged = $defaults;

        foreach ($overrides as $templateKey => $phases) {
            if (! is_array($phases)) {
                continue;
            }

            foreach ($phases as $phaseKey => $policy) {
                if (! is_array($policy)) {
                    continue;
                }

                $merged[$templateKey][$phaseKey] = array_merge(
                    $merged[$templateKey][$phaseKey] ?? ['enabled' => false, 'chain' => []],
                    array_filter([
                        'enabled' => array_key_exists('enabled', $policy) ? (bool) $policy['enabled'] : null,
                        'chain' => isset($policy['chain']) && is_array($policy['chain'])
                            ? RolloutGateApprovalChainRoles::sanitize($policy['chain'])
                            : null,
                    ], static fn ($value) => $value !== null),
                );
            }
        }

        return $merged;
    }

    /**
     * @return array{enabled: bool, chain: list<string>}|null
     */
    public function policyForPhase(string $projectType, string $phaseKey, ?TenantRolloutPlaybookConfig $config = null): ?array
    {
        $templateKey = match (strtolower($projectType)) {
            'rtb' => 'rtb',
            'colocation', 'colo' => 'colocation',
            default => 'bts',
        };

        $policies = $this->mergedPolicies($config);
        $policy = $policies[$templateKey][$phaseKey] ?? null;

        if ($policy === null || ! ($policy['enabled'] ?? false)) {
            return null;
        }

        $chain = $policy['chain'] ?? [];
        if ($chain === []) {
            return null;
        }

        return [
            'enabled' => true,
            'chain' => $chain,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $policies
     */
    public function saveTenantPolicies(array $policies): void
    {
        $config = TenantRolloutPlaybookConfig::query()->firstOrFail();
        $config->gate_approval_policies = $policies;
        $config->save();
    }
}
