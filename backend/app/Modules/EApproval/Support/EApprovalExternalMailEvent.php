<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

/**
 * Mail event names for anonymous external submitter notifications.
 * Keep in sync with EApprovalExternalSubmissionNotification and settings toggles.
 */
final class EApprovalExternalMailEvent
{
    public const RECEIVED = 'external_status_received';

    public const APPROVED = 'external_status_approved';

    public const REJECTED = 'external_status_rejected';

    public const RETURNED = 'external_status_returned';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::RECEIVED,
            self::APPROVED,
            self::REJECTED,
            self::RETURNED,
        ];
    }
}
