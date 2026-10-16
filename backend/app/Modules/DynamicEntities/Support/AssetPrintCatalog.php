<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Support;

use App\Modules\DynamicEntities\Models\DynEntity;

/**
 * Named printables for the asset register. Missing catalog templates are inserted once.
 * A template the user later deletes is recorded in seeded_template_ids and is not put back.
 * Existing HTML and CSS are left as saved.
 */
final class AssetPrintCatalog
{
    public const SLUG = 'it_asset';

    /**
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            ItAssetPrintTemplate::settings(),
            AssetTransferPrintTemplate::settings(),
            AssetReturnPrintTemplate::settings(),
        ];
    }

    public static function ensure(DynEntity $entity): void
    {
        if ($entity->slug !== self::SLUG) {
            return;
        }

        $stored = is_array($entity->print_settings_json) ? $entity->print_settings_json : [];
        $templates = isset($stored['templates']) && is_array($stored['templates'])
            ? array_values($stored['templates'])
            : [];

        if ($templates === [] && isset($stored['id']) && is_string($stored['id']) && $stored['id'] !== '') {
            $templates[] = $stored;
        }

        $seeded = [];
        if (isset($stored['seeded_template_ids']) && is_array($stored['seeded_template_ids'])) {
            foreach ($stored['seeded_template_ids'] as $id) {
                if (is_string($id) && $id !== '') {
                    $seeded[$id] = true;
                }
            }
        }

        $present = [];
        foreach ($templates as $row) {
            if (is_array($row) && isset($row['id']) && is_string($row['id']) && $row['id'] !== '') {
                $present[$row['id']] = true;
            }
        }

        $changed = false;
        foreach (self::all() as $fresh) {
            $id = (string) $fresh['id'];
            if (isset($present[$id])) {
                if (! isset($seeded[$id])) {
                    $seeded[$id] = true;
                    $changed = true;
                }

                continue;
            }
            if (isset($seeded[$id])) {
                continue;
            }
            $fresh['updated_at'] = now()->toIso8601String();
            $templates[] = $fresh;
            $present[$id] = true;
            $seeded[$id] = true;
            $changed = true;
        }

        if (! $changed) {
            return;
        }

        $defaultId = isset($stored['default_template_id']) && is_string($stored['default_template_id'])
            ? $stored['default_template_id']
            : ItAssetPrintTemplate::TEMPLATE_ID;
        if (! isset($present[$defaultId])) {
            $defaultId = isset($present[ItAssetPrintTemplate::TEMPLATE_ID])
                ? ItAssetPrintTemplate::TEMPLATE_ID
                : (string) (array_key_first($present) ?? ItAssetPrintTemplate::TEMPLATE_ID);
        }

        $stored['templates'] = array_values($templates);
        $stored['seeded_template_ids'] = array_keys($seeded);
        $stored['default_template_id'] = $defaultId;
        $entity->print_settings_json = $stored;
        $entity->save();
    }
}
