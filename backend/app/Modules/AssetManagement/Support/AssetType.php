<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Support;

use Illuminate\Validation\ValidationException;

enum AssetType: string
{
    case Laptop = 'laptop';
    case Desktop = 'desktop';
    case Monitor = 'monitor';
    case Dock = 'dock';
    case Phone = 'phone';
    case Peripheral = 'peripheral';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }

    public static function parse(string $value): self
    {
        $type = self::tryFrom(strtolower(trim($value)));
        if ($type === null) {
            throw ValidationException::withMessages([
                'asset_type' => [__('Invalid asset type.')],
            ]);
        }

        return $type;
    }

    public function tagPrefix(): string
    {
        return match ($this) {
            self::Laptop => 'ATC-LAP',
            self::Desktop => 'ATC-DSK',
            self::Monitor => 'ATC-MON',
            self::Dock => 'ATC-DOC',
            self::Phone => 'ATC-PHN',
            self::Peripheral => 'ATC-PER',
        };
    }

    public function isComputer(): bool
    {
        return $this === self::Laptop || $this === self::Desktop;
    }
}
