<?php

declare(strict_types=1);

namespace App\Modules\AssetOne\Services;

use App\Modules\AssetOne\Models\Asset;

final class AssetCodeAllocator
{
    public function allocate(): string
    {
        $prefix = 'AST-'.now()->format('Y').'-';
        $last = Asset::query()
            ->where('asset_code', 'like', $prefix.'%')
            ->orderByDesc('asset_code')
            ->value('asset_code');

        $sequence = 0;
        if (is_string($last) && preg_match('/-(\d+)$/', $last, $matches) === 1) {
            $sequence = (int) $matches[1];
        }

        return $prefix.str_pad((string) ($sequence + 1), 4, '0', STR_PAD_LEFT);
    }
}
