<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynField;
use App\Modules\DynamicEntities\Models\DynFieldGroup;
use App\Modules\DynamicEntities\Support\AtcPrintTemplates;
use Illuminate\Support\Facades\DB;

final class DynPrintLayoutService
{
    /**
     * Apply default print settings + field groups for entities (idempotent).
     *
     * Known ATC packs get bespoke document templates; all other entities get a
     * sensible grouped Print layout so every list Print button works.
     */
    public function ensureForEntity(DynEntity $entity): void
    {
        $defaults = AtcPrintTemplates::defaultsForSlug($entity->slug);
        $current = is_array($entity->print_settings_json) ? $entity->print_settings_json : null;
        $label = is_array($current) ? (string) ($current['list_label'] ?? '') : '';
        $layout = is_array($current) ? (string) ($current['layout'] ?? '') : '';

        $knownPacks = [
            'site_permits',
            'land_leases',
            'saq_trackers',
            'power_trackers',
            'postcon_trackers',
            'construction_projects',
            'tower_sites',
            'procurement_requests',
            'site_tickets',
            'sales_transactions',
            'purchase_transactions',
            'bir_form_2307_certificates',
        ];

        $defaultLayout = (string) ($defaults['layout'] ?? 'grouped');
        $shouldUpgrade = $current === null || $current === [];
        if (! $shouldUpgrade && in_array($entity->slug, $knownPacks, true)) {
            $shouldUpgrade = $label === ''
                || $label === 'Print'
                || $layout === 'saq_status_report'
                || ($defaultLayout !== 'grouped' && ($layout === '' || $layout === 'grouped'));
        }

        if ($shouldUpgrade) {
            $entity->print_settings_json = $defaults;
            $entity->save();
        }

        $groups = match ($entity->slug) {
            'site_permits' => AtcPrintTemplates::sitePermitsGroups(),
            'land_leases' => AtcPrintTemplates::landLeasesGroups(),
            default => [],
        };

        if ($groups !== []) {
            $this->ensureGroups($entity, $groups);
        }
    }

    public function ensureAllKnown(): int
    {
        $count = 0;
        foreach (DynEntity::query()->where('is_active', true)->orderBy('sort_order')->get() as $entity) {
            $this->ensureForEntity($entity);
            $count++;
        }

        return $count;
    }

    /**
     * @param  list<array{name: string, sort_order: int, start_collapsed: bool, fields: list<string>}>  $defs
     */
    private function ensureGroups(DynEntity $entity, array $defs): void
    {
        if ($entity->fieldGroups()->exists()) {
            DynField::query()
                ->where('entity_id', $entity->id)
                ->where('is_system_field', true)
                ->update(['show_in_table' => false]);

            return;
        }

        DB::transaction(function () use ($entity, $defs): void {
            foreach ($defs as $def) {
                $group = DynFieldGroup::query()->create([
                    'entity_id' => $entity->id,
                    'name' => $def['name'],
                    'applies_to_form' => true,
                    'applies_to_view' => true,
                    'start_collapsed' => $def['start_collapsed'],
                    'sort_order' => $def['sort_order'],
                ]);

                if ($def['fields'] !== []) {
                    DynField::query()
                        ->where('entity_id', $entity->id)
                        ->whereIn('name', $def['fields'])
                        ->update([
                            'form_group_id' => $group->id,
                            'view_group_id' => $group->id,
                        ]);
                }
            }

            DynField::query()
                ->where('entity_id', $entity->id)
                ->where('is_system_field', true)
                ->update(['show_in_table' => false]);
        });
    }
}
