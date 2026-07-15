<?php

declare(strict_types=1);

namespace App\Modules\Documents\Support;

final class DocumentApprovalStatus
{
    public const NONE = 'none';

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::NONE,
            self::PENDING,
            self::APPROVED,
            self::REJECTED,
        ];
    }
}
