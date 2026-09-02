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
 *     company_address?: string|null,
 *     company_phone?: string|null,
 *     company_email?: string|null,
 *     company_tin?: string|null,
 *     light?: array<string, string>,
 *     dark?: array<string, string>
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
                'company_address' => ['nullable', 'string', 'max:1000'],
                'company_phone' => ['nullable', 'string', 'max:80'],
                'company_email' => ['nullable', 'string', 'max:255'],
                'company_tin' => ['nullable', 'string', 'max:80'],
                'light' => ['nullable', 'array', 'max:50'],
                'dark' => ['nullable', 'array', 'max:50'],
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

            $email = $themeTokens['company_email'] ?? null;
            if (is_string($email) && trim($email) !== '' && filter_var(trim($email), FILTER_VALIDATE_EMAIL) === false) {
                $v->errors()->add('company_email', __('Enter a valid company email.'));
            }

            foreach (['company_address', 'company_phone', 'company_email', 'company_tin'] as $textKey) {
                $value = $themeTokens[$textKey] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                if (! is_string($value)) {
                    $v->errors()->add($textKey, __('Company letterhead fields must be text.'));

                    continue;
                }
                if (preg_match('/[<>]|javascript:/i', $value) === 1) {
                    $v->errors()->add($textKey, __('Invalid characters in :key.', ['key' => $textKey]));
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
            'company_address' => null,
            'company_phone' => null,
            'company_email' => null,
            'company_tin' => null,
            'light' => [],
            'dark' => [],
        ];

        if (! is_array($raw)) {
            return $defaults;
        }

        try {
            $validated = self::validate($raw);
            unset($validated['logo_asset'], $validated['favicon_asset']);

            // Normalize empty strings to null for public consumers.
            foreach (['company_address', 'company_phone', 'company_email', 'company_tin'] as $key) {
                if (! array_key_exists($key, $validated)) {
                    continue;
                }
                $value = $validated[$key];
                $validated[$key] = is_string($value) && trim($value) !== '' ? trim($value) : null;
            }

            return array_merge($defaults, $validated);
        } catch (ValidationException) {
            return $defaults;
        }
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
