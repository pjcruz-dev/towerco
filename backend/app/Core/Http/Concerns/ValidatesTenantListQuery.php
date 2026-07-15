<?php

declare(strict_types=1);

namespace App\Core\Http\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

trait ValidatesTenantListQuery
{
    /**
     * @return array{page: int, per_page: int, search: string, sort: string|null}
     */
    protected function validatedTenantListQuery(Request $request): array
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'string', 'max:255'],
            'sort' => ['sometimes', 'string', 'max:64'],
        ]);

        return [
            'page' => (int) ($validated['page'] ?? 1),
            'per_page' => (int) ($validated['per_page'] ?? 25),
            'search' => Str::limit(trim((string) ($validated['search'] ?? '')), 255, ''),
            'sort' => isset($validated['sort']) ? (string) $validated['sort'] : null,
        ];
    }
}
