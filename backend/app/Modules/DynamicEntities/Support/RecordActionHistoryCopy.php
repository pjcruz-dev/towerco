<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Support;

/**
 * Titles for the action-history panel. Kept separate so the wording can be tested
 * without a tenant database.
 */
final class RecordActionHistoryCopy
{
    public static function title(string $kind, string $entityLabel, int $count, ?string $fieldLabel): string
    {
        $entity = trim($entityLabel) !== '' ? trim($entityLabel) : 'record';
        $count = max(1, $count);

        return match ($kind) {
            'created' => $count === 1
                ? 'Created a '.$entity.' record'
                : 'Created '.$count.' '.$entity.' record(s)',
            'deleted' => $count === 1
                ? 'Deleted a '.$entity.' record'
                : 'Deleted '.$count.' '.$entity.' record(s)',
            'imported' => 'Imported '.$entity,
            'updated' => is_string($fieldLabel) && $fieldLabel !== ''
                ? 'Edited '.$fieldLabel.' on one '.$entity.' record'
                : 'Edited a '.$entity.' record',
            default => 'Updated a '.$entity.' record',
        };
    }

    public static function detail(string $kind, int $count, string $entityLabel): string
    {
        $verb = match ($kind) {
            'created' => 'created',
            'deleted' => 'deleted',
            'imported' => 'imported',
            default => 'updated',
        };
        $count = max(1, $count);

        return $count.' '.$verb.' · '.$entityLabel;
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public static function reversible(string $module, string $kind, ?array $before, ?array $after, int $recordCount): bool
    {
        $before = $before ?? [];
        $after = $after ?? [];

        if ($kind === 'created') {
            return $module !== 'assets' && isset($after['id']);
        }

        if ($kind === 'updated') {
            return isset($before['id']);
        }

        if ($kind === 'deleted') {
            if (isset($before['id'])) {
                return true;
            }
            $records = $before['records'] ?? null;

            return is_array($records) && count($records) === $recordCount && $recordCount > 0;
        }

        if ($kind === 'imported') {
            $created = is_array($before['created_ids'] ?? null) ? count($before['created_ids']) : 0;
            $updates = is_array($before['updates'] ?? null) ? count($before['updates']) : 0;
            if ($created + $updates !== $recordCount || $recordCount < 1) {
                return false;
            }
            if ($module === 'assets') {
                return $updates > 0;
            }

            return true;
        }

        return false;
    }

    public static function blockedReason(string $module, string $kind, bool $reversible): ?string
    {
        if ($reversible) {
            return null;
        }
        if ($module === 'assets' && $kind === 'created') {
            return 'Creating an asset cannot be undone from history.';
        }
        if ($module === 'assets' && $kind === 'imported') {
            return 'New assets stay in the register. Only edits in an import can be reverted, and very large imports cannot.';
        }

        return 'This action is too large to revert.';
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public static function changedFieldLabel(?array $before, ?array $after): ?string
    {
        $beforeValues = is_array($before['values'] ?? null) ? $before['values'] : [];
        $afterValues = is_array($after['values'] ?? null) ? $after['values'] : [];
        $keys = array_values(array_unique(array_merge(array_keys($beforeValues), array_keys($afterValues))));
        $changed = [];
        foreach ($keys as $key) {
            if (($beforeValues[$key] ?? null) !== ($afterValues[$key] ?? null)) {
                $changed[] = $key;
            }
        }
        if (count($changed) !== 1) {
            return null;
        }

        return ucfirst(str_replace('_', ' ', (string) $changed[0]));
    }
}
