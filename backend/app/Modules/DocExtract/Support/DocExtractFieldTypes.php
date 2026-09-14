<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Support;

final class DocExtractFieldTypes
{
    /** @var list<string> */
    public const ALL = [
        'text',
        'multiline',
        'number',
        'currency',
        'percentage',
        'date',
        'email',
        'phone',
        'boolean',
        'table',
    ];

    /** Scalar types allowed inside a table column definition. */
    public const TABLE_COLUMN_TYPES = [
        'text',
        'number',
        'currency',
        'percentage',
        'date',
        'boolean',
    ];

    public static function rule(): string
    {
        return 'in:'.implode(',', self::ALL);
    }

    public static function tableColumnRule(): string
    {
        return 'in:'.implode(',', self::TABLE_COLUMN_TYPES);
    }

    public static function normalize(string $type): string
    {
        $normalized = strtolower(trim($type));

        return in_array($normalized, self::ALL, true) ? $normalized : 'text';
    }

    public static function normalizeTableColumnType(string $type): string
    {
        $normalized = strtolower(trim($type));

        return in_array($normalized, self::TABLE_COLUMN_TYPES, true) ? $normalized : 'text';
    }
}
