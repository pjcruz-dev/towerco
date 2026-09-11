<?php

declare(strict_types=1);

namespace App\Core\Support;

use Illuminate\Http\Request;

/**
 * Axios often serializes a one-element array as a bare query string
 * (`ids=uuid` / `columns=status`) which PHP treats as a string.
 * Laravel then 422s on `'ids' => ['array']`. Normalize before validate.
 */
final class ModuleListExportQuery
{
    /**
     * @param  list<string>  $keys
     */
    public static function coerceArrayParams(Request $request, array $keys = ['ids', 'columns']): void
    {
        $merge = [];
        foreach ($keys as $key) {
            if (! $request->exists($key)) {
                continue;
            }
            $value = $request->input($key);
            if (is_string($value)) {
                $merge[$key] = $value === '' ? [] : [$value];
            }
        }
        if ($merge !== []) {
            $request->merge($merge);
        }
    }
}
