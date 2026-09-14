<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Support;

final class DocExtractBatchStatus
{
    public const PENDING = 'pending';

    public const PROCESSING = 'processing';

    public const READY = 'ready';

    public const FAILED = 'failed';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::PENDING, self::PROCESSING, self::READY, self::FAILED];
    }
}
