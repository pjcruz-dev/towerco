<?php

declare(strict_types=1);

namespace App\Modules\Documents\Support;

final class ControlledDocumentRevisionStatus
{
    public const PUBLISHED = 'published';

    public const SUPERSEDED = 'superseded';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::PUBLISHED, self::SUPERSEDED];
    }
}
