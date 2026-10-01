<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Support;

use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynRecord;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class DynReportSupport
{
    /**
     * @return Collection<int, DynRecord>
     */
    public static function recordsForSlug(string $slug): Collection
    {
        $entity = DynEntity::query()->where('slug', $slug)->first();
        if ($entity === null) {
            return collect();
        }

        return DynRecord::query()
            ->where('entity_id', $entity->id)
            ->where('is_deleted', false)
            ->get();
    }

    /**
     * @return array<string, string> uuid => title
     */
    public static function titleMap(string $slug, array $valueKeys = ['name', 'title']): array
    {
        $map = [];
        foreach (self::recordsForSlug($slug) as $record) {
            $values = $record->values_json ?? [];
            $title = $record->title;
            if (! is_string($title) || $title === '') {
                foreach ($valueKeys as $key) {
                    if (! empty($values[$key]) && is_scalar($values[$key])) {
                        $title = (string) $values[$key];
                        break;
                    }
                }
            }
            $map[$record->id] = is_string($title) && $title !== '' ? $title : self::shortId($record->id);
        }

        return $map;
    }

    /**
     * @return array<string, array{id: string, title: string|null, values: array<string, mixed>, status: string|null}>
     */
    public static function siteIndex(): array
    {
        $out = [];
        foreach (self::recordsForSlug('tower_sites') as $record) {
            $out[$record->id] = [
                'id' => $record->id,
                'title' => $record->title,
                'values' => $record->values_json ?? [],
                'status' => $record->status,
                'source_external_id' => $record->source_external_id,
            ];
        }

        return $out;
    }

    public static function money(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }

        return (float) preg_replace('/[^\d.\-]/', '', (string) $value);
    }

    public static function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return Carbon::parse((string) $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function shortId(string $id): string
    {
        return Str::isUuid($id) ? substr($id, 0, 8) : $id;
    }

    public static function isUuid(mixed $value): bool
    {
        return is_string($value) && Str::isUuid($value);
    }
}
