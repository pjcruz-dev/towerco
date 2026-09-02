<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynField;
use App\Modules\DynamicEntities\Models\DynRecord;
use App\Modules\DynamicEntities\Support\AtcPmRelatedTabs;
use App\Modules\DynamicEntities\Support\AtcTicketingPack;
use App\Modules\DynamicEntities\Support\DynModulePack;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds ATC Dynamic Ticketing entities/fields (greenfield — dump has no ticket tables).
 */
final class AtcTicketingPackSeedService
{
    /**
     * @return array{entities: int, fields: int, categories: int, related_tabs: bool}
     */
    public function seed(?string $actorId = null): array
    {
        $stats = ['entities' => 0, 'fields' => 0, 'categories' => 0, 'related_tabs' => false];

        DB::transaction(function () use ($actorId, &$stats): void {
            $slugToId = [];

            foreach (AtcTicketingPack::entityDefinitions() as $def) {
                $entity = DynEntity::query()->updateOrCreate(
                    ['slug' => $def['slug']],
                    [
                        'name' => $def['name'],
                        'description' => $def['description'],
                        'module_pack' => DynModulePack::TICKETING,
                        'storage_mode' => 'json',
                        'is_location_based' => false,
                        'source_linked_table' => null,
                        'sort_order' => $def['sort_order'],
                        'is_active' => true,
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ]
                );
                $slugToId[$def['slug']] = $entity->id;
                $stats['entities']++;
            }

            // Second pass: fields (resolve relationship targets) + related tabs on site_tickets.
            foreach (AtcTicketingPack::entityDefinitions() as $def) {
                $entityId = $slugToId[$def['slug']];
                foreach ($def['fields'] as $fieldDef) {
                    $targetId = null;
                    if (($fieldDef['type'] ?? '') === 'relationship' && ! empty($fieldDef['target_slug'])) {
                        $targetId = $slugToId[$fieldDef['target_slug']]
                            ?? DynEntity::query()->where('slug', $fieldDef['target_slug'])->value('id');
                    }

                    DynField::query()->updateOrCreate(
                        [
                            'entity_id' => $entityId,
                            'name' => $fieldDef['name'],
                        ],
                        [
                            'label' => $fieldDef['label'],
                            'type' => $fieldDef['type'],
                            'is_required' => (bool) ($fieldDef['is_required'] ?? false),
                            'show_in_table' => (bool) ($fieldDef['show_in_table'] ?? false),
                            'is_filterable' => (bool) ($fieldDef['is_filterable'] ?? false),
                            'options_json' => $fieldDef['options_json'] ?? null,
                            'target_entity_id' => $targetId,
                            'column_span' => (int) ($fieldDef['column_span'] ?? 6),
                            'field_order' => (int) ($fieldDef['field_order'] ?? 10),
                            'system_column' => $fieldDef['name'],
                        ]
                    );
                    $stats['fields']++;
                }

                if ($def['slug'] === 'site_tickets') {
                    DynEntity::query()->whereKey($entityId)->update([
                        'related_tabs_json' => AtcTicketingPack::forSiteTickets(),
                        'updated_by' => $actorId,
                    ]);
                }
            }

            $towerSites = DynEntity::query()->where('slug', 'tower_sites')->first();
            if ($towerSites !== null) {
                $tabs = AtcPmRelatedTabs::forTowerSites();
                $towerSites->related_tabs_json = $tabs;
                $towerSites->updated_by = $actorId;
                $towerSites->save();
                $stats['related_tabs'] = true;
            }

            $categoriesEntity = DynEntity::query()->where('slug', 'ticket_categories')->first();
            if ($categoriesEntity !== null) {
                $existing = DynRecord::query()
                    ->where('entity_id', $categoriesEntity->id)
                    ->where('is_deleted', false)
                    ->get(['title', 'values_json']);
                $existingTitles = $existing->pluck('title')->filter()->all();
                $existingCodes = $existing
                    ->map(fn (DynRecord $r): string => (string) (($r->values_json['code'] ?? '')))
                    ->filter(fn (string $c): bool => $c !== '')
                    ->all();

                foreach (AtcTicketingPack::defaultCategories() as $row) {
                    $code = (string) ($row['values']['code'] ?? '');
                    if (in_array($row['title'], $existingTitles, true)) {
                        continue;
                    }
                    if ($code !== '' && in_array($code, $existingCodes, true)) {
                        continue;
                    }

                    DynRecord::query()->create([
                        'id' => (string) Str::uuid(),
                        'entity_id' => $categoriesEntity->id,
                        'title' => $row['title'],
                        'status' => $row['status'],
                        'values_json' => $row['values'],
                        'is_deleted' => false,
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ]);
                    $stats['categories']++;
                    $existingTitles[] = $row['title'];
                    if ($code !== '') {
                        $existingCodes[] = $code;
                    }
                }
            }
        });

        return $stats;
    }
}
