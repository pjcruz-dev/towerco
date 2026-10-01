<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

final class TenantNotificationModule
{
    public const E_APPROVAL = 'e_approval';

    public const TICKETING = 'ticketing';

    public const DOCUMENTS = 'documents';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::E_APPROVAL,
            self::TICKETING,
            self::DOCUMENTS,
        ];
    }
}
