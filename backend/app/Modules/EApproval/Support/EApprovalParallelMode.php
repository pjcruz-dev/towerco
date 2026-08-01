<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

final class EApprovalParallelMode
{
    public const ALL = 'all';

    public const ANY = 'any';

    public const N_OF_M = 'n_of_m';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::ALL, self::ANY, self::N_OF_M];
    }

    public static function normalize(?string $mode): string
    {
        $value = strtolower(trim((string) $mode));

        return in_array($value, self::all(), true) ? $value : self::ALL;
    }

    /**
     * @param  array<string, mixed>|null  $condition
     */
    public static function fromCondition(?array $condition): string
    {
        if (! is_array($condition)) {
            return self::ALL;
        }

        return self::normalize(
            isset($condition['parallel_mode']) ? (string) $condition['parallel_mode'] : null,
        );
    }

    /**
     * @param  array<string, mixed>|null  $condition
     */
    public static function quorumFromCondition(?array $condition, int $memberCount): int
    {
        $mode = self::fromCondition($condition);
        if ($mode === self::ANY) {
            return 1;
        }

        if ($mode !== self::N_OF_M) {
            return max(1, $memberCount);
        }

        $raw = is_array($condition) ? ($condition['parallel_quorum'] ?? null) : null;
        $quorum = is_numeric($raw) ? (int) $raw : 1;

        return max(1, min($memberCount, $quorum));
    }
}
