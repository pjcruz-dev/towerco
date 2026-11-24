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

        $modules = [
            ['key' => 'module-e-forms', 'title' => 'E-Forms', 'icon' => 'ClipboardCheck', 'accent' => 'sky', 'path' => '/e-approval'],
            ['key' => 'module-ticketing', 'title' => 'Ticketing', 'icon' => 'LifeBuoy', 'accent' => 'violet', 'path' => '/ticketing'],
            ['key' => 'module-document-control', 'title' => 'Document Control', 'icon' => 'Archive', 'accent' => 'emerald', 'path' => '/documents/controlled'],
            ['key' => 'module-doc-extract', 'title' => 'DocExtract', 'icon' => 'FileScan', 'accent' => 'amber', 'path' => '/doc-extract'],
        ];

        $tiles = [
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

        foreach ($modules as $index => $module) {
            $tiles[] = [
                'key' => $module['key'],
                'title' => $module['title'],
                'subtitle' => 'Opens the '.$module['title'].' dashboard',
                'icon' => $module['icon'],
                'accent' => $module['accent'],
                'href' => self::moduleHref($productionHref, $module['path']),
                'open_in_new_tab' => false,
                'sort_order' => $index + 2,
                'is_visible' => true,
                'is_system' => true,
            ];
        }

        return $tiles;
    }

    public static function moduleHref(string $loginUrl, string $path): string
    {
        $base = preg_replace('#/login(?:\?.*)?$#', '', trim($loginUrl));
        if (! is_string($base) || $base === '') {
            $base = trim($loginUrl);
        }

        return rtrim($base, '/').$path;
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
