<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Validates tenant theme token payloads (semantic CSS variable keys, safe values).
 *
 * @phpstan-type ThemeTokens array{
 *     version?: int,
 *     logo_url?: string|null,
 *     favicon_url?: string|null,
 *     logo_asset?: string|null,
 *     favicon_asset?: string|null,
 *     light?: array<string, string>,
 *     dark?: array<string, string>,
 *     chrome?: array<string, mixed>|null
 * }
 */
final class TenantThemeTokensValidator
{
    /**
     * Keys match app/globals.css custom properties without the leading "--".
     *
     * @var list<string>
     */
    public const ALLOWED_KEYS = [
        'background',
        'foreground',
        'card',
        'card-foreground',
        'popover',
        'popover-foreground',
        'primary',
        'primary-foreground',
        'secondary',
        'secondary-foreground',
        'muted',
        'muted-foreground',
        'accent',
        'accent-foreground',
        'destructive',
        'border',
        'input',
        'ring',
        'chart-1',
        'chart-2',
        'chart-3',
        'chart-4',
        'chart-5',
        'radius',
        'sidebar',
        'sidebar-foreground',
        'sidebar-primary',
        'sidebar-primary-foreground',
        'sidebar-accent',
        'sidebar-accent-foreground',
        'sidebar-border',
        'sidebar-ring',
    ];

    private const VALUE_PATTERN = '/^(oklch|hsl|hsla|rgb|rgba)\([^)]+\)$|^#[0-9A-Fa-f]{3,8}$/';

    private const HOSTED_ASSET_URL = '/^\/api\/v1\/public\/tenant-branding\/(logo|favicon)\?tenant=[0-9a-f-]{36}$/i';

    private const HOSTED_ASSET_PATH = '/^platform\/tenant-branding\/[0-9a-f-]{36}\/(logo|favicon)\.[a-z0-9]{2,5}$/';

    /**
     * @param  array<string, mixed>  $themeTokens
     * @return ThemeTokens
     */
    public static function validate(array $themeTokens): array
    {
        $validator = Validator::make(
            $themeTokens,
            [
                'version' => ['required', 'integer', 'min:1', 'max:999'],
                'logo_url' => ['nullable', 'string', 'max:2048'],
                'favicon_url' => ['nullable', 'string', 'max:2048'],
                'logo_asset' => ['nullable', 'string', 'max:512', 'regex:'.self::HOSTED_ASSET_PATH],
                'favicon_asset' => ['nullable', 'string', 'max:512', 'regex:'.self::HOSTED_ASSET_PATH],
                'light' => ['nullable', 'array', 'max:50'],
                'dark' => ['nullable', 'array', 'max:50'],
                'chrome' => ['nullable', 'array'],
                'chrome.saved' => ['sometimes', 'boolean'],
                'chrome.primary_preset' => ['nullable', 'string', 'in:violet,teal,rose,amber,blue,custom'],
                'chrome.primary' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                'chrome.skin' => ['nullable', 'string', 'in:default,bordered'],
                'chrome.layout' => ['nullable', 'string', 'in:vertical,collapsed,horizontal'],
                'chrome.content' => ['nullable', 'string', 'in:compact,wide'],
            ],
        );

        $validator->after(function ($v) use ($themeTokens): void {
            foreach (['logo_url', 'favicon_url'] as $urlKey) {
                $url = $themeTokens[$urlKey] ?? null;
                if ($url === null || $url === '') {
                    continue;
                }
                if (! is_string($url) || ! self::isAllowedPublicUrl($url)) {
                    $v->errors()->add($urlKey, __('Use an HTTPS URL or upload a file in Tenant branding.'));
                }
            }

            foreach (['light', 'dark'] as $mode) {
                if (! isset($themeTokens[$mode]) || ! is_array($themeTokens[$mode])) {
                    continue;
                }
                foreach ($themeTokens[$mode] as $key => $value) {
                    if (! is_string($key) || ! is_string($value)) {
                        $v->errors()->add($mode, __('Each theme key must map to a string value.'));

                        continue;
                    }
                    if (! in_array($key, self::ALLOWED_KEYS, true)) {
                        $v->errors()->add($mode, __('Unknown theme key: :key.', ['key' => $key]));

                        continue;
                    }
                    if (strlen($value) > 200) {
                        $v->errors()->add($mode, __('Theme value for :key is too long.', ['key' => $key]));

                        continue;
                    }
                    if (preg_match('/[;<>]|url\s*\(|expression\s*\(/i', $value) === 1) {
                        $v->errors()->add($mode, __('Invalid characters in theme value for :key.', ['key' => $key]));

                        continue;
                    }
                    if ($key !== 'radius' && preg_match(self::VALUE_PATTERN, $value) !== 1) {
                        $v->errors()->add($mode, __('Theme value for :key must be oklch(), hsl(), rgb(), or hex.', ['key' => $key]));

                        continue;
                    }
                    if ($key === 'radius' && ! preg_match('/^[\d.]+(rem|px|ch|em|%)$/', $value)) {
                        $v->errors()->add($mode, __('Radius must be a CSS length (e.g. 0.75rem).'));

                        continue;
                    }
                }
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        /** @var ThemeTokens $validated */
        $validated = $validator->validated();

        return $validated;
    }

    /**
     * Strip invalid entries when reading from DB for public API (defense in depth).
     *
     * @param  array<string, mixed>|null  $raw
     * @return array<string, mixed>
     */
    public static function sanitizeForPublic(?array $raw): array
    {
        $defaults = [
            'version' => 1,
            'logo_url' => null,
            'favicon_url' => null,
            'light' => [],
            'dark' => [],
            'chrome' => self::defaultChrome(),
        ];

        if (! is_array($raw)) {
            return $defaults;
        }

        try {
            $validated = self::validate($raw);
            unset($validated['logo_asset'], $validated['favicon_asset']);
            $validated['chrome'] = self::normalizeChrome(
                is_array($validated['chrome'] ?? null) ? $validated['chrome'] : null,
            );

            return array_merge($defaults, $validated);
        } catch (ValidationException) {
            return $defaults;
        }
    }

    /**
     * Keep a previously saved customizer when an older client saves logo URLs only.
     *
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    public static function withPreservedChrome(array $existing, array $incoming): array
    {
        if (! array_key_exists('chrome', $incoming) && isset($existing['chrome']) && is_array($existing['chrome'])) {
            $incoming['chrome'] = $existing['chrome'];
        }

        return $incoming;
    }

    /**
     * @return array{
     *     saved: bool,
     *     primary_preset: string|null,
     *     primary: string|null,
     *     skin: string,
     *     layout: string,
     *     content: string
     * }
     */
    public static function defaultChrome(): array
    {
        return [
            'saved' => false,
            'primary_preset' => null,
            'primary' => null,
            'skin' => 'default',
            'layout' => 'vertical',
            'content' => 'compact',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $chrome
     * @return array{
     *     saved: bool,
     *     primary_preset: string|null,
     *     primary: string|null,
     *     skin: string,
     *     layout: string,
     *     content: string
     * }
     */
    public static function normalizeChrome(?array $chrome): array
    {
        $defaults = self::defaultChrome();
        if ($chrome === null) {
            return $defaults;
        }

        $preset = $chrome['primary_preset'] ?? null;
        $primary = $chrome['primary'] ?? null;

        return [
            'saved' => (bool) ($chrome['saved'] ?? false),
            'primary_preset' => is_string($preset) && $preset !== '' ? $preset : null,
            'primary' => is_string($primary) && $primary !== '' ? strtolower($primary) : null,
            'skin' => is_string($chrome['skin'] ?? null) && $chrome['skin'] !== ''
                ? (string) $chrome['skin']
                : $defaults['skin'],
            'layout' => is_string($chrome['layout'] ?? null) && $chrome['layout'] !== ''
                ? (string) $chrome['layout']
                : $defaults['layout'],
            'content' => is_string($chrome['content'] ?? null) && $chrome['content'] !== ''
                ? (string) $chrome['content']
                : $defaults['content'],
        ];
    }

    public static function isAllowedPublicUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return true;
        }

        if (preg_match(self::HOSTED_ASSET_URL, $url) === 1) {
            return true;
        }

        if (preg_match('/^https:\/\//i', $url) !== 1) {
            return false;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
