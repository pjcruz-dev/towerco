<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

final class EApprovalApprovalStatus
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    /** Reviewer finished. This is not an approval and does not sign the request. */
    public const ACKNOWLEDGED = 'acknowledged';

    public const REJECTED = 'rejected';

    public const CANCELLED = 'cancelled';

    /** Pending approval cleared because the submission was returned for revision. */
    public const INVALIDATED = 'invalidated';

    /** Prior-cycle decision retained for history after a full restart resubmit. */
    public const SUPERSEDED = 'superseded';

    /**
     * @return list<string>
     */
    public static function historical(): array
    {
        return [
            self::APPROVED,
            self::ACKNOWLEDGED,
            self::REJECTED,
            self::CANCELLED,
            self::INVALIDATED,
            self::SUPERSEDED,
        ];
    }
}
