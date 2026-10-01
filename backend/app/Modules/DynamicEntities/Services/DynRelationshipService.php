<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynField;
use App\Modules\DynamicEntities\Models\DynRecord;
use App\Modules\DynamicEntities\Support\DynFieldType;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Workspace\Services\TenantActivityLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Relationship Studio graph + bidirectional edge sync (field ↔ related tab).
 */
final class DynRelationshipService
{
    private const LAYOUT_CACHE_KEY = 'dyn.relationship_studio.layout';

    public function __construct(
        private readonly DynEntityAdminService $admin,
        private readonly TenantActivityLogger $activity,
    ) {}

    /**
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>, layout: array<string, array{x: float, y: float}>, viewport: array<string, mixed>|null}
     */
    public function graph(?string $modulePack = null): array
    {
        $query = DynEntity::query()
            ->where('is_active', true)
            ->with(['fields' => static function ($q): void {
                $q->orderBy('field_order');
            }])
            ->orderBy('module_pack')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($modulePack !== null && $modulePack !== '' && $modulePack !== 'all') {
            $query->where('module_pack', $modulePack);
        }

        $entities = $query->get();
        $byId = $entities->keyBy('id');
        $bySlug = $entities->keyBy('slug');

        $nodes = [];
        $edges = [];
        $edgeKeys = [];

        foreach ($entities as $entity) {
            $keyFields = $entity->fields
                ->filter(static fn (DynField $f): bool => (bool) $f->is_key || $f->type === DynFieldType::RELATIONSHIP)
                ->take(8)
                ->values()
                ->map(static fn (DynField $f): array => [
                    'id' => $f->id,
                    'name' => $f->name,
                    'label' => $f->label,
                    'type' => $f->type,
                    'is_key' => (bool) $f->is_key,
                    'target_entity_id' => $f->target_entity_id,
                ])
                ->all();

            $nodes[] = [
                'id' => $entity->id,
                'slug' => $entity->slug,
                'name' => $entity->name,
                'module_pack' => $entity->module_pack,
                'fields' => $keyFields,
            ];

            foreach ($entity->fields as $field) {
                if ($field->type !== DynFieldType::RELATIONSHIP || ! $field->target_entity_id) {
                    continue;
                }
                if (! $byId->has($field->target_entity_id)) {
                    continue;
                }

                $edgeId = 'rel:'.$field->id;
                $edgeKeys[$edgeId] = true;
                $edges[] = [
                    'id' => $edgeId,
                    'kind' => 'relationship',
                    'source_entity_id' => $entity->id,
                    'target_entity_id' => $field->target_entity_id,
                    'field_id' => $field->id,
                    'field_name' => $field->name,
                    'field_label' => $field->label,
                    'cardinality' => 'n:1',
                    'related_tab_label' => $this->findRelatedTabLabel(
                        $byId->get($field->target_entity_id),
                        (string) $entity->slug,
                        (string) $field->name,
                    ),
                    'usage_count' => $this->relationshipUsageCount($field),
                ];
            }

            $tabs = is_array($entity->related_tabs_json) ? $entity->related_tabs_json : [];
            foreach ($tabs as $index => $tab) {
                if (! is_array($tab)) {
                    continue;
                }
                $childSlug = (string) ($tab['entity_slug'] ?? '');
                $foreignField = (string) ($tab['foreign_field'] ?? '');
                if ($childSlug === '' || ! $bySlug->has($childSlug)) {
                    continue;
                }
                $child = $bySlug->get($childSlug);
                $edgeId = 'tab:'.$entity->id.':'.$childSlug.':'.$foreignField.':'.$index;
                $duplicate = false;
                foreach ($edges as $existing) {
                    if (
                        $existing['kind'] === 'relationship'
                        && $existing['source_entity_id'] === $child->id
                        && $existing['target_entity_id'] === $entity->id
                        && ($foreignField === '' || $existing['field_name'] === $foreignField)
                    ) {
                        $duplicate = true;
                        break;
                    }
                }
                if ($duplicate || isset($edgeKeys[$edgeId])) {
                    continue;
                }
                $edgeKeys[$edgeId] = true;
                $edges[] = [
                    'id' => $edgeId,
                    'kind' => 'related_tab',
                    'source_entity_id' => $child->id,
                    'target_entity_id' => $entity->id,
                    'field_id' => null,
                    'field_name' => $foreignField !== '' ? $foreignField : null,
                    'field_label' => (string) ($tab['label'] ?? $child->name),
                    'cardinality' => 'n:1',
                    'related_tab_label' => (string) ($tab['label'] ?? $child->name),
                ];
            }
        }

        $layoutState = $this->readLayout();

        return [
            'nodes' => $nodes,
            'edges' => $edges,
            'layout' => $layoutState['positions'],
            'viewport' => $layoutState['viewport'],
        ];
    }

    /**
     * Create A→B relationship field and sync B's related tab (inverse).
     *
     * @param  array{source_entity_id: string, target_entity_id: string, label?: string, name?: string, related_tab_label?: string, sync_related_tab?: bool}  $data
     * @return array<string, mixed>
     */
    public function upsertEdge(array $data, TenantUser $actor): array
    {
        $sourceId = (string) ($data['source_entity_id'] ?? '');
        $targetId = (string) ($data['target_entity_id'] ?? '');
        if ($sourceId === '' || $targetId === '') {
            throw ValidationException::withMessages([
                'source_entity_id' => ['Source and target entities are required.'],
            ]);
        }
        if ($sourceId === $targetId) {
            throw ValidationException::withMessages([
                'target_entity_id' => ['An entity cannot relate to itself.'],
            ]);
        }

        $source = DynEntity::query()->whereKey($sourceId)->firstOrFail();
        $target = DynEntity::query()->whereKey($targetId)->firstOrFail();
        $syncTab = array_key_exists('sync_related_tab', $data) ? (bool) $data['sync_related_tab'] : true;

        $label = trim((string) ($data['label'] ?? $target->name));
        if ($label === '') {
            $label = $target->name;
        }
        $name = isset($data['name']) && is_string($data['name']) && trim($data['name']) !== ''
            ? Str::snake(trim($data['name']))
            : Str::snake($label);

        return DB::transaction(function () use ($source, $target, $label, $name, $syncTab, $data, $actor): array {
            $existing = DynField::query()
                ->where('entity_id', $source->id)
                ->where('type', DynFieldType::RELATIONSHIP)
                ->where('target_entity_id', $target->id)
                ->where('name', $name)
                ->first();

            if ($existing) {
                $field = $this->admin->updateField($existing, [
                    'label' => $label,
                    'target_entity_id' => $target->id,
                    'type' => DynFieldType::RELATIONSHIP,
                ], $actor);
            } else {
                $maxOrder = (int) DynField::query()->where('entity_id', $source->id)->max('field_order');
                $field = $this->admin->createField($source, [
                    'name' => $name,
                    'label' => $label,
                    'type' => DynFieldType::RELATIONSHIP,
                    'target_entity_id' => $target->id,
                    'show_in_table' => true,
                    'is_filterable' => true,
                    'field_order' => $maxOrder + 10,
                    'column_span' => 6,
                ], $actor);
            }

            // createField/updateField already call sync — pass tab label override when provided.
            if ($syncTab) {
                $tabLabel = isset($data['related_tab_label']) && is_string($data['related_tab_label'])
                    ? trim($data['related_tab_label'])
                    : $source->name;
                $this->syncInverseRelatedTab($field, $tabLabel !== '' ? $tabLabel : $source->name);
            }

            $this->activity->record(
                module: 'dynamic_entities',
                action: 'dyn_relationship.upserted',
                summary: 'Relationship · '.$source->name.' → '.$target->name,
                entityType: 'dyn_field',
                entityId: (string) $field->id,
                entityLabel: (string) $field->label,
                actor: $actor,
                metadata: [
                    'source_slug' => $source->slug,
                    'target_slug' => $target->slug,
                    'field_name' => $field->name,
                ],
            );

            return $this->presentEdge($field);
        });
    }

    /**
     * @param  array{label?: string, target_entity_id?: string, related_tab_label?: string, sync_related_tab?: bool}  $data
     * @return array<string, mixed>
     */
    public function updateEdge(string $fieldId, array $data, TenantUser $actor): array
    {
        $field = DynField::query()->whereKey($fieldId)->firstOrFail();
        if ($field->type !== DynFieldType::RELATIONSHIP) {
            throw ValidationException::withMessages([
                'field_id' => ['Only relationship fields can be updated as edges.'],
            ]);
        }

        $previousTargetId = $field->target_entity_id;
        $previousName = $field->name;
        $source = $field->entity;
        if ($source === null) {
            throw ValidationException::withMessages(['field_id' => ['Field has no entity.']]);
        }

        $payload = [];
        if (isset($data['label'])) {
            $payload['label'] = (string) $data['label'];
        }
        if (array_key_exists('target_entity_id', $data)) {
            $targetId = $data['target_entity_id'] !== null ? (string) $data['target_entity_id'] : null;
            if ($targetId === $source->id) {
                throw ValidationException::withMessages([
                    'target_entity_id' => ['An entity cannot relate to itself.'],
                ]);
            }
            $payload['target_entity_id'] = $targetId;
            $payload['type'] = DynFieldType::RELATIONSHIP;
        }

        return DB::transaction(function () use ($field, $payload, $data, $actor, $previousTargetId, $previousName, $source): array {
            if ($previousTargetId && (
                (array_key_exists('target_entity_id', $payload) && $payload['target_entity_id'] !== $previousTargetId)
            )) {
                $oldTarget = DynEntity::query()->whereKey($previousTargetId)->first();
                if ($oldTarget) {
                    $this->removeInverseRelatedTab($oldTarget, (string) $source->slug, (string) $previousName);
                }
            }

            $updated = $payload === []
                ? $field->refresh()
                : $this->admin->updateField($field, $payload, $actor);

            $syncTab = array_key_exists('sync_related_tab', $data) ? (bool) $data['sync_related_tab'] : true;
            if ($syncTab && $updated->target_entity_id) {
                $tabLabel = isset($data['related_tab_label']) && is_string($data['related_tab_label'])
                    ? trim($data['related_tab_label'])
                    : null;
                $this->syncInverseRelatedTab($updated, $tabLabel);
            }

            if ($syncTab === false && $updated->target_entity_id) {
                $target = DynEntity::query()->whereKey($updated->target_entity_id)->first();
                if ($target) {
                    $this->removeInverseRelatedTab($target, (string) $source->slug, (string) $updated->name);
                }
            }

            return $this->presentEdge($updated->refresh());
        });
    }

    /**
     * How many records currently store a non-empty value for this relationship field.
     */
    public function relationshipUsageCount(DynField $field): int
    {
        $name = (string) $field->name;
        if ($name === '') {
            return 0;
        }

        return (int) DynRecord::query()
            ->where('entity_id', $field->entity_id)
            ->where('is_deleted', false)
            ->whereNotNull('values_json->'.$name)
            ->where('values_json->'.$name, '!=', '')
            ->where('values_json->'.$name, '!=', 'null')
            ->count();
    }

    /**
     * Soft-disable: clear target, remove inverse tab, keep the field row (values retained).
     *
     * @return array<string, mixed>
     */
    public function softDisableEdge(string $fieldId, TenantUser $actor): array
    {
        $field = DynField::query()->whereKey($fieldId)->firstOrFail();
        if ($field->type !== DynFieldType::RELATIONSHIP) {
            throw ValidationException::withMessages([
                'field_id' => ['Only relationship fields can be soft-disabled.'],
            ]);
        }

        $presented = $this->presentEdge($field);
        $source = $field->entity;
        $targetId = $field->target_entity_id;
        $fieldName = $field->name;

        DB::transaction(function () use ($field, $targetId, $fieldName, $source, $actor): void {
            if ($targetId && $source) {
                $target = DynEntity::query()->whereKey($targetId)->first();
                if ($target) {
                    $this->removeInverseRelatedTab($target, (string) $source->slug, (string) $fieldName);
                }
            }

            $options = is_array($field->options_json) ? $field->options_json : [];
            $options['relationship_disabled'] = true;
            $options['relationship_disabled_at'] = now()->toIso8601String();

            $this->admin->updateField($field, [
                'target_entity_id' => null,
                'show_in_table' => false,
                'is_filterable' => false,
                'options_json' => $options,
                'type' => DynFieldType::RELATIONSHIP,
            ], $actor);

            $this->activity->record(
                module: 'dynamic_entities',
                action: 'dyn_relationship.soft_disabled',
                summary: 'Relationship soft-disabled · '.($source?->name ?? 'Entity').' / '.$field->label,
                entityType: 'dyn_field',
                entityId: (string) $field->id,
                entityLabel: (string) $field->label,
                actor: $actor,
                metadata: [
                    'entity_slug' => $source?->slug,
                    'field_name' => $fieldName,
                    'previous_target_entity_id' => $targetId,
                ],
            );
        });

        return [
            ...$presented,
            'soft_disabled' => true,
            'usage_count' => $this->relationshipUsageCount($field->refresh()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteEdge(string $fieldId, TenantUser $actor, bool $force = false, bool $softDisable = false): array
    {
        if ($softDisable) {
            return $this->softDisableEdge($fieldId, $actor);
        }

        $field = DynField::query()->whereKey($fieldId)->firstOrFail();
        if ($field->type !== DynFieldType::RELATIONSHIP) {
            throw ValidationException::withMessages([
                'field_id' => ['Only relationship fields can be deleted as edges.'],
            ]);
        }
        if ($field->is_system_field) {
            throw ValidationException::withMessages([
                'field_id' => ['System relationship fields cannot be deleted.'],
            ]);
        }

        $usage = $this->relationshipUsageCount($field);
        if ($usage > 0 && ! $force) {
            throw ValidationException::withMessages([
                'field_id' => [
                    "This relationship is used on {$usage} record(s). Pass force=1 to delete anyway, or soft_disable=1 to detach the target while keeping values.",
                ],
                'usage_count' => [(string) $usage],
            ]);
        }

        $presented = $this->presentEdge($field);
        $presented['usage_count'] = $usage;
        $source = $field->entity;
        $targetId = $field->target_entity_id;
        $fieldName = $field->name;
        $sourceSlug = $source?->slug;

        DB::transaction(function () use ($field, $targetId, $fieldName, $sourceSlug, $actor, $source): void {
            if ($targetId && $sourceSlug) {
                $target = DynEntity::query()->whereKey($targetId)->first();
                if ($target) {
                    $this->removeInverseRelatedTab($target, $sourceSlug, $fieldName);
                }
            }

            $label = $field->label;
            $id = (string) $field->id;
            $field->delete();

            $this->activity->record(
                module: 'dynamic_entities',
                action: 'dyn_relationship.deleted',
                summary: 'Relationship removed · '.($source?->name ?? 'Entity').' / '.$label,
                entityType: 'dyn_field',
                entityId: $id,
                entityLabel: $label,
                actor: $actor,
                metadata: [
                    'entity_slug' => $sourceSlug,
                    'field_name' => $fieldName,
                    'target_entity_id' => $targetId,
                ],
            );
        });

        return $presented;
    }

    /**
     * Keep target entity related_tabs_json in sync when a relationship field is created/updated
     * (Manage Fields and Relationship Studio share this path).
     */
    public function syncInverseRelatedTab(DynField $field, ?string $tabLabel = null): void
    {
        if ($field->type !== DynFieldType::RELATIONSHIP || ! $field->target_entity_id) {
            return;
        }

        $source = $field->entity ?? DynEntity::query()->whereKey($field->entity_id)->first();
        $target = DynEntity::query()->whereKey($field->target_entity_id)->first();
        if ($source === null || $target === null) {
            return;
        }

        $tabs = is_array($target->related_tabs_json) ? $target->related_tabs_json : [];
        $label = $tabLabel !== null && trim($tabLabel) !== '' ? trim($tabLabel) : $source->name;
        $found = false;

        foreach ($tabs as $i => $tab) {
            if (! is_array($tab)) {
                continue;
            }
            if (
                (string) ($tab['entity_slug'] ?? '') === (string) $source->slug
                && (string) ($tab['foreign_field'] ?? '') === (string) $field->name
            ) {
                $tabs[$i]['label'] = $label;
                $tabs[$i]['entity_slug'] = $source->slug;
                $tabs[$i]['foreign_field'] = $field->name;
                $found = true;
                break;
            }
        }

        if (! $found) {
            $tabs[] = [
                'entity_slug' => $source->slug,
                'foreign_field' => $field->name,
                'label' => $label,
            ];
        }

        $target->related_tabs_json = array_values($tabs);
        $target->save();
    }

    public function removeInverseRelatedTab(DynEntity $target, string $sourceSlug, string $foreignField): void
    {
        $tabs = is_array($target->related_tabs_json) ? $target->related_tabs_json : [];
        $next = [];
        foreach ($tabs as $tab) {
            if (! is_array($tab)) {
                continue;
            }
            if (
                (string) ($tab['entity_slug'] ?? '') === $sourceSlug
                && (string) ($tab['foreign_field'] ?? '') === $foreignField
            ) {
                continue;
            }
            $next[] = $tab;
        }
        $target->related_tabs_json = array_values($next);
        $target->save();
    }

    /**
     * After Manage Fields mutates a field — sync or clear inverse tab.
     */
    public function syncAfterFieldMutation(DynField $field, ?string $previousTargetId = null, ?string $previousName = null): void
    {
        $source = $field->entity ?? DynEntity::query()->whereKey($field->entity_id)->first();
        if ($source === null) {
            return;
        }

        if (
            $previousTargetId
            && (
                $field->type !== DynFieldType::RELATIONSHIP
                || (string) $field->target_entity_id !== (string) $previousTargetId
                || ($previousName !== null && $previousName !== $field->name)
            )
        ) {
            $oldTarget = DynEntity::query()->whereKey($previousTargetId)->first();
            if ($oldTarget) {
                $this->removeInverseRelatedTab(
                    $oldTarget,
                    (string) $source->slug,
                    $previousName ?? (string) $field->name,
                );
            }
        }

        if ($field->type === DynFieldType::RELATIONSHIP && $field->target_entity_id) {
            $this->syncInverseRelatedTab($field);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function presentEdge(DynField $field): array
    {
        $field->loadMissing('entity');
        $relatedTabLabel = null;
        if ($field->target_entity_id && $field->entity) {
            $target = DynEntity::query()->whereKey($field->target_entity_id)->first();
            if ($target) {
                $relatedTabLabel = $this->findRelatedTabLabel(
                    $target,
                    (string) $field->entity->slug,
                    (string) $field->name,
                );
            }
        }

        return [
            'id' => 'rel:'.$field->id,
            'kind' => 'relationship',
            'source_entity_id' => $field->entity_id,
            'target_entity_id' => $field->target_entity_id,
            'field_id' => $field->id,
            'field_name' => $field->name,
            'field_label' => $field->label,
            'cardinality' => 'n:1',
            'related_tab_label' => $relatedTabLabel,
            'usage_count' => $this->relationshipUsageCount($field),
        ];
    }

    private function findRelatedTabLabel(?DynEntity $target, string $sourceSlug, string $foreignField): ?string
    {
        if ($target === null) {
            return null;
        }
        $tabs = is_array($target->related_tabs_json) ? $target->related_tabs_json : [];
        foreach ($tabs as $tab) {
            if (! is_array($tab)) {
                continue;
            }
            if (
                (string) ($tab['entity_slug'] ?? '') === $sourceSlug
                && (string) ($tab['foreign_field'] ?? '') === $foreignField
            ) {
                return isset($tab['label']) ? (string) $tab['label'] : null;
            }
        }

        return null;
    }

    /**
     * @param  array<string, array{x?: float|int, y?: float|int}>  $positions
     * @param  array<string, mixed>|null  $viewport
     * @return array{positions: array<string, array{x: float, y: float}>, viewport: array<string, mixed>|null}
     */
    public function saveLayout(array $positions, ?array $viewport = null): array
    {
        $clean = [];
        foreach ($positions as $entityId => $pos) {
            if (! is_string($entityId) || $entityId === '' || ! is_array($pos)) {
                continue;
            }
            if (! array_key_exists('x', $pos) || ! array_key_exists('y', $pos)) {
                continue;
            }
            $clean[$entityId] = [
                'x' => (float) $pos['x'],
                'y' => (float) $pos['y'],
            ];
        }

        $payload = [
            'positions' => $clean,
            'viewport' => is_array($viewport) ? $viewport : null,
            'updated_at' => now()->toIso8601String(),
        ];

        Cache::forever($this->layoutCacheKey(), $payload);

        return [
            'positions' => $clean,
            'viewport' => $payload['viewport'],
        ];
    }

    /**
     * @return array{positions: array<string, array{x: float, y: float}>, viewport: array<string, mixed>|null}
     */
    public function readLayout(): array
    {
        $raw = Cache::get($this->layoutCacheKey());
        if (! is_array($raw)) {
            return ['positions' => [], 'viewport' => null];
        }

        $positions = [];
        $rawPositions = is_array($raw['positions'] ?? null) ? $raw['positions'] : [];
        foreach ($rawPositions as $entityId => $pos) {
            if (! is_string($entityId) || ! is_array($pos)) {
                continue;
            }
            if (! array_key_exists('x', $pos) || ! array_key_exists('y', $pos)) {
                continue;
            }
            $positions[$entityId] = [
                'x' => (float) $pos['x'],
                'y' => (float) $pos['y'],
            ];
        }

        return [
            'positions' => $positions,
            'viewport' => is_array($raw['viewport'] ?? null) ? $raw['viewport'] : null,
        ];
    }

    public function clearLayout(): void
    {
        Cache::forget($this->layoutCacheKey());
    }

    private function layoutCacheKey(): string
    {
        $tenantId = (string) (tenant('id') ?? 'central');

        return self::LAYOUT_CACHE_KEY.'.'.$tenantId;
    }
}
