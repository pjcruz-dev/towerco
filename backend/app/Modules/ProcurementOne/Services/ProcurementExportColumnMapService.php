<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Data\ProcurementExportColumnMapDefaults;
use App\Modules\ProcurementOne\Support\ProcurementExportEntity;
use Illuminate\Validation\ValidationException;

final class ProcurementExportColumnMapService
{
    public const SETTINGS_KEY = 'export_column_maps';

    public function __construct(
        private readonly ProcurementOneSettingsService $settings,
    ) {}

    /**
     * @return array<string, list<array{key: string, label: string, enabled: bool}>>
     */
    public function resolveAll(): array
    {
        $defaults = ProcurementExportColumnMapDefaults::all();
        $stored = $this->settings->getJson(self::SETTINGS_KEY);
        if ($stored === []) {
            return $defaults;
        }

        $resolved = [];
        foreach (ProcurementExportEntity::all() as $entity) {
            $resolved[$entity] = $this->mergeEntityMap(
                $defaults[$entity] ?? [],
                is_array($stored[$entity] ?? null) ? $stored[$entity] : [],
            );
        }

        return $resolved;
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public function enabledColumns(string $entity): array
    {
        abort_unless(ProcurementExportEntity::isValid($entity), 422, __('Invalid export entity.'));

        return array_values(array_filter(
            $this->resolveAll()[$entity] ?? [],
            static fn (array $column) => ($column['enabled'] ?? false) === true,
        ));
    }

    /**
     * @return list<string>
     */
    public function enabledHeaders(string $entity): array
    {
        return array_map(static fn (array $column) => (string) $column['label'], $this->enabledColumns($entity));
    }

    /**
     * @return list<string>
     */
    public function enabledKeys(string $entity): array
    {
        return array_map(static fn (array $column) => (string) $column['key'], $this->enabledColumns($entity));
    }

    /**
     * @param  array<string, list<array{key?: string, label?: string, enabled?: bool}>>  $input
     * @return array<string, list<array{key: string, label: string, enabled: bool}>>
     */
    public function validateAndNormalize(array $input): array
    {
        $defaults = ProcurementExportColumnMapDefaults::all();
        $normalized = [];

        foreach ($input as $entity => $columns) {
            if (! ProcurementExportEntity::isValid((string) $entity) || ! is_array($columns)) {
                continue;
            }

            $normalized[$entity] = $this->mergeEntityMap($defaults[$entity] ?? [], $columns);
        }

        foreach ($normalized as $entity => $columns) {
            $enabledCount = count(array_filter($columns, static fn (array $column) => $column['enabled']));
            if ($enabledCount === 0) {
                throw ValidationException::withMessages([
                    "export_column_maps.{$entity}" => [__('At least one column must remain enabled for :entity.', ['entity' => ProcurementExportEntity::label($entity)])],
                ]);
            }
        }

        return $normalized;
    }

    /**
     * @param  list<array{key: string, label: string, enabled: bool}>  $defaults
     * @param  list<array{key?: string, label?: string, enabled?: bool}>  $overrides
     * @return list<array{key: string, label: string, enabled: bool}>
     */
    private function mergeEntityMap(array $defaults, array $overrides): array
    {
        $overrideByKey = [];
        foreach ($overrides as $column) {
            if (! is_array($column)) {
                continue;
            }
            $key = trim((string) ($column['key'] ?? ''));
            if ($key !== '') {
                $overrideByKey[$key] = $column;
            }
        }

        $merged = [];
        foreach ($defaults as $defaultColumn) {
            $key = $defaultColumn['key'];
            $override = $overrideByKey[$key] ?? null;
            $label = trim((string) ($override['label'] ?? $defaultColumn['label']));
            if ($label === '') {
                $label = $defaultColumn['label'];
            }

            $merged[] = [
                'key' => $key,
                'label' => $label,
                'enabled' => array_key_exists('enabled', (array) $override)
                    ? (bool) ($override['enabled'] ?? false)
                    : (bool) $defaultColumn['enabled'],
            ];
        }

        return $merged;
    }
}
