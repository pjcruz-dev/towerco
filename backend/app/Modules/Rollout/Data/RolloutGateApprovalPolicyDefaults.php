<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Data;

/**
 * Platform default gate approval policies (Phase A pilot gates).
 *
 * @phpstan-type GatePolicy array{enabled: bool, chain: list<string>}
 */
final class RolloutGateApprovalPolicyDefaults
{
    /** @var list<string> */
    public const PILOT_PHASE_KEYS = [
        'site_hunting',
        'pre_assessment',
        'moc_col',
        'tssr_creation',
        'tssr_mno_approval',
        'pre_construction',
        'permitting',
        'skom',
        'construction',
        'site_license',
        'handover_operations',
    ];

    /**
     * @return array<string, array<string, GatePolicy>>
     */
    public static function all(): array
    {
        $pilot = [
            'site_hunting' => [
                'enabled' => true,
                'chain' => ['saq', 'pmo'],
            ],
            'pre_assessment' => [
                'enabled' => true,
                'chain' => ['mno', 'pmo'],
            ],
            'moc_col' => [
                'enabled' => true,
                'chain' => ['saq', 'pmo'],
            ],
            'tssr_creation' => [
                'enabled' => true,
                'chain' => ['saq_engineering', 'saq', 'pmo'],
            ],
            'tssr_mno_approval' => [
                'enabled' => true,
                'chain' => ['mno', 'pmo'],
            ],
            'pre_construction' => [
                'enabled' => true,
                'chain' => ['engineering', 'pmo'],
            ],
            'permitting' => [
                'enabled' => true,
                'chain' => ['saq', 'engineering', 'pmo'],
            ],
            'skom' => [
                'enabled' => true,
                'chain' => ['cme', 'pmo'],
            ],
            'construction' => [
                'enabled' => true,
                'chain' => ['cme', 'pmo', 'tenant_admin'],
            ],
            'site_license' => [
                'enabled' => true,
                'chain' => ['saq', 'pmo', 'tenant_admin'],
            ],
            'handover_operations' => [
                'enabled' => true,
                'chain' => ['pmo', 'tenant_admin'],
            ],
        ];

        return [
            'bts' => $pilot,
            'rtb' => $pilot,
            'colocation' => [
                'site_license' => [
                    'enabled' => true,
                    'chain' => ['pmo', 'tenant_admin'],
                ],
                'implementation' => [
                    'enabled' => true,
                    'chain' => ['cme', 'pmo'],
                ],
            ],
        ];
    }

    /**
     * @return GatePolicy|null
     */
    public static function forPhase(string $templateKey, string $phaseKey): ?array
    {
        $all = self::all();

        return $all[$templateKey][$phaseKey] ?? null;
    }
}
