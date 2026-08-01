<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

final class EApprovalWhenLogic
{
    public const AND = 'and';

    public const OR = 'or';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::AND, self::OR];
    }

    public static function normalize(?string $logic): string
    {
        $value = strtolower(trim((string) $logic));

        return $value === self::OR ? self::OR : self::AND;
    }

    /**
     * @param  array<string, mixed>|null  $condition
     * @param  array<string, mixed>|null  $definition
     */
    public static function fromDefinition(?array $definition, ?array $condition = null): string
    {
        if (is_array($definition) && isset($definition['when_logic'])) {
            return self::normalize((string) $definition['when_logic']);
        }

        if (is_array($condition) && isset($condition['when_logic'])) {
            return self::normalize((string) $condition['when_logic']);
        }

        return self::AND;
    }
}
