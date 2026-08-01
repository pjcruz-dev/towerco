<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

final class EApprovalExportHistoryStatus
{
    public const QUEUED = 'queued';

    public const PROCESSING = 'processing';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::QUEUED,
            self::PROCESSING,
            self::COMPLETED,
            self::FAILED,
        ];
    }
}
