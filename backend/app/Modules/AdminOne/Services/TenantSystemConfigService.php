<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Services;

use App\Models\Tenant;
use App\Modules\AdminOne\Models\SystemSettings;
use App\Modules\Platform\Services\TenantBrandingAssetService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Metacoresoft-style Manage System configuration (tenant DB) with branding sync to central theme_tokens.
 */
final class TenantSystemConfigService
{
    public function __construct(
        private readonly TenantBrandingAssetService $brandingAssets,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        $config = $this->mergedConfig();
        $tenant = $this->centralTenant();
        $tokens = is_array($tenant?->theme_tokens) ? $tenant->theme_tokens : [];

        return [
            'config' => $config,
            'branding' => [
                'logo_url' => $tokens['logo_url'] ?? null,
                'favicon_url' => $tokens['favicon_url'] ?? null,
                'company_address' => $tokens['company_address'] ?? ($config['support']['office_address'] ?? null),
                'company_phone' => $tokens['company_phone'] ?? ($config['support']['contact_phone'] ?? null),
                'company_email' => $tokens['company_email'] ?? ($config['support']['support_email'] ?? null),
                'company_tin' => $tokens['company_tin'] ?? null,
                'light' => is_array($tokens['light'] ?? null) ? $tokens['light'] : [],
                'dark' => is_array($tokens['dark'] ?? null) ? $tokens['dark'] : [],
            ],
            'tenant_slug' => $tenant?->slug,
        ];
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    public function update(array $incoming): array
    {
        $current = $this->mergedConfig();
        $next = $this->normalizeConfig(array_replace_recursive($current, $incoming));

        $record = $this->singleton();
        $record->config_json = $next;
        $record->save();

        $this->syncThemeTokens($next);

        return $this->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function uploadBrandingAsset(UploadedFile $file, string $kind): array
    {
        $tenant = $this->centralTenant();
        if ($tenant === null) {
            throw ValidationException::withMessages([
                'file' => [__('Tenant context is missing.')],
            ]);
        }

        $this->brandingAssets->store($tenant, $file, $kind, null);

        return $this->get();
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'brand' => [
                'application_name' => '',
                'company_name' => '',
                'show_company_beside_logo' => false,
                'logo_background' => '#F4F6F9',
                'application_name_color' => 'auto',
            ],
            'theme' => [
                'sidebar_dark' => '#161e2e',
                'accent' => '#2563EB',
                'base_light' => '#F8FAFC',
                'shell_layout' => 'sidebar',
                'lock_layout' => false,
                'preset' => null,
            ],
            'localization' => [
                'ai_business_context' => '',
                'country' => 'Philippines',
                'office_city' => '',
                'currency_symbol' => '₱',
                'currency_code' => 'PHP',
                'timezone' => 'Asia/Manila',
                'locale' => 'en-PH',
                'date_format' => 'DD/MM/YYYY',
                'locations_entity' => 'locations',
            ],
            'support' => [
                'support_email' => '',
                'contact_phone' => '',
                'office_address' => '',
                'website_url' => '',
                'copyright_footer' => '',
            ],
            'security' => [
                'login_background_url' => null,
                'gps_tracking_enabled' => false,
            ],
            'integrations' => [
                'maps_provider' => 'mapbox',
                'maps_note' => 'Map provider is configured at the platform level.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mergedConfig(): array
    {
        $stored = $this->singleton()->config_json;
        if (! is_array($stored)) {
            $stored = [];
        }

        return $this->normalizeConfig(array_replace_recursive(self::defaults(), $stored));
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function normalizeConfig(array $config): array
    {
        $defaults = self::defaults();

        return [
            'brand' => [
                'application_name' => Str::limit(trim((string) ($config['brand']['application_name'] ?? '')), 120, ''),
                'company_name' => Str::limit(trim((string) ($config['brand']['company_name'] ?? '')), 160, ''),
                'show_company_beside_logo' => (bool) ($config['brand']['show_company_beside_logo'] ?? false),
                'logo_background' => $this->safeHex((string) ($config['brand']['logo_background'] ?? '#F4F6F9'), '#F4F6F9'),
                'application_name_color' => $this->safeNameColor((string) ($config['brand']['application_name_color'] ?? 'auto')),
            ],
            'theme' => [
                'sidebar_dark' => $this->safeHex((string) ($config['theme']['sidebar_dark'] ?? '#161e2e'), '#161e2e'),
                'accent' => $this->safeHex((string) ($config['theme']['accent'] ?? '#2563EB'), '#2563EB'),
                'base_light' => $this->safeHex((string) ($config['theme']['base_light'] ?? '#F8FAFC'), '#F8FAFC'),
                'shell_layout' => in_array(($config['theme']['shell_layout'] ?? 'sidebar'), ['sidebar', 'navbar'], true)
                    ? (string) $config['theme']['shell_layout']
                    : 'sidebar',
                'lock_layout' => (bool) ($config['theme']['lock_layout'] ?? false),
                'preset' => isset($config['theme']['preset']) ? Str::limit((string) $config['theme']['preset'], 64, '') : null,
            ],
            'localization' => [
                'ai_business_context' => Str::limit(trim((string) ($config['localization']['ai_business_context'] ?? '')), 5000, ''),
                'country' => Str::limit(trim((string) ($config['localization']['country'] ?? $defaults['localization']['country'])), 80, ''),
                'office_city' => Str::limit(trim((string) ($config['localization']['office_city'] ?? '')), 120, ''),
                'currency_symbol' => Str::limit(trim((string) ($config['localization']['currency_symbol'] ?? '₱')), 8, ''),
                'currency_code' => Str::upper(Str::limit(trim((string) ($config['localization']['currency_code'] ?? 'PHP')), 8, '')),
                'timezone' => Str::limit(trim((string) ($config['localization']['timezone'] ?? 'Asia/Manila')), 64, ''),
                'locale' => Str::limit(trim((string) ($config['localization']['locale'] ?? 'en-PH')), 32, ''),
                'date_format' => Str::limit(trim((string) ($config['localization']['date_format'] ?? 'DD/MM/YYYY')), 32, ''),
                'locations_entity' => Str::limit(trim((string) ($config['localization']['locations_entity'] ?? 'locations')), 64, ''),
            ],
            'support' => [
                'support_email' => Str::limit(trim((string) ($config['support']['support_email'] ?? '')), 255, ''),
                'contact_phone' => Str::limit(trim((string) ($config['support']['contact_phone'] ?? '')), 80, ''),
                'office_address' => Str::limit(trim((string) ($config['support']['office_address'] ?? '')), 1000, ''),
                'website_url' => Str::limit(trim((string) ($config['support']['website_url'] ?? '')), 500, ''),
                'copyright_footer' => Str::limit(trim((string) ($config['support']['copyright_footer'] ?? '')), 255, ''),
            ],
            'security' => [
                'login_background_url' => ($config['security']['login_background_url'] ?? null)
                    ? Str::limit(trim((string) $config['security']['login_background_url']), 2048, '')
                    : null,
                'gps_tracking_enabled' => (bool) ($config['security']['gps_tracking_enabled'] ?? false),
            ],
            'integrations' => [
                'maps_provider' => 'mapbox',
                'maps_note' => (string) ($defaults['integrations']['maps_note'] ?? ''),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function syncThemeTokens(array $config): void
    {
        $tenant = $this->centralTenant();
        if ($tenant === null) {
            return;
        }

        $existing = is_array($tenant->theme_tokens) ? $tenant->theme_tokens : [];
        $light = is_array($existing['light'] ?? null) ? $existing['light'] : [];
        $dark = is_array($existing['dark'] ?? null) ? $existing['dark'] : [];

        $accent = (string) $config['theme']['accent'];
        $base = (string) $config['theme']['base_light'];

        $light['background'] = $base;
        // Keep Geist CTA primary (#171717); accent is brand only (charts / chips).
        $light['chart-1'] = $accent;
        // Aside matches header (card surface).
        $light['sidebar'] = 'var(--card)';
        $light['sidebar-foreground'] = 'var(--card-foreground)';
        $light['sidebar-primary'] = 'var(--primary)';
        $light['sidebar-accent'] = 'var(--muted)';
        $light['sidebar-accent-foreground'] = 'var(--foreground)';
        $light['sidebar-border'] = 'var(--border)';

        $dark['chart-1'] = $accent;
        $dark['sidebar'] = 'var(--card)';
        $dark['sidebar-foreground'] = 'var(--card-foreground)';
        $dark['sidebar-primary'] = 'var(--primary)';
        $dark['sidebar-primary-foreground'] = 'var(--primary-foreground)';
        $dark['sidebar-accent'] = 'var(--muted)';
        $dark['sidebar-accent-foreground'] = 'var(--foreground)';
        $dark['sidebar-border'] = 'var(--border)';

        $payload = array_merge($existing, [
            'version' => max(1, (int) ($existing['version'] ?? 1)),
            'company_address' => $config['support']['office_address'] !== ''
                ? $config['support']['office_address']
                : ($existing['company_address'] ?? null),
            'company_phone' => $config['support']['contact_phone'] !== ''
                ? $config['support']['contact_phone']
                : ($existing['company_phone'] ?? null),
            'company_email' => $config['support']['support_email'] !== ''
                ? $config['support']['support_email']
                : ($existing['company_email'] ?? null),
            'light' => $light,
            'dark' => $dark,
        ]);

        // Keep hosted logo/favicon paths intact via mergeForSave.
        $tenant->theme_tokens = $this->brandingAssets->mergeForSave($tenant, $payload);
        $tenant->save();
    }

    private function singleton(): SystemSettings
    {
        $existing = SystemSettings::query()->first();
        if ($existing) {
            return $existing;
        }

        return SystemSettings::query()->create([
            'id' => (string) Str::uuid(),
            'config_json' => self::defaults(),
        ]);
    }

    private function centralTenant(): ?Tenant
    {
        $tenant = tenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }

    private function safeHex(string $value, string $fallback): string
    {
        $value = trim($value);
        if (preg_match('/^#[0-9A-Fa-f]{3,8}$/', $value) === 1) {
            return $value;
        }

        return $fallback;
    }

    private function safeNameColor(string $value): string
    {
        $allowed = ['auto', 'white', 'near_black', 'theme_accent', 'theme_dark'];

        return in_array($value, $allowed, true) ? $value : 'auto';
    }
}
