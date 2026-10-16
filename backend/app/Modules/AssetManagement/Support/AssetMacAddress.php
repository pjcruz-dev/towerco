<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Support;

final class AssetMacAddress
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $hex = strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', $value) ?? '');
        if ($hex === '') {
            return null;
        }
        if (strlen($hex) !== 12) {
            return strtoupper(trim($value));
        }

        return implode('-', str_split($hex, 2));
    }
}
