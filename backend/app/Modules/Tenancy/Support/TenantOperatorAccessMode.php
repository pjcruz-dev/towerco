<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

final class TenantOperatorAccessMode
{
    public const READ_ONLY = 'read_only';

    public const BLOCKED = 'blocked';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::READ_ONLY,
            self::BLOCKED,
        ];
    }

    public static function normalize(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, self::all(), true) ? $normalized : null;
    }
}
