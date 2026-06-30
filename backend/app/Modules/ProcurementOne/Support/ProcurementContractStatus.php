<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Support;

final class ProcurementContractStatus
{
    public const DRAFT = 'draft';

    public const ACTIVE = 'active';

    public const EXPIRED = 'expired';

    public const TERMINATED = 'terminated';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::DRAFT,
            self::ACTIVE,
            self::EXPIRED,
            self::TERMINATED,
        ];
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::DRAFT => 'Draft',
            self::ACTIVE => 'Active',
            self::EXPIRED => 'Expired',
            self::TERMINATED => 'Terminated',
            default => $status,
        };
    }

    public static function isEditable(string $status): bool
    {
        return $status === self::DRAFT;
    }
}
