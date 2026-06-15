<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

final class EApprovalSubmissionStatus
{
    public const DRAFT = 'draft';

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const CANCELLED = 'cancelled';

    public const RETURNED = 'returned';

    public const AWAITING_DCF = 'awaiting_dcf';

    /**
     * @return list<string>
     */
    public static function open(): array
    {
        return [self::PENDING, self::RETURNED, self::AWAITING_DCF];
    }
}
