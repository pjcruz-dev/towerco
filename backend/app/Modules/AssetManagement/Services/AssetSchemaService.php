<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Services;

use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynField;
use App\Modules\DynamicEntities\Models\DynFieldGroup;
use App\Modules\DynamicEntities\Services\DynEntityAdminService;
use App\Modules\DynamicEntities\Services\ManagedSeriesService;
use App\Modules\DynamicEntities\Support\AssetPrintCatalog;
use App\Modules\DynamicEntities\Support\DynFieldType;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AssetSchemaService
{
    public const SLUG = 'it_asset';

    public function __construct(
        private readonly DynEntityAdminService $fields,
    ) {}

    public function ensure(): DynEntity
    {
        $entity = DynEntity::query()->where('slug', self::SLUG)->first();
        if ($entity instanceof DynEntity) {
            $this->ensureSystemFields($entity);
            $this->ensurePrintable($entity);
            app(ManagedSeriesService::class)->ensureAsset();

            return $entity;
        }

        $entity = DynEntity::query()->create([
            'id' => (string) Str::uuid(),
            'slug' => self::SLUG,
            'name' => 'IT asset',
            'description' => 'Physical IT assets. System fields map to the assets table. Extra fields are stored on the asset.',
            'module_pack' => 'system',
            'storage_mode' => 'linked',
            'is_location_based' => false,
            'source_linked_table' => 'it_assets',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $group = DynFieldGroup::query()->create([
            'id' => (string) Str::uuid(),
            'entity_id' => $entity->id,
            'name' => 'Identification',
            'applies_to_form' => true,
            'applies_to_view' => true,
            'sort_order' => 0,
        ]);

        $order = 10;
        foreach ($this->systemFields() as $field) {
            DynField::query()->create([
                'id' => (string) Str::uuid(),
                'entity_id' => $entity->id,
                'name' => $field['name'],
                'label' => $field['label'],
                'type' => $field['type'],
                'is_required' => $field['required'],
                'is_system_field' => true,
                'system_column' => $field['column'],
                'show_in_table' => $field['table'],
                'is_filterable' => $field['table'],
                'column_span' => $field['span'],
                'field_order' => $order,
                'form_group_id' => $group->id,
                'view_group_id' => $group->id,
            ]);
            $order += 10;
        }

        $entity = $entity->refresh();
        $this->ensurePrintable($entity);
        app(ManagedSeriesService::class)->ensureAsset();

        return $entity;
    }

    private function ensurePrintable(DynEntity $entity): void
    {
        AssetPrintCatalog::ensure($entity);
    }

    /**
     * @return array<string, mixed>
     */
    public function show(): array
    {
        $entity = $this->ensure()->load(['fields', 'fieldGroups']);

        return [
            'id' => $entity->id,
            'slug' => $entity->slug,
            'name' => $entity->name,
            'manage_fields_href' => '/dynamic-entities/fields?entity='.self::SLUG,
            'groups' => $entity->fieldGroups->map(fn (DynFieldGroup $group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'sort_order' => $group->sort_order,
                'surface' => $group->surface ?: 'overview',
            ])->values()->all(),
            'fields' => $entity->fields->map(fn (DynField $field): array => [
                'id' => $field->id,
                'name' => $field->name,
                'label' => $field->label,
                'type' => $field->type,
                'is_required' => $field->is_required,
                'is_system_field' => $field->is_system_field,
                'system_column' => $field->system_column,
                'show_in_table' => $field->show_in_table,
                'column_span' => $field->column_span,
                'field_order' => $field->field_order,
                'form_group_id' => $field->form_group_id,
                'options' => $field->options_json,
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createField(array $data, TenantUser $actor): DynField
    {
        $entity = $this->ensure();
        $data['is_system_field'] = false;
        $data['system_column'] = null;
        if (! isset($data['type'])) {
            $data['type'] = DynFieldType::TEXT;
        }
        $data = $this->assignDefaultGroup($data);

        return $this->fields->createField($entity, $data, $actor);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateField(DynField $field, array $data, TenantUser $actor): DynField
    {
        $this->assertOwned($field);
        unset($data['is_system_field'], $data['system_column'], $data['name']);
        if ($field->is_system_field) {
            unset($data['type']);
        }

        return $this->fields->updateField($field, $data, $actor);
    }

    public function deleteField(DynField $field): void
    {
        $this->assertOwned($field);
        if ($field->is_system_field) {
            throw ValidationException::withMessages([
                'field' => [__('System fields stay on the asset. You can rename or move them, not delete them.')],
            ]);
        }
        $field->delete();
    }

    /**
     * @return list<array{name: string, label: string, type: string, column: string, required: bool, table: bool, span: int}>
     */
    private function systemFields(): array
    {
        return [
            ['name' => 'asset_tag', 'label' => 'Asset tag', 'type' => DynFieldType::TEXT, 'column' => 'asset_tag', 'required' => true, 'table' => true, 'span' => 6],
            ['name' => 'serial_number', 'label' => 'Serial number', 'type' => DynFieldType::TEXT, 'column' => 'serial_number', 'required' => true, 'table' => true, 'span' => 6],
            ['name' => 'asset_type', 'label' => 'Type', 'type' => DynFieldType::SELECT, 'column' => 'asset_type', 'required' => true, 'table' => true, 'span' => 6],
            ['name' => 'status', 'label' => 'Status', 'type' => DynFieldType::SELECT, 'column' => 'status', 'required' => true, 'table' => true, 'span' => 6],
            ['name' => 'manufacturer', 'label' => 'Manufacturer', 'type' => DynFieldType::TEXT, 'column' => 'manufacturer', 'required' => false, 'table' => true, 'span' => 6],
            ['name' => 'product_name', 'label' => 'Product name', 'type' => DynFieldType::TEXT, 'column' => 'product_name', 'required' => false, 'table' => true, 'span' => 6],
            ['name' => 'product_version', 'label' => 'Product version', 'type' => DynFieldType::TEXT, 'column' => 'product_version', 'required' => false, 'table' => false, 'span' => 6],
            ['name' => 'hostname', 'label' => 'Computer name', 'type' => DynFieldType::TEXT, 'column' => 'hostname', 'required' => false, 'table' => true, 'span' => 6],
            ['name' => 'purchase_date', 'label' => 'Purchase date', 'type' => DynFieldType::DATE, 'column' => 'purchase_date', 'required' => false, 'table' => false, 'span' => 4],
            ['name' => 'warranty_end', 'label' => 'Warranty end', 'type' => DynFieldType::DATE, 'column' => 'warranty_end', 'required' => false, 'table' => false, 'span' => 4],
            ['name' => 'commission_date', 'label' => 'Commission date', 'type' => DynFieldType::DATE, 'column' => 'commission_date', 'required' => false, 'table' => false, 'span' => 4],
            ['name' => 'processor', 'label' => 'Processor', 'type' => DynFieldType::TEXT, 'column' => 'processor', 'required' => false, 'table' => false, 'span' => 6],
            ['name' => 'installed_ram', 'label' => 'Installed RAM', 'type' => DynFieldType::TEXT, 'column' => 'installed_ram', 'required' => false, 'table' => false, 'span' => 6],
            ['name' => 'operating_system', 'label' => 'Operating system', 'type' => DynFieldType::TEXT, 'column' => 'operating_system', 'required' => false, 'table' => false, 'span' => 6],
            ['name' => 'bios_version', 'label' => 'BIOS version', 'type' => DynFieldType::TEXT, 'column' => 'bios_version', 'required' => false, 'table' => false, 'span' => 6],
            ['name' => 'notes', 'label' => 'Notes', 'type' => DynFieldType::TEXTAREA, 'column' => 'notes', 'required' => false, 'table' => false, 'span' => 12],
        ];
    }

    private function ensureSystemFields(DynEntity $entity): void
    {
        $entity->load('fields');
        $present = $entity->fields->pluck('name')->all();
        $missing = array_values(array_filter(
            $this->systemFields(),
            fn (array $field): bool => ! in_array($field['name'], $present, true),
        ));
        if ($missing === []) {
            return;
        }

        $group = $this->formGroup($entity);
        $order = (int) $entity->fields->max('field_order') + 10;
        foreach ($missing as $field) {
            DynField::query()->create([
                'id' => (string) Str::uuid(),
                'entity_id' => $entity->id,
                'name' => $field['name'],
                'label' => $field['label'],
                'type' => $field['type'],
                'is_required' => $field['required'],
                'is_system_field' => true,
                'system_column' => $field['column'],
                'show_in_table' => $field['table'],
                'is_filterable' => $field['table'],
                'column_span' => $field['span'],
                'field_order' => $order,
                'form_group_id' => $group->id,
                'view_group_id' => $group->id,
            ]);
            $order += 10;
        }
    }

    /**
     * A field saved with no group belongs on Identification.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function assignDefaultGroup(array $data): array
    {
        if (! empty($data['form_group_id'])) {
            return $data;
        }
        $group = $this->formGroup($this->ensure());
        $data['form_group_id'] = $group->id;
        $data['view_group_id'] = $data['view_group_id'] ?? $group->id;

        return $data;
    }

    private function formGroup(DynEntity $entity): DynFieldGroup
    {
        $group = DynFieldGroup::query()
            ->where('entity_id', $entity->id)
            ->where('name', 'Identification')
            ->first();
        if ($group instanceof DynFieldGroup) {
            return $group;
        }

        $group = DynFieldGroup::query()
            ->where('entity_id', $entity->id)
            ->where('applies_to_form', true)
            ->orderBy('sort_order')
            ->first();
        if ($group instanceof DynFieldGroup) {
            return $group;
        }

        return DynFieldGroup::query()->create([
            'id' => (string) Str::uuid(),
            'entity_id' => $entity->id,
            'name' => 'Identification',
            'applies_to_form' => true,
            'applies_to_view' => true,
            'sort_order' => 0,
        ]);
    }

    private function assertOwned(DynField $field): void
    {
        $entity = $this->ensure();
        if ((string) $field->entity_id !== (string) $entity->id) {
            abort(404);
        }
    }
}
