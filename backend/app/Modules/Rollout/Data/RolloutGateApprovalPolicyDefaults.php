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
        'tssr_creation',
        'pre_construction',
        'permitting',
        'construction',
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
            'tssr_creation' => [
                'enabled' => true,
                'chain' => ['saq_engineering', 'saq', 'pmo'],
            ],
            'pre_construction' => [
                'enabled' => true,
                'chain' => ['engineering', 'pmo'],
            ],
            'permitting' => [
                'enabled' => true,
                'chain' => ['saq', 'engineering', 'pmo'],
            ],
            'construction' => [
                'enabled' => true,
                'chain' => ['cme', 'pmo', 'tenant_admin'],
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
