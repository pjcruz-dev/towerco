<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

final class AppMenuGroupDefaults
{
    /**
     * @return list<array{
     *   key: string,
     *   title: string,
     *   sort_order: int,
     *   is_visible: bool
     * }>
     */
    public static function groups(): array
    {
        return [
            [
                'key' => 'workspaces',
                'title' => 'Workspaces',
                'sort_order' => 0,
                'is_visible' => true,
            ],
        ];
    }
}
