<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Services;

use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynField;
use App\Modules\DynamicEntities\Models\DynFieldGroup;
use App\Modules\DynamicEntities\Services\DynEntityAdminService;
use App\Modules\DynamicEntities\Services\ManagedSeriesService;
use App\Modules\DynamicEntities\Support\DynFieldType;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Ticketing\Support\TicketingCategoryCatalog;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Helpdesk tickets are dynamic records (same shape as site tickets). The
 * ticketing_tickets row stays beside each record so SLA, resolve, reopen,
 * and the IT mailbox keep running.
 */
final class TicketingSchemaService
{
    public const SLUG = 'helpdesk_ticket';

    public function __construct(
        private readonly DynEntityAdminService $fields,
    ) {}

    public function ensure(): DynEntity
    {
        $entity = DynEntity::query()->where('slug', self::SLUG)->first();
        if (! $entity instanceof DynEntity) {
            $entity = DynEntity::query()->create([
                'id' => (string) Str::uuid(),
                'slug' => self::SLUG,
                'name' => 'IT Ticket',
                'description' => 'IT tickets. Add and rearrange fields here. Status, SLA, resolve, and reopen stay on the ticket.',
                'module_pack' => 'system',
                'storage_mode' => 'json',
                'is_location_based' => false,
                'source_linked_table' => null,
                'sort_order' => 0,
                'is_active' => true,
            ]);
        }

        $entity->name = 'IT Ticket';
        $entity->description = 'IT tickets. Add and rearrange fields here. Status, SLA, resolve, and reopen stay on the ticket.';
        $entity->storage_mode = 'json';
        $entity->source_linked_table = null;
        $entity->related_tabs_json = [[
            'entity_slug' => 'helpdesk_ticket_activities',
            'foreign_field' => 'helpdesk_ticket_id',
            'label' => 'Activity',
        ]];
        $entity->save();

        $this->ensureSystemFields($entity);
        $this->ensureListChrome($entity);
        $this->correctFieldTypes($entity);
        $this->arrangeView($entity);
        $activities = $this->ensureActivities();
        app(ManagedSeriesService::class)->ensureTicket();
        app(TicketingHelpdeskMigrator::class)->migrate($entity, $activities);

        return $entity->refresh();
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
            'manage_fields_href' => '/ticketing/fields',
            'groups' => $entity->fieldGroups->map(fn (DynFieldGroup $group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'sort_order' => $group->sort_order,
            ])->values()->all(),
            'fields' => $entity->fields->map(fn (DynField $field): array => $this->presentField($field))->values()->all(),
        ];
    }

    /**
     * @return list<string>
     */
    public function tableCustomFieldNames(): array
    {
        $entity = $this->ensure();

        return DynField::query()
            ->where('entity_id', $entity->id)
            ->where('is_system_field', false)
            ->where('show_in_table', true)
            ->orderBy('field_order')
            ->pluck('name')
            ->map(static fn ($name): string => (string) $name)
            ->all();
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
                'field' => [__('System fields stay on the ticket. You can rename or move them, not delete them.')],
            ]);
        }
        $field->delete();
    }

    /**
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

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function valuesForCreate(array $values): array
    {
        $fields = $this->customFields();
        $clean = $this->normalizeIncoming($values, $fields);
        foreach ($fields as $field) {
            if (! $field->is_required) {
                continue;
            }
            if (! array_key_exists($field->name, $clean) || $this->isEmpty($clean[$field->name])) {
                throw ValidationException::withMessages([
                    'custom_values.'.$field->name => [__(':label is required.', ['label' => $field->label])],
                ]);
            }
        }

        return array_filter(
            $clean,
            fn (mixed $value): bool => ! $this->isEmpty($value),
        );
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    public function mergeForUpdate(array $current, array $incoming): array
    {
        $fields = $this->customFields();
        $clean = $this->normalizeIncoming($incoming, $fields);
        $merged = $current;
        foreach ($clean as $name => $value) {
            $field = $fields[$name] ?? null;
            if ($field instanceof DynField && $field->is_required && $this->isEmpty($value)) {
                throw ValidationException::withMessages([
                    'custom_values.'.$name => [__(':label is required.', ['label' => $field->label])],
                ]);
            }
            if ($this->isEmpty($value)) {
                unset($merged[$name]);

                continue;
            }
            $merged[$name] = $value;
        }

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    public function presentField(DynField $field): array
    {
        return [
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
        ];
    }

    public static function stringify(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $item) {
                if (is_scalar($item)) {
                    $parts[] = (string) $item;
                }
            }

            return implode(', ', $parts);
        }

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @return array<string, DynField>
     */
    private function customFields(): array
    {
        $entity = $this->ensure();
        $rows = DynField::query()
            ->where('entity_id', $entity->id)
            ->where('is_system_field', false)
            ->get();
        $indexed = [];
        foreach ($rows as $field) {
            $indexed[(string) $field->name] = $field;
        }

        return $indexed;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, DynField>  $fields
     * @return array<string, mixed>
     */
    private function normalizeIncoming(array $values, array $fields): array
    {
        if (count($values) > 50) {
            throw ValidationException::withMessages([
                'custom_values' => [__('Too many custom fields.')],
            ]);
        }

        $clean = [];
        foreach ($values as $name => $value) {
            if (! is_string($name) || ! array_key_exists($name, $fields)) {
                throw ValidationException::withMessages([
                    'custom_values' => [__('Unknown ticket field.')],
                ]);
            }
            $clean[$name] = $this->normalizeValue($fields[$name], $value);
        }

        return $clean;
    }

    private function normalizeValue(DynField $field, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value)) {
            if (! array_is_list($value) || count($value) > 20) {
                throw ValidationException::withMessages([
                    'custom_values.'.$field->name => [__('That value is not valid for :label.', ['label' => $field->label])],
                ]);
            }
            $items = [];
            foreach ($value as $item) {
                if (! is_scalar($item) || mb_strlen((string) $item) > 500) {
                    throw ValidationException::withMessages([
                        'custom_values.'.$field->name => [__('That value is not valid for :label.', ['label' => $field->label])],
                    ]);
                }
                $items[] = (string) $item;
            }

            return $items;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if (! is_string($value) || mb_strlen($value) > 8000) {
            throw ValidationException::withMessages([
                'custom_values.'.$field->name => [__('That value is not valid for :label.', ['label' => $field->label])],
            ]);
        }

        return $value;
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /**
     * @return list<array{name: string, label: string, type: string, column: string, required: bool, table: bool, span: int, options?: mixed}>
     */
    private function systemFields(): array
    {
        return [
            ['name' => 'ticket_number', 'label' => 'Ticket', 'type' => DynFieldType::TEXT, 'column' => 'ticket_number', 'required' => false, 'table' => true, 'span' => 4],
            ['name' => 'title', 'label' => 'Title', 'type' => DynFieldType::TEXT, 'column' => 'title', 'required' => true, 'table' => true, 'span' => 12],
            ['name' => 'description', 'label' => 'Description', 'type' => DynFieldType::TEXTAREA, 'column' => 'description', 'required' => false, 'table' => false, 'span' => 12],
            ['name' => 'status', 'label' => 'Status', 'type' => DynFieldType::SELECT, 'column' => 'status', 'required' => true, 'table' => true, 'span' => 6, 'options' => [
                ['value' => 'open', 'label' => 'Open', 'badge' => 'info'],
                ['value' => 'in_progress', 'label' => 'In progress', 'badge' => 'warning'],
                ['value' => 'resolved', 'label' => 'Resolved', 'badge' => 'success'],
                ['value' => 'closed', 'label' => 'Closed', 'badge' => 'none'],
            ]],
            ['name' => 'priority', 'label' => 'Priority', 'type' => DynFieldType::SELECT, 'column' => 'priority', 'required' => true, 'table' => true, 'span' => 6, 'options' => [
                ['value' => 'low', 'label' => 'Low', 'badge' => 'none'],
                ['value' => 'normal', 'label' => 'Normal', 'badge' => 'info'],
                ['value' => 'high', 'label' => 'High', 'badge' => 'warning'],
                ['value' => 'urgent', 'label' => 'Urgent', 'badge' => 'danger'],
            ]],
            ['name' => 'category', 'label' => 'Category', 'type' => DynFieldType::SELECT, 'column' => 'category', 'required' => false, 'table' => true, 'span' => 6],
            ['name' => 'requester', 'label' => 'Requester', 'type' => DynFieldType::RELATIONSHIP, 'column' => 'requester', 'required' => false, 'table' => true, 'span' => 6],
            ['name' => 'assigned_to', 'label' => 'Assigned to', 'type' => DynFieldType::RELATIONSHIP, 'column' => 'assigned_to', 'required' => false, 'table' => true, 'span' => 6],
            ['name' => 'sla_status', 'label' => 'SLA', 'type' => DynFieldType::SELECT, 'column' => 'sla_status', 'required' => false, 'table' => true, 'span' => 4, 'options' => [
                ['value' => 'on_track', 'label' => 'On track', 'badge' => 'success'],
                ['value' => 'at_risk', 'label' => 'At risk', 'badge' => 'warning'],
                ['value' => 'breached', 'label' => 'Breached', 'badge' => 'danger'],
            ]],
            ['name' => 'sla_due_at', 'label' => 'SLA due', 'type' => DynFieldType::DATETIME, 'column' => 'sla_due_at', 'required' => false, 'table' => false, 'span' => 6],
            ['name' => 'resolved_at', 'label' => 'Resolved at', 'type' => DynFieldType::DATETIME, 'column' => 'resolved_at', 'required' => false, 'table' => false, 'span' => 6],
            ['name' => 'source_label', 'label' => 'Source', 'type' => DynFieldType::TEXT, 'column' => 'source_label', 'required' => false, 'table' => false, 'span' => 6],
            ['name' => 'resolution_notes', 'label' => 'Resolution notes', 'type' => DynFieldType::TEXTAREA, 'column' => 'resolution_notes', 'required' => false, 'table' => false, 'span' => 12],
            ['name' => 'attachment_names', 'label' => 'Attachments', 'type' => DynFieldType::FILE, 'column' => 'attachment_names', 'required' => false, 'table' => false, 'span' => 12],
        ];
    }

    /**
     * @param  array{name: string, label: string, type: string, column: string, required: bool, table: bool, span: int, options?: list<string>}  $field
     */
    private function insertSystemField(DynEntity $entity, DynFieldGroup $group, array $field, int $order): void
    {
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
            'options_json' => $field['options'] ?? null,
        ]);
    }

    /**
     * Add the list columns without re-running ticket migration.
     */
    public function ensureListColumns(): void
    {
        $entity = DynEntity::query()->where('slug', self::SLUG)->first();
        if (! $entity instanceof DynEntity) {
            $this->ensure();

            return;
        }
        $this->ensureListChrome($entity);
        $entity->name = 'IT Ticket';
        $entity->description = 'IT tickets. Add and rearrange fields here. Status, SLA, resolve, and reopen stay on the ticket.';
        $entity->save();
    }

    /**
     * ID, Actions, and Print are list columns. They stay off the ticket form.
     */
    private function ensureListChrome(DynEntity $entity): void
    {
        $defs = [
            ['name' => 'id', 'label' => 'ID', 'order' => 1],
            ['name' => 'actions', 'label' => 'Actions', 'order' => 2],
            ['name' => 'print', 'label' => 'Print', 'order' => 3],
        ];
        foreach ($defs as $def) {
            $exists = DynField::query()
                ->where('entity_id', $entity->id)
                ->where('name', $def['name'])
                ->exists();
            if ($exists) {
                continue;
            }
            DynField::query()->create([
                'id' => (string) Str::uuid(),
                'entity_id' => $entity->id,
                'name' => $def['name'],
                'label' => $def['label'],
                'type' => DynFieldType::TEXT,
                'is_required' => false,
                'is_system_field' => true,
                'system_column' => null,
                'show_in_table' => true,
                'is_filterable' => false,
                'column_span' => 6,
                'field_order' => $def['order'],
                'form_group_id' => null,
                'view_group_id' => null,
            ]);
        }
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
            $this->insertSystemField($entity, $group, $field, $order);
            $order += 10;
        }

        foreach ($this->systemFields() as $field) {
            if (! isset($field['options'])) {
                continue;
            }
            $row = DynField::query()
                ->where('entity_id', $entity->id)
                ->where('name', $field['name'])
                ->first();
            if ($row instanceof DynField) {
                $row->options_json = $field['options'];
                $row->type = $field['type'];
                $row->save();
            }
        }

        DynField::query()
            ->where('entity_id', $entity->id)
            ->where('name', 'ticket_number')
            ->update(['is_required' => false]);
    }

    /**
     * Refresh built-in select choices. A type saved in Manage Fields is left as saved.
     */
    private function correctFieldTypes(DynEntity $entity): void
    {
        $categoryOptions = array_map(
            static fn (array $option): array => [
                'value' => $option['id'],
                'label' => $option['label'],
            ],
            app(TicketingCategoryCatalog::class)->resolveOptions(),
        );

        $this->setFieldType($entity, 'category', DynFieldType::SELECT, $categoryOptions);
        $this->setFieldType($entity, 'sla_status', DynFieldType::SELECT, [
            ['value' => 'on_track', 'label' => 'On track', 'badge' => 'success'],
            ['value' => 'at_risk', 'label' => 'At risk', 'badge' => 'warning'],
            ['value' => 'breached', 'label' => 'Breached', 'badge' => 'danger'],
        ]);
        $this->setFieldType($entity, 'attachment_names', DynFieldType::FILE);
        $this->linkPeopleFields($entity);
    }

    private function linkPeopleFields(DynEntity $entity): void
    {
        $usersId = (string) $this->ensureWorkspaceUsers()->id;
        foreach (['requester', 'assigned_to'] as $name) {
            $row = DynField::query()
                ->where('entity_id', $entity->id)
                ->where('name', $name)
                ->first();
            if (! $row instanceof DynField) {
                continue;
            }
            if ((string) $row->type !== DynFieldType::RELATIONSHIP) {
                continue;
            }
            if ($row->target_entity_id === null || $row->target_entity_id === '') {
                $row->target_entity_id = $usersId;
                $row->save();
            }
        }
    }

    /** Directory the relationship picker searches. Rows are tenant users, not stored records. */
    private function ensureWorkspaceUsers(): DynEntity
    {
        $entity = DynEntity::query()->where('slug', 'workspace_users')->first();
        if (! $entity instanceof DynEntity) {
            $entity = DynEntity::query()->create([
                'id' => (string) Str::uuid(),
                'slug' => 'workspace_users',
                'name' => 'Users',
                'description' => 'Active workspace users. Requester and Assigned to link here.',
                'module_pack' => 'system',
                'storage_mode' => 'json',
                'is_location_based' => false,
                'sort_order' => 50,
                'is_active' => true,
            ]);
        }

        foreach ([
            ['name' => 'name', 'label' => 'Name', 'order' => 10],
            ['name' => 'email', 'label' => 'Email', 'order' => 20],
        ] as $field) {
            DynField::query()->updateOrCreate(
                ['entity_id' => $entity->id, 'name' => $field['name']],
                [
                    'label' => $field['label'],
                    'type' => DynFieldType::TEXT,
                    'is_required' => false,
                    'is_system_field' => true,
                    'show_in_table' => true,
                    'is_filterable' => true,
                    'is_key' => $field['name'] === 'name',
                    'column_span' => 6,
                    'field_order' => $field['order'],
                ],
            );
        }

        return $entity;
    }

    /**
     * @param  list<array<string, mixed>>|null  $options
     */
    private function setFieldType(DynEntity $entity, string $name, string $type, ?array $options = null): void
    {
        $row = DynField::query()
            ->where('entity_id', $entity->id)
            ->where('name', $name)
            ->first();
        if (! $row instanceof DynField) {
            return;
        }

        if ((string) $row->type !== $type) {
            return;
        }
        if ($options !== null) {
            $existing = is_array($row->options_json) ? $row->options_json : [];
            $default = $existing['default'] ?? $existing['default_value'] ?? null;
            $row->options_json = is_string($default) && $default !== ''
                ? ['choices' => $options, 'default' => $default]
                : $options;
        }
        $row->save();
    }

    private function ensureActivities(): DynEntity
    {
        $entity = DynEntity::query()->where('slug', 'helpdesk_ticket_activities')->first();
        if (! $entity instanceof DynEntity) {
            $entity = DynEntity::query()->create([
                'id' => (string) Str::uuid(),
                'slug' => 'helpdesk_ticket_activities',
                'name' => 'Ticket activity',
                'description' => 'Comments and internal notes on an IT ticket.',
                'module_pack' => 'system',
                'storage_mode' => 'json',
                'is_location_based' => false,
                'sort_order' => 1,
                'is_active' => true,
            ]);
        }

        $ticketId = DynEntity::query()->where('slug', self::SLUG)->value('id');
        $defs = [
            ['name' => 'helpdesk_ticket_id', 'label' => 'Ticket', 'type' => DynFieldType::RELATIONSHIP, 'required' => true, 'table' => true, 'order' => 10],
            ['name' => 'activity_type', 'label' => 'Type', 'type' => DynFieldType::SELECT, 'required' => true, 'table' => true, 'order' => 20, 'options' => ['Comment', 'Internal']],
            ['name' => 'notes', 'label' => 'Notes', 'type' => DynFieldType::TEXTAREA, 'required' => true, 'table' => true, 'order' => 30],
            ['name' => 'activity_at', 'label' => 'When', 'type' => DynFieldType::DATETIME, 'required' => false, 'table' => true, 'order' => 40],
            ['name' => 'performed_by', 'label' => 'By', 'type' => DynFieldType::TEXT, 'required' => false, 'table' => true, 'order' => 50],
        ];
        foreach ($defs as $def) {
            DynField::query()->updateOrCreate(
                ['entity_id' => $entity->id, 'name' => $def['name']],
                [
                    'label' => $def['label'],
                    'type' => $def['type'],
                    'is_required' => $def['required'],
                    'is_system_field' => true,
                    'show_in_table' => $def['table'],
                    'is_filterable' => $def['table'],
                    'column_span' => $def['type'] === DynFieldType::TEXTAREA ? 12 : 6,
                    'field_order' => $def['order'],
                    'options_json' => $def['options'] ?? null,
                    'target_entity_id' => $def['type'] === DynFieldType::RELATIONSHIP ? $ticketId : null,
                ],
            );
        }

        return $entity;
    }

    /**
     * First placement of the built-in fields: request, controls, facts, then files.
     * A field that already has a form group keeps the span and group from Adjust widths.
     */
    private function arrangeView(DynEntity $entity): void
    {
        $specs = [
            'Request' => [
                'sort' => 10,
                'fields' => [
                    'title' => [10, 12],
                    'description' => [20, 12],
                ],
            ],
            'Manage' => [
                'sort' => 20,
                'fields' => [
                    'status' => [10, 3],
                    'priority' => [20, 3],
                    'category' => [30, 3],
                    'assigned_to' => [40, 3],
                    'resolution_notes' => [50, 12],
                ],
            ],
            'Details' => [
                'sort' => 30,
                'fields' => [
                    'ticket_number' => [10, 4],
                    'source_label' => [20, 4],
                    'requester' => [30, 4],
                    'sla_status' => [40, 4],
                    'sla_due_at' => [50, 4],
                    'resolved_at' => [60, 4],
                ],
            ],
            'Attachments' => [
                'sort' => 40,
                'fields' => [
                    'attachment_names' => [10, 12],
                ],
            ],
        ];

        foreach ($specs as $name => $spec) {
            $group = DynFieldGroup::query()->updateOrCreate(
                ['entity_id' => $entity->id, 'name' => $name],
                [
                    'applies_to_form' => true,
                    'applies_to_view' => true,
                    'start_collapsed' => false,
                    'sort_order' => $spec['sort'],
                ],
            );
            foreach ($spec['fields'] as $fieldName => [$order, $span]) {
                $field = DynField::query()
                    ->where('entity_id', $entity->id)
                    ->where('name', $fieldName)
                    ->first();
                if (! $field instanceof DynField) {
                    continue;
                }
                // A placed field keeps the span and group chosen in Adjust widths.
                if ($field->form_group_id !== null && $field->view_group_id !== null) {
                    continue;
                }
                $field->form_group_id = $group->id;
                $field->view_group_id = $group->id;
                $field->field_order = $order;
                $field->column_span = $span;
                $field->save();
            }
        }

        $legacy = DynFieldGroup::query()
            ->where('entity_id', $entity->id)
            ->where('name', 'Ticket')
            ->first();
        if ($legacy instanceof DynFieldGroup) {
            $stillUsed = DynField::query()
                ->where('entity_id', $entity->id)
                ->where(function ($query) use ($legacy): void {
                    $query->where('form_group_id', $legacy->id)
                        ->orWhere('view_group_id', $legacy->id);
                })
                ->exists();
            if (! $stillUsed) {
                $legacy->delete();
            }
        }
    }

    private function formGroup(DynEntity $entity): DynFieldGroup
    {
        $group = DynFieldGroup::query()
            ->where('entity_id', $entity->id)
            ->where('name', 'Request')
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
            'name' => 'Ticket',
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
