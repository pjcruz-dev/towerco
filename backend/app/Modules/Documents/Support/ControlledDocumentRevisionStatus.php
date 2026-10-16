<?php

declare(strict_types=1);

namespace App\Modules\Documents\Support;

final class ControlledDocumentRevisionStatus
{
    public const PENDING = 'pending';

    public const PUBLISHED = 'published';

    public const SUPERSEDED = 'superseded';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::PENDING, self::PUBLISHED, self::SUPERSEDED];
    }
}
