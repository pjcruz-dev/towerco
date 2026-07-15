<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Support;

final class ProcurementInventoryMovementType
{
    public const GRN_RECEIPT = 'grn_receipt';

    public const TRANSFER_OUT = 'transfer_out';

    public const TRANSFER_IN = 'transfer_in';

    public const DEPLOY = 'deploy';

    public const ADJUSTMENT = 'adjustment';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::GRN_RECEIPT,
            self::TRANSFER_OUT,
            self::TRANSFER_IN,
            self::DEPLOY,
            self::ADJUSTMENT,
        ];
    }

    public static function label(string $type): string
    {
        return match ($type) {
            self::GRN_RECEIPT => __('GRN receipt'),
            self::TRANSFER_OUT => __('Transfer out'),
            self::TRANSFER_IN => __('Transfer in'),
            self::DEPLOY => __('Deploy to site'),
            self::ADJUSTMENT => __('Adjustment'),
            default => $type,
        };
    }
}
