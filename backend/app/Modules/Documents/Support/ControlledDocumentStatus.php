<?php

declare(strict_types=1);

namespace App\Modules\Documents\Support;

final class ControlledDocumentStatus
{
    public const PENDING = 'pending';

    public const PUBLISHED = 'published';

    public const OBSOLETE = 'obsolete';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::PENDING, self::PUBLISHED, self::OBSOLETE];
    }

    /**
     * Document rows stored as superseded are obsolete. Older revisions still use that word.
     *
     * @return list<string>
     */
    public static function obsoleteValues(): array
    {
        return [self::OBSOLETE, 'superseded'];
    }
}
