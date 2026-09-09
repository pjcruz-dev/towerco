<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Support;

final class DocExtractTemplateStatus
{
    public const DRAFT = 'draft';

    public const PUBLISHED = 'published';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::DRAFT, self::PUBLISHED];
    }

    public static function normalize(?string $status): string
    {
        $value = strtolower(trim((string) $status));
        if ($value === self::PUBLISHED) {
            return self::PUBLISHED;
        }

        return self::DRAFT;
    }

    public static function isPublished(?string $status): bool
    {
        return self::normalize($status) === self::PUBLISHED;
    }
}
