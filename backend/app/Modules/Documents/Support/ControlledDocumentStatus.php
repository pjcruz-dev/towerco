<?php

declare(strict_types=1);

namespace App\Modules\Documents\Support;

final class ControlledDocumentStatus
{
    public const PUBLISHED = 'published';

    public const OBSOLETE = 'obsolete';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::PUBLISHED, self::OBSOLETE];
    }
}
