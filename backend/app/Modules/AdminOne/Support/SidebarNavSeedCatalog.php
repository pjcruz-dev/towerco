<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Support;

final class SidebarNavSeedCatalog
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function tree(): array
    {
        $path = __DIR__.DIRECTORY_SEPARATOR.'sidebar-nav-seed.json';
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }
}
