<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Modules\Tenancy\Support\FrontendDevUrl;

final class AppMenuTileDefaults
{
    /**
     * @return list<array{
     *   key: string,
     *   title: string,
     *   subtitle: string|null,
     *   icon: string,
     *   accent: string,
     *   href: string,
     *   open_in_new_tab: bool,
     *   sort_order: int,
     *   is_visible: bool,
     *   is_system: bool
     * }>
     */
    public static function tiles(): array
    {
        $productionHref = self::configuredHref('production')
            ?? FrontendDevUrl::tenantLoginUrl('app.myapp.localhost', 'production');
        $stagingHref = self::configuredHref('staging')
            ?? FrontendDevUrl::tenantLoginUrl('staging.myapp.localhost', 'staging');

        return [
            [
                'key' => 'production',
                'title' => 'Production',
                'subtitle' => 'Live workspace',
                'icon' => 'Building2',
                'accent' => 'emerald',
                'href' => $productionHref,
                'open_in_new_tab' => false,
                'sort_order' => 0,
                'is_visible' => true,
                'is_system' => true,
            ],
            [
                'key' => 'staging',
                'title' => 'Staging',
                'subtitle' => 'UAT / testing',
                'icon' => 'FlaskConical',
                'accent' => 'amber',
                'href' => $stagingHref,
                'open_in_new_tab' => false,
                'sort_order' => 1,
                'is_visible' => true,
                'is_system' => true,
            ],
        ];
    }

    private static function configuredHref(string $environment): ?string
    {
        $map = [
            'production' => config('toweros.app_menu.production_url'),
            'staging' => config('toweros.app_menu.staging_url'),
        ];
        $value = $map[$environment] ?? null;
        if (! is_string($value)) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
