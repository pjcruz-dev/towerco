<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Support;

final class DynModulePack
{
    public const PM = 'pm';

    public const PROCUREMENT = 'procurement';

    public const FINANCE = 'finance';

    public const TICKETING = 'ticketing';

    public const REFERENCE = 'reference';

    public const SYSTEM = 'system';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::PM,
            self::PROCUREMENT,
            self::FINANCE,
            self::TICKETING,
            self::REFERENCE,
            self::SYSTEM,
        ];
    }
}
