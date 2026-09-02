<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Support;

/**
 * Metacoresoft Excel export templates (entity list + column labels) → TowerOS dyn_* slugs/fields.
 *
 * Source: Metacoresoft per-entity CSV templates (labels as headers). Regenerated via
 * storage/app/tmp_dump_export_map.php against a tenant after atc:import-meta.
 */
final class AtcMetacoresoftExportMap
{
    /** @var array<string, string> Metacoresoft file/table slug → TowerOS entity slug */
    public const SLUG_ALIASES = [
        'materials_&_equipment' => 'products',
        'materials_and_equipment' => 'products',
        'materials-equipment' => 'products',
    ];

    /**
     * All Metacoresoft export entities covered by the Aug 2026 field templates (37 unique).
     *
     * @return list<string> TowerOS entity slugs
     */
    public static function entitySlugs(): array
    {
        return array_keys(self::payload()['entities'] ?? []);
    }

    public static function resolveSlug(string $slugOrAlias): string
    {
        $key = strtolower(trim($slugOrAlias));

        return self::SLUG_ALIASES[$key] ?? $key;
    }

    /**
     * Metacoresoft CSV header label → dyn field name (or title/status).
     *
     * @return array<string, string>
     */
    public static function columnsFor(string $entitySlug): array
    {
        $slug = self::resolveSlug($entitySlug);
        $entity = self::payload()['entities'][$slug] ?? null;
        if (! is_array($entity)) {
            return [];
        }
        $columns = $entity['columns'] ?? [];

        return is_array($columns) ? array_map(static fn ($v) => (string) $v, $columns) : [];
    }

    /**
     * Ordered Metacoresoft header labels for import templates.
     *
     * @return list<string>
     */
    public static function headerLabelsFor(string $entitySlug): array
    {
        return array_keys(self::columnsFor($entitySlug));
    }

    /**
     * @return array{generated_at?: string, entities: array<string, array{metacoresoft_slug: string, toweros_slug: string, columns: array<string, string>}>}
     */
    private static function payload(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $path = dirname(__DIR__).DIRECTORY_SEPARATOR.'Data'.DIRECTORY_SEPARATOR.'atc-metacoresoft-export-map.json';
        if (! is_file($path)) {
            $cache = ['entities' => []];

            return $cache;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        $cache = is_array($decoded) ? $decoded : ['entities' => []];
        if (! isset($cache['entities']) || ! is_array($cache['entities'])) {
            $cache['entities'] = [];
        }

        return $cache;
    }
}
