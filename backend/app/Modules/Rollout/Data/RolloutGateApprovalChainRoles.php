<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Data;

/**
 * Allowed gate approval chain step keys (RolloutGateApproverResolver).
 */
final class RolloutGateApprovalChainRoles
{
    /** @var list<string> */
    public const ALL = [
        'saq',
        'saq_engineering',
        'pmo',
        'engineering',
        'cme',
        'cme_power',
        'mno',
        'bd',
        'bd_pmo',
        'tenant_admin',
    ];

    /**
     * @param  list<string>  $chain
     * @return list<string>
     */
    public static function sanitize(array $chain): array
    {
        $allowed = array_fill_keys(self::ALL, true);
        $seen = [];
        $result = [];

        foreach ($chain as $role) {
            $key = strtolower(trim((string) $role));
            if ($key === '' || ! isset($allowed[$key]) || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = $key;
        }

        return $result;
    }

    public static function isAllowed(string $role): bool
    {
        return in_array(strtolower(trim($role)), self::ALL, true);
    }
}
