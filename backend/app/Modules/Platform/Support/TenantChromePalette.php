<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use Illuminate\Validation\ValidationException;

/**
 * Turns a tenant customizer choice into semantic color tokens.
 * Preset hex values live here so the UI can reference CSS variables instead of literals.
 */
final class TenantChromePalette
{
    /**
     * @var array<string, string>
     */
    public const PRESETS = [
        'violet' => '#7367f0',
        'teal' => '#0d9488',
        'rose' => '#e11d48',
        'amber' => '#f59e0b',
        'blue' => '#2563eb',
    ];

    /**
     * Keys overwritten when a primary color is saved, and removed when it is cleared.
     *
     * @var list<string>
     */
    public const COLOR_KEYS = [
        'primary',
        'primary-foreground',
        'ring',
        'sidebar-primary',
        'sidebar-primary-foreground',
        'chart-1',
    ];

    /**
     * Above this relative luminance, button text is near-black. Amber sits above it; the saturated presets sit below.
     */
    private const LIGHT_TEXT_MAX_LUMINANCE = 0.4;

    public static function resolvePrimary(?string $preset, ?string $hex): ?string
    {
        if ($preset === null || $preset === '') {
            return null;
        }

        if ($preset === 'custom') {
            if (! is_string($hex) || preg_match('/^#[0-9A-Fa-f]{6}$/', $hex) !== 1) {
                throw ValidationException::withMessages([
                    'primary' => [__('Choose a custom color.')],
                ]);
            }

            return strtolower($hex);
        }

        $color = self::PRESETS[$preset] ?? null;
        if ($color === null) {
            throw ValidationException::withMessages([
                'primary_preset' => [__('Unknown primary color.')],
            ]);
        }

        return $color;
    }

    /**
     * @param  array<string, string>  $light
     * @param  array<string, string>  $dark
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    public static function paint(array $light, array $dark, ?string $primary): array
    {
        return [
            'light' => self::paintMode($light, $primary),
            'dark' => self::paintMode($dark, $primary),
        ];
    }

    public static function foreground(string $hex): string
    {
        $rgb = self::hexToRgb($hex);
        $luminance = self::relativeLuminance($rgb[0], $rgb[1], $rgb[2]);

        return $luminance > self::LIGHT_TEXT_MAX_LUMINANCE ? '#111827' : '#fafafa';
    }

    /**
     * @param  array<string, string>  $mode
     * @return array<string, string>
     */
    private static function paintMode(array $mode, ?string $primary): array
    {
        foreach (self::COLOR_KEYS as $key) {
            unset($mode[$key]);
        }

        if ($primary === null) {
            return $mode;
        }

        $foreground = self::foreground($primary);
        foreach (self::COLOR_KEYS as $key) {
            $mode[$key] = str_ends_with($key, 'foreground') ? $foreground : $primary;
        }

        return $mode;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private static function relativeLuminance(int $red, int $green, int $blue): float
    {
        $channels = [$red / 255, $green / 255, $blue / 255];
        $linear = array_map(static function (float $channel): float {
            if ($channel <= 0.04045) {
                return $channel / 12.92;
            }

            return (($channel + 0.055) / 1.055) ** 2.4;
        }, $channels);

        return (0.2126 * $linear[0]) + (0.7152 * $linear[1]) + (0.0722 * $linear[2]);
    }
}
