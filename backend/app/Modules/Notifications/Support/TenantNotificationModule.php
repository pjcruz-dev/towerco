<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

final class TenantNotificationModule
{
    public const E_APPROVAL = 'e_approval';

    public const TICKETING = 'ticketing';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::E_APPROVAL,
            self::TICKETING,
        ];
    }
}
