<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

final class TenantNotificationModule
{
    public const E_APPROVAL = 'e_approval';

    public const PROJECT_ONE = 'project_one';

    public const TICKETING = 'ticketing';

    public const DOCUMENTS = 'documents';

    public const PROCUREMENT_ONE = 'procurement_one';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::E_APPROVAL,
            self::PROJECT_ONE,
            self::TICKETING,
            self::DOCUMENTS,
            self::PROCUREMENT_ONE,
        ];
    }
}
