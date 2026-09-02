<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Support;

final class DynFieldType
{
    public const TEXT = 'text';

    public const TEXTAREA = 'textarea';

    public const NUMBER = 'number';

    public const DECIMAL = 'decimal';

    public const DATE = 'date';

    public const DATETIME = 'datetime';

    public const SELECT = 'select';

    public const MULTISELECT = 'multiselect';

    public const BOOLEAN = 'boolean';

    public const RELATIONSHIP = 'relationship';

    public const FILE = 'file';

    public const EMAIL = 'email';

    public const PHONE = 'phone';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::TEXT,
            self::TEXTAREA,
            self::NUMBER,
            self::DECIMAL,
            self::DATE,
            self::DATETIME,
            self::SELECT,
            self::MULTISELECT,
            self::BOOLEAN,
            self::RELATIONSHIP,
            self::FILE,
            self::EMAIL,
            self::PHONE,
        ];
    }
}
