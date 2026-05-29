<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Data;

/**
 * Gate approval policies for every phase in playbook timeline templates.
 *
 * @phpstan-type GatePolicy array{enabled: bool, chain: list<string>}
 */
final class RolloutGateApprovalPolicyFullCoverage
{
    /**
     * Build full gate-approval map from playbook timeline templates (all phases enabled).
     *
     * @param  array<string, list<array<string, mixed>>>  $timelineTemplates
     * @return array<string, array<string, GatePolicy>>
     */
    public static function fromTimelineTemplates(array $timelineTemplates): array
    {
        $policies = [];

        foreach ($timelineTemplates as $templateKey => $phases) {
            if (! is_array($phases)) {
                continue;
            }

            $templatePolicies = [];

            foreach ($phases as $phase) {
                if (! is_array($phase)) {
                    continue;
                }

                $phaseKey = (string) ($phase['phase_key'] ?? '');
                if ($phaseKey === '') {
                    continue;
                }

                $templatePolicies[$phaseKey] = [
                    'enabled' => true,
                    'chain' => self::chainForPhase($phaseKey, (string) ($phase['owner_role'] ?? '')),
                ];
            }

            $policies[$templateKey] = $templatePolicies;
        }

        return $policies;
    }

    /**
     * Uses playbook v2.0.0 timeline structure with full approval on every phase.
     *
     * @return array<string, array<string, GatePolicy>>
     */
    public static function forPlaybookV2(): array
    {
        $payload = RolloutPlaybookV2Definition::payload();
        $templates = $payload['timeline_templates'] ?? [];

        if (! is_array($templates)) {
            return [];
        }

        /** @var array<string, list<array<string, mixed>>> $templates */
        return self::fromTimelineTemplates($templates);
    }

    /**
     * @return list<string>
     */
    private static function chainForPhase(string $phaseKey, string $ownerRole): array
    {
        return match ($phaseKey) {
            'site_hunting' => ['saq', 'pmo'],
            'tssr_creation' => ['saq_engineering', 'saq', 'pmo'],
            'tssr_mno_approval' => ['mno', 'pmo'],
            'pre_construction' => ['engineering', 'pmo'],
            'permitting' => ['saq', 'engineering', 'pmo'],
            'construction' => ['cme', 'pmo', 'tenant_admin'],
            'site_license' => ['pmo', 'tenant_admin'],
            'implementation' => ['cme', 'pmo'],
            default => match ($ownerRole) {
                'saq' => ['saq', 'pmo'],
                'saq_engineering' => ['saq_engineering', 'saq', 'pmo'],
                'mno' => ['mno', 'pmo'],
                'engineering' => ['engineering', 'pmo'],
                'cme', 'cme_power' => ['cme', 'pmo'],
                'bd', 'bd_pmo' => ['pmo'],
                default => ['pmo'],
            },
        };
    }
}
