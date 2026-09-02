<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\DynamicEntities\Models\AtcImportIdMap;
use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynField;
use App\Modules\DynamicEntities\Models\DynFieldGroup;
use App\Modules\DynamicEntities\Support\SqlDumpReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Imports Metacoresoft sys_field_groups + field form/view_group assignments.
 */
final class AtcFieldGroupImportService
{
    public function __construct(
        private readonly SqlDumpReader $reader,
    ) {}

    /**
     * @return array{groups: int, fields_assigned: int, entities: int, skipped: int}
     */
    public function importFromDump(string $dumpPath, bool $replaceExisting = false): array
    {
        $sql = $this->reader->readFile($dumpPath);
        $groupRows = $this->reader->extractInsertRows($sql, 'sys_field_groups');
        $fieldRows = $this->reader->extractInsertRows($sql, 'fields');

        $stats = ['groups' => 0, 'fields_assigned' => 0, 'entities' => 0, 'skipped' => 0];

        /** @var array<string, string> $entityIdMap */
        $entityIdMap = AtcImportIdMap::query()
            ->where('source_table', 'entities')
            ->where('target_type', 'entity')
            ->pluck('target_uuid', 'source_id')
            ->all();

        if ($entityIdMap === []) {
            foreach (DynEntity::query()->get(['id', 'sort_order']) as $entity) {
                $sourceId = (string) $entity->sort_order;
                if ($sourceId !== '' && $sourceId !== '0') {
                    $entityIdMap[$sourceId] = $entity->id;
                }
            }
        }

        DB::transaction(function () use ($groupRows, $fieldRows, $entityIdMap, $replaceExisting, &$stats): void {
            /** @var array<string, true> $importedEntities */
            $importedEntities = [];
            /** @var array<string, true> $skippedEntities */
            $skippedEntities = [];
            /** @var array<string, string> $groupIdMap */
            $groupIdMap = [];

            foreach ($groupRows as $row) {
                $sourceGroupId = (string) ($row['id'] ?? '');
                $sourceEntityId = (string) ($row['entity_id'] ?? '');
                $label = trim((string) ($row['label'] ?? ''));
                if ($sourceGroupId === '' || $sourceEntityId === '' || $label === '' || ! isset($entityIdMap[$sourceEntityId])) {
                    $stats['skipped']++;
                    continue;
                }

                $entityUuid = $entityIdMap[$sourceEntityId];
                if (isset($skippedEntities[$entityUuid])) {
                    $stats['skipped']++;
                    continue;
                }

                $entity = DynEntity::query()->find($entityUuid);
                if ($entity === null) {
                    $stats['skipped']++;
                    continue;
                }

                if (! isset($importedEntities[$entityUuid])) {
                    $decision = $this->decideEntityImport($entity, $replaceExisting);
                    if ($decision === 'skip') {
                        $skippedEntities[$entityUuid] = true;
                        $stats['skipped']++;
                        continue;
                    }
                    if ($decision === 'replace') {
                        $this->wipeEntityGroups($entity);
                    }
                    $importedEntities[$entityUuid] = true;
                    $stats['entities']++;
                }

                $context = strtolower((string) ($row['context'] ?? 'both'));
                $appliesForm = in_array($context, ['both', 'form', ''], true);
                $appliesView = in_array($context, ['both', 'view', ''], true);
                $sortOrder = (int) ($row['group_order'] ?? 0);
                if ($sortOrder < 0) {
                    $sortOrder = 0;
                }
                $normalizedSort = $sortOrder === 0 ? 10 : $sortOrder * 10;

                $payload = [
                    'entity_id' => $entityUuid,
                    'name' => $label,
                    'description' => ($row['description'] ?? '') !== '' ? (string) $row['description'] : null,
                    'icon' => ($row['icon'] ?? '') !== '' ? (string) $row['icon'] : null,
                    'applies_to_form' => $appliesForm,
                    'applies_to_view' => $appliesView,
                    'start_collapsed' => (bool) ((int) ($row['is_collapsed'] ?? 0)),
                    'sort_order' => $normalizedSort,
                ];

                $mappedUuid = AtcImportIdMap::query()
                    ->where('source_table', 'sys_field_groups')
                    ->where('source_id', $sourceGroupId)
                    ->where('target_type', 'field_group')
                    ->value('target_uuid');

                $group = $mappedUuid
                    ? DynFieldGroup::query()->find((string) $mappedUuid)
                    : null;

                if ($group !== null) {
                    $group->fill($payload);
                    $group->save();
                } else {
                    $group = DynFieldGroup::query()->create($payload);
                    AtcImportIdMap::query()->updateOrCreate(
                        [
                            'source_table' => 'sys_field_groups',
                            'source_id' => $sourceGroupId,
                            'target_type' => 'field_group',
                        ],
                        ['target_uuid' => $group->id]
                    );
                }

                $groupIdMap[$sourceGroupId] = $group->id;
                $stats['groups']++;
            }

            foreach (AtcImportIdMap::query()
                ->where('source_table', 'sys_field_groups')
                ->where('target_type', 'field_group')
                ->pluck('target_uuid', 'source_id') as $sourceId => $uuid) {
                $groupIdMap[(string) $sourceId] = (string) $uuid;
            }

            foreach ($fieldRows as $row) {
                $sourceEntityId = (string) ($row['entity_id'] ?? '');
                $fieldName = Str::snake((string) ($row['name'] ?? ''));
                if ($sourceEntityId === '' || $fieldName === '' || ! isset($entityIdMap[$sourceEntityId])) {
                    continue;
                }

                $entityUuid = $entityIdMap[$sourceEntityId];
                if (! isset($importedEntities[$entityUuid])) {
                    continue;
                }

                $formGroupSource = (string) ($row['form_group_id'] ?? '');
                $viewGroupSource = (string) ($row['view_group_id'] ?? '');
                $isSystem = (bool) ((int) ($row['is_system_field'] ?? 0));
                $formGroupId = (! $isSystem && $formGroupSource !== '' && isset($groupIdMap[$formGroupSource]))
                    ? $groupIdMap[$formGroupSource]
                    : null;
                $viewGroupId = (! $isSystem && $viewGroupSource !== '' && isset($groupIdMap[$viewGroupSource]))
                    ? $groupIdMap[$viewGroupSource]
                    : ($isSystem ? null : $formGroupId);

                $updated = DynField::query()
                    ->where('entity_id', $entityUuid)
                    ->where('name', $fieldName)
                    ->update([
                        'form_group_id' => $formGroupId,
                        'view_group_id' => $viewGroupId,
                    ]);

                if ($updated > 0 && ($formGroupId !== null || $viewGroupId !== null)) {
                    $stats['fields_assigned']++;
                }
            }

            // Drop empty groups left over from Metacore defaults (e.g. Basic Information with only system ID).
            foreach (array_keys($importedEntities) as $entityUuid) {
                DynField::query()
                    ->where('entity_id', $entityUuid)
                    ->where('is_system_field', true)
                    ->update(['form_group_id' => null, 'view_group_id' => null, 'show_in_table' => false]);

                $groups = DynFieldGroup::query()->where('entity_id', $entityUuid)->get();
                $emptyIds = [];
                foreach ($groups as $group) {
                    $hasFields = DynField::query()
                        ->where(function ($q) use ($group): void {
                            $q->where('form_group_id', $group->id)->orWhere('view_group_id', $group->id);
                        })
                        ->where('is_system_field', false)
                        ->exists();
                    if (! $hasFields) {
                        $emptyIds[] = $group->id;
                    }
                }
                if ($emptyIds === []) {
                    continue;
                }
                AtcImportIdMap::query()
                    ->where('source_table', 'sys_field_groups')
                    ->where('target_type', 'field_group')
                    ->whereIn('target_uuid', $emptyIds)
                    ->delete();
                DynFieldGroup::query()->whereIn('id', $emptyIds)->delete();
            }
        });

        return $stats;
    }

    /**
     * @return 'import'|'replace'|'skip'
     */
    private function decideEntityImport(DynEntity $entity, bool $replaceExisting): string
    {
        if ($replaceExisting) {
            return 'replace';
        }

        if (! $entity->fieldGroups()->exists()) {
            return 'import';
        }

        $groupIds = $entity->fieldGroups()->pluck('id');
        $hasMapped = AtcImportIdMap::query()
            ->where('source_table', 'sys_field_groups')
            ->where('target_type', 'field_group')
            ->whereIn('target_uuid', $groupIds)
            ->exists();

        // Hand-seeded print layouts (no dump map) stay intact.
        return $hasMapped ? 'import' : 'skip';
    }

    private function wipeEntityGroups(DynEntity $entity): void
    {
        $existingIds = $entity->fieldGroups()->pluck('id');
        DynField::query()
            ->where('entity_id', $entity->id)
            ->update(['form_group_id' => null, 'view_group_id' => null]);
        AtcImportIdMap::query()
            ->where('source_table', 'sys_field_groups')
            ->where('target_type', 'field_group')
            ->whereIn('target_uuid', $existingIds)
            ->delete();
        DynFieldGroup::query()->where('entity_id', $entity->id)->delete();
    }
}
