<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Support;

final class ProcurementVendorAccreditationStatus
{
    public const PENDING = 'pending';

    public const ACCREDITED = 'accredited';

    public const SUSPENDED = 'suspended';

    public const EXPIRED = 'expired';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::PENDING,
            self::ACCREDITED,
            self::SUSPENDED,
            self::EXPIRED,
        ];
    }

    public static function isValid(string $status): bool
    {
        return in_array($status, self::all(), true);
    }

    public static function isSelectableOnPo(string $status): bool
    {
        return $status === self::ACCREDITED;
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::PENDING => __('Pending accreditation'),
            self::ACCREDITED => __('Accredited'),
            self::SUSPENDED => __('Suspended'),
            self::EXPIRED => __('Accreditation expired'),
            default => $status,
        };
    }
}
