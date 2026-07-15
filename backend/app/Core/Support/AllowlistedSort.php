<?php

declare(strict_types=1);

namespace App\Core\Support;

/**
 * Resolves API list `sort` query values in the form `column:direction`.
 */
final class AllowlistedSort
{
    /**
     * @param  list<string>  $sortable
     * @return array{0: string, 1: 'asc'|'desc'}
     */
    public static function resolve(
        string $sort,
        array $sortable,
        string $defaultColumn,
        string $defaultDirection = 'asc',
    ): array {
        $parts = explode(':', $sort, 2);
        $column = $parts[0] !== '' ? $parts[0] : $defaultColumn;
        $requestedDirection = strtolower($parts[1] ?? $defaultDirection);
        $direction = $requestedDirection === 'desc' ? 'desc' : 'asc';

        if (! in_array($column, $sortable, true)) {
            $column = $defaultColumn;
            $direction = strtolower($defaultDirection) === 'desc' ? 'desc' : 'asc';
        }

        return [$column, $direction];
    }
}
