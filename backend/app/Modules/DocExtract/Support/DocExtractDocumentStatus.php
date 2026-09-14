<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Support;

final class DocExtractDocumentStatus
{
    public const PENDING = 'pending';

    public const SCANNING = 'scanning';

    public const READY = 'ready';

    public const FAILED = 'failed';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::PENDING, self::SCANNING, self::READY, self::FAILED];
    }
}
