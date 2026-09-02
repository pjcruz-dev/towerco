<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynField;
use App\Modules\DynamicEntities\Models\DynFieldGroup;
use App\Modules\DynamicEntities\Support\AtcPrintTemplates;
use App\Modules\DynamicEntities\Support\DynFieldType;
use App\Modules\DynamicEntities\Support\DynModulePack;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Workspace\Services\TenantActivityLogger;
use App\Modules\Workspace\Support\WorkspaceAuditChanges;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DynEntityAdminService
{
    public function __construct(
        private readonly TenantActivityLogger $activity,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createEntity(array $data, TenantUser $actor): DynEntity
    {
        $slug = Str::slug((string) ($data['slug'] ?? $data['name'] ?? ''), '_');
        if ($slug === '') {
            throw ValidationException::withMessages(['slug' => 'A valid slug is required.']);
        }

        if (DynEntity::query()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['slug' => 'An entity with this slug already exists.']);
        }

        $pack = (string) ($data['module_pack'] ?? DynModulePack::PM);
        if (! in_array($pack, DynModulePack::all(), true)) {
            throw ValidationException::withMessages(['module_pack' => 'Invalid module pack.']);
        }

        $entity = DynEntity::query()->create([
            'slug' => $slug,
            'name' => (string) $data['name'],
            'description' => isset($data['description']) ? (string) $data['description'] : null,
            'module_pack' => $pack,
            'storage_mode' => (string) ($data['storage_mode'] ?? 'json'),
            'is_location_based' => (bool) ($data['is_location_based'] ?? false),
            'source_linked_table' => isset($data['source_linked_table']) ? (string) $data['source_linked_table'] : null,
            'related_tabs_json' => $data['related_tabs_json'] ?? null,
            'print_settings_json' => $data['print_settings_json']
                ?? AtcPrintTemplates::defaultsForSlug($slug),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $this->activity->record(
            module: 'dynamic_entities',
            action: 'dyn_entity.created',
            summary: 'Entity created · '.$entity->name,
            entityType: 'dyn_entity',
            entityId: (string) $entity->slug,
            entityLabel: (string) $entity->name,
            actor: $actor,
            metadata: ['entity_slug' => (string) $entity->slug, 'module_pack' => $entity->module_pack],
            changes: WorkspaceAuditChanges::of([
                'slug' => ['from' => null, 'to' => $entity->slug],
                'name' => ['from' => null, 'to' => $entity->name],
            ]),
        );

        return $entity;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateEntity(DynEntity $entity, array $data, TenantUser $actor): DynEntity
    {
        $before = [
            'name' => $entity->name,
            'description' => $entity->description,
            'module_pack' => $entity->module_pack,
            'is_active' => $entity->is_active,
            'sort_order' => $entity->sort_order,
        ];

        if (isset($data['name'])) {
            $entity->name = (string) $data['name'];
        }
        if (array_key_exists('description', $data)) {
            $entity->description = $data['description'] !== null ? (string) $data['description'] : null;
        }
        if (isset($data['module_pack'])) {
            $pack = (string) $data['module_pack'];
            if (! in_array($pack, DynModulePack::all(), true)) {
                throw ValidationException::withMessages(['module_pack' => 'Invalid module pack.']);
            }
            $entity->module_pack = $pack;
        }
        if (isset($data['sort_order'])) {
            $entity->sort_order = (int) $data['sort_order'];
        }
        if (array_key_exists('is_active', $data)) {
            $entity->is_active = (bool) $data['is_active'];
        }
        if (array_key_exists('related_tabs_json', $data)) {
            $entity->related_tabs_json = $data['related_tabs_json'];
        }
        if (array_key_exists('print_settings_json', $data)) {
            $entity->print_settings_json = $data['print_settings_json'];
        }
        $entity->updated_by = $actor->id;
        $entity->save();

        $entity = $entity->refresh();

        $this->activity->record(
            module: 'dynamic_entities',
            action: 'dyn_entity.updated',
            summary: 'Entity updated · '.$entity->name,
            entityType: 'dyn_entity',
            entityId: (string) $entity->slug,
            entityLabel: (string) $entity->name,
            actor: $actor,
            metadata: ['entity_slug' => (string) $entity->slug],
            changes: WorkspaceAuditChanges::diff($before, [
                'name' => $entity->name,
                'description' => $entity->description,
                'module_pack' => $entity->module_pack,
                'is_active' => $entity->is_active,
                'sort_order' => $entity->sort_order,
            ], array_keys($before)),
        );

        return $entity;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createField(DynEntity $entity, array $data, ?TenantUser $actor = null): DynField
    {
        $name = Str::snake((string) ($data['name'] ?? $data['label'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'A valid field name is required.']);
        }

        if ($entity->fields()->where('name', $name)->exists()) {
            throw ValidationException::withMessages(['name' => 'A field with this name already exists on the entity.']);
        }

        $type = (string) ($data['type'] ?? DynFieldType::TEXT);
        if (! in_array($type, DynFieldType::all(), true)) {
            throw ValidationException::withMessages(['type' => 'Invalid field type.']);
        }

        $field = DynField::query()->create([
            'entity_id' => $entity->id,
            'name' => $name,
            'label' => (string) ($data['label'] ?? $name),
            'type' => $type,
            'is_required' => (bool) ($data['is_required'] ?? false),
            'is_system_field' => (bool) ($data['is_system_field'] ?? false),
            'system_column' => isset($data['system_column']) ? (string) $data['system_column'] : null,
            'show_in_table' => (bool) ($data['show_in_table'] ?? false),
            'is_filterable' => (bool) ($data['is_filterable'] ?? false),
            'is_key' => (bool) ($data['is_key'] ?? false),
            'calculate_totals' => (bool) ($data['calculate_totals'] ?? false),
            'options_json' => $data['options_json'] ?? null,
            'target_entity_id' => $data['target_entity_id'] ?? null,
            'placeholder' => isset($data['placeholder']) ? (string) $data['placeholder'] : null,
            'formula_definition' => isset($data['formula_definition']) ? (string) $data['formula_definition'] : null,
            'column_span' => (int) ($data['column_span'] ?? 6),
            'field_order' => (int) ($data['field_order'] ?? 10),
            'form_group_id' => $data['form_group_id'] ?? null,
            'view_group_id' => $data['view_group_id'] ?? null,
            'conditional_rules_json' => $data['conditional_rules_json'] ?? null,
            'is_virtual' => (bool) ($data['is_virtual'] ?? false),
        ]);

        $this->activity->record(
            module: 'dynamic_entities',
            action: 'dyn_field.created',
            summary: 'Field created · '.$entity->name.' / '.$field->label,
            entityType: 'dyn_field',
            entityId: (string) $field->id,
            entityLabel: (string) $field->label,
            actor: $actor,
            metadata: [
                'entity_slug' => (string) $entity->slug,
                'field_name' => (string) $field->name,
                'field_type' => (string) $field->type,
            ],
            changes: WorkspaceAuditChanges::of([
                'name' => ['from' => null, 'to' => $field->name],
                'type' => ['from' => null, 'to' => $field->type],
            ]),
        );

        return $field;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateField(DynField $field, array $data, ?TenantUser $actor = null): DynField
    {
        $before = [
            'label' => $field->label,
            'type' => $field->type,
            'is_required' => $field->is_required,
            'show_in_table' => $field->show_in_table,
            'is_filterable' => $field->is_filterable,
            'is_key' => $field->is_key,
            'calculate_totals' => $field->calculate_totals,
            'is_virtual' => $field->is_virtual,
            'field_order' => $field->field_order,
            'column_span' => $field->column_span,
        ];

        if (isset($data['label'])) {
            $field->label = (string) $data['label'];
        }
        if (isset($data['type'])) {
            $type = (string) $data['type'];
            if (! in_array($type, DynFieldType::all(), true)) {
                throw ValidationException::withMessages(['type' => 'Invalid field type.']);
            }
            if ($field->is_system_field && $type !== (string) $field->type) {
                throw ValidationException::withMessages(['type' => 'System field type cannot be changed.']);
            }
            $field->type = $type;
        }
        foreach (['is_required', 'show_in_table', 'is_filterable', 'is_key', 'calculate_totals', 'is_virtual'] as $boolKey) {
            if (array_key_exists($boolKey, $data)) {
                // Chrome system fields stay non-required / non-key; list visibility still updates.
                if (
                    $field->is_system_field
                    && in_array($field->name, ['actions', 'workflows', 'print', 'id'], true)
                    && in_array($boolKey, ['is_required', 'is_key', 'is_virtual'], true)
                ) {
                    continue;
                }
                $field->{$boolKey} = (bool) $data[$boolKey];
            }
        }
        foreach (['placeholder', 'system_column', 'formula_definition'] as $strKey) {
            if (array_key_exists($strKey, $data)) {
                $field->{$strKey} = $data[$strKey] !== null ? (string) $data[$strKey] : null;
            }
        }
        foreach (['options_json', 'conditional_rules_json', 'target_entity_id', 'form_group_id', 'view_group_id'] as $jsonKey) {
            if (array_key_exists($jsonKey, $data)) {
                $field->{$jsonKey} = $data[$jsonKey];
            }
        }
        if (isset($data['column_span'])) {
            $field->column_span = (int) $data['column_span'];
        }
        if (isset($data['field_order'])) {
            $field->field_order = (int) $data['field_order'];
        }
        $field->save();

        $field = $field->refresh();
        $entity = $field->entity;

        $this->activity->record(
            module: 'dynamic_entities',
            action: 'dyn_field.updated',
            summary: 'Field updated · '.($entity?->name ?? 'Entity').' / '.$field->label,
            entityType: 'dyn_field',
            entityId: (string) $field->id,
            entityLabel: (string) $field->label,
            actor: $actor,
            metadata: [
                'entity_slug' => $entity?->slug,
                'field_name' => (string) $field->name,
            ],
            changes: WorkspaceAuditChanges::diff($before, [
                'label' => $field->label,
                'type' => $field->type,
                'is_required' => $field->is_required,
                'show_in_table' => $field->show_in_table,
                'is_filterable' => $field->is_filterable,
                'is_key' => $field->is_key,
                'calculate_totals' => $field->calculate_totals,
                'is_virtual' => $field->is_virtual,
                'field_order' => $field->field_order,
                'column_span' => $field->column_span,
            ], array_keys($before)),
        );

        return $field;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createFieldGroup(DynEntity $entity, array $data, ?TenantUser $actor = null): DynFieldGroup
    {
        $group = DynFieldGroup::query()->create([
            'entity_id' => $entity->id,
            'name' => (string) $data['name'],
            'description' => isset($data['description']) ? (string) $data['description'] : null,
            'icon' => isset($data['icon']) ? (string) $data['icon'] : null,
            'applies_to_form' => (bool) ($data['applies_to_form'] ?? true),
            'applies_to_view' => (bool) ($data['applies_to_view'] ?? true),
            'start_collapsed' => (bool) ($data['start_collapsed'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        $this->activity->record(
            module: 'dynamic_entities',
            action: 'dyn_field_group.created',
            summary: 'Field group created · '.$entity->name.' / '.$group->name,
            entityType: 'dyn_field_group',
            entityId: (string) $group->id,
            entityLabel: (string) $group->name,
            actor: $actor,
            metadata: ['entity_slug' => (string) $entity->slug],
        );

        return $group;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateFieldGroup(DynFieldGroup $group, array $data, ?TenantUser $actor = null): DynFieldGroup
    {
        $beforeName = $group->name;

        if (isset($data['name'])) {
            $group->name = (string) $data['name'];
        }
        if (array_key_exists('description', $data)) {
            $group->description = $data['description'] !== null ? (string) $data['description'] : null;
        }
        if (array_key_exists('icon', $data)) {
            $group->icon = $data['icon'] !== null ? (string) $data['icon'] : null;
        }
        if (array_key_exists('applies_to_form', $data)) {
            $group->applies_to_form = (bool) $data['applies_to_form'];
        }
        if (array_key_exists('applies_to_view', $data)) {
            $group->applies_to_view = (bool) $data['applies_to_view'];
        }
        if (array_key_exists('start_collapsed', $data)) {
            $group->start_collapsed = (bool) $data['start_collapsed'];
        }
        if (isset($data['sort_order'])) {
            $group->sort_order = (int) $data['sort_order'];
        }
        $group->save();

        $group = $group->refresh();
        $entity = $group->entity;

        $this->activity->record(
            module: 'dynamic_entities',
            action: 'dyn_field_group.updated',
            summary: 'Field group updated · '.($entity?->name ?? 'Entity').' / '.$group->name,
            entityType: 'dyn_field_group',
            entityId: (string) $group->id,
            entityLabel: (string) $group->name,
            actor: $actor,
            metadata: ['entity_slug' => $entity?->slug],
            changes: WorkspaceAuditChanges::of([
                'name' => ['from' => $beforeName, 'to' => $group->name],
            ]),
        );

        return $group;
    }

    /**
     * Delete a field group. Fields keep their data but become ungrouped.
     */
    public function deleteFieldGroup(DynFieldGroup $group, ?TenantUser $actor = null): void
    {
        $entity = $group->entity;
        $label = (string) $group->name;
        $groupId = (string) $group->id;

        DynField::query()
            ->where('entity_id', $group->entity_id)
            ->where(function ($q) use ($group): void {
                $q->where('form_group_id', $group->id)->orWhere('view_group_id', $group->id);
            })
            ->update([
                'form_group_id' => null,
                'view_group_id' => null,
            ]);

        $group->delete();

        $this->activity->record(
            module: 'dynamic_entities',
            action: 'dyn_field_group.deleted',
            summary: 'Field group deleted · '.($entity?->name ?? 'Entity').' / '.$label,
            entityType: 'dyn_field_group',
            entityId: $groupId,
            entityLabel: $label,
            actor: $actor,
            metadata: ['entity_slug' => $entity?->slug],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function presentEntity(DynEntity $entity, bool $withSchema = false): array
    {
        $payload = [
            'id' => $entity->id,
            'slug' => $entity->slug,
            'name' => $entity->name,
            'description' => $entity->description,
            'module_pack' => $entity->module_pack,
            'storage_mode' => $entity->storage_mode,
            'is_location_based' => $entity->is_location_based,
            'source_linked_table' => $entity->source_linked_table,
            'related_tabs' => $entity->related_tabs_json ?? [],
            'print_settings' => AtcPrintTemplates::merge(
                is_array($entity->print_settings_json) ? $entity->print_settings_json : null,
                $entity->slug,
                $entity->name,
            ),
            'sort_order' => $entity->sort_order,
            'is_active' => $entity->is_active,
            'created_at' => $entity->created_at?->toIso8601String(),
            'updated_at' => $entity->updated_at?->toIso8601String(),
        ];

        if ($withSchema) {
            $entity->loadMissing(['fields', 'fieldGroups']);
            $payload['field_groups'] = $entity->fieldGroups->map(static fn (DynFieldGroup $g): array => [
                'id' => $g->id,
                'name' => $g->name,
                'description' => $g->description,
                'icon' => $g->icon,
                'applies_to_form' => $g->applies_to_form,
                'applies_to_view' => $g->applies_to_view,
                'start_collapsed' => $g->start_collapsed,
                'sort_order' => $g->sort_order,
            ])->values()->all();
            $payload['fields'] = $entity->fields->map(fn (DynField $f): array => $this->presentField($f))->values()->all();
            $payload['record_count'] = $entity->records()->where('is_deleted', false)->count();
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function presentField(DynField $field): array
    {
        $targetSlug = null;
        if ($field->target_entity_id) {
            $targetSlug = DynEntity::query()->whereKey($field->target_entity_id)->value('slug');
        }

        return [
            'id' => $field->id,
            'entity_id' => $field->entity_id,
            'name' => $field->name,
            'label' => $field->label,
            'type' => $field->type,
            'is_required' => $field->is_required,
            'is_system_field' => $field->is_system_field,
            'system_column' => $field->system_column,
            'show_in_table' => $field->show_in_table,
            'is_filterable' => $field->is_filterable,
            'is_key' => (bool) $field->is_key,
            'calculate_totals' => (bool) $field->calculate_totals,
            'options' => $field->options_json,
            'target_entity_id' => $field->target_entity_id,
            'target_entity_slug' => $targetSlug,
            'placeholder' => $field->placeholder,
            'formula_definition' => $field->formula_definition,
            'column_span' => $field->column_span,
            'field_order' => $field->field_order,
            'form_group_id' => $field->form_group_id,
            'view_group_id' => $field->view_group_id,
            'conditional_rules' => $field->conditional_rules_json,
            'is_virtual' => $field->is_virtual,
        ];
    }
}
