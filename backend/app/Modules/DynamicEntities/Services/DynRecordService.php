<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynField;
use App\Modules\DynamicEntities\Models\DynRecord;
use App\Modules\DynamicEntities\Models\DynRecordIndex;
use App\Modules\DynamicEntities\Support\DynEntityWorkflowActions;
use App\Modules\DynamicEntities\Support\DynFieldType;
use App\Modules\DynamicEntities\Support\DynRoleDataFilterApplier;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Workspace\Services\TenantActivityLogger;
use App\Modules\Workspace\Support\WorkspaceAuditChanges;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DynRecordService
{
    public function __construct(
        private readonly DynEntityAdminService $entityAdmin,
        private readonly TenantActivityLogger $activity,
        private readonly DynAutomaticIdService $automaticIds,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, DynRecord>
     */
    public function paginate(DynEntity $entity, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = $this->filteredRecordsQuery($entity, $filters);

        $sort = (string) ($filters['sort'] ?? '-updated_at');
        $desc = str_starts_with($sort, '-');
        $sortCol = ltrim($sort, '-');

        if (in_array($sortCol, ['updated_at', 'created_at', 'title', 'status'], true)) {
            $query->orderBy($sortCol, $desc ? 'desc' : 'asc');
        } elseif (preg_match('/^[a-z][a-z0-9_]{0,63}$/', $sortCol) === 1) {
            // Sort by indexed dyn field value when present.
            $direction = $desc ? 'desc' : 'asc';
            $query->orderByRaw(
                '(select coalesce(dyn_record_indexes.value_string, cast(dyn_record_indexes.value_number as char), dyn_record_indexes.value_date)
                  from dyn_record_indexes
                  where dyn_record_indexes.record_id = dyn_records.id
                    and dyn_record_indexes.entity_id = ?
                    and dyn_record_indexes.field_name = ?
                  limit 1) '.$direction,
                [$entity->id, $sortCol]
            );
        } else {
            $query->orderBy('updated_at', 'desc');
        }

        return $query->paginate(max(1, min(100, $perPage)));
    }

    /**
     * Sum calculate_totals fields over the same filtered set as the list (not just the current page).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, float>
     */
    public function columnTotals(DynEntity $entity, array $filters = []): array
    {
        $entity->loadMissing('fields');
        $fields = $entity->fields
            ->filter(static fn (DynField $f): bool => (bool) $f->calculate_totals && ! (bool) $f->is_virtual)
            ->values();

        if ($fields->isEmpty()) {
            return [];
        }

        $matchingIds = $this->filteredRecordsQuery($entity, $filters)->select('dyn_records.id');
        $totals = [];

        foreach ($fields as $field) {
            $name = (string) $field->name;
            $sum = DynRecordIndex::query()
                ->where('entity_id', $entity->id)
                ->where('field_name', $name)
                ->whereIn('record_id', (clone $matchingIds))
                ->sum('value_number');

            $totals[$name] = round((float) $sum, 6);
        }

        return $totals;
    }

    /**
     * Shared list filters (search / status / column filters / role / view-own) without sort or pagination.
     *
     * @param  array<string, mixed>  $filters
     * @return \Illuminate\Database\Eloquent\Builder<DynRecord>
     */
    private function filteredRecordsQuery(DynEntity $entity, array $filters = [])
    {
        $query = DynRecord::query()
            ->where('dyn_records.entity_id', $entity->id)
            ->where('dyn_records.is_deleted', false);

        if (! empty($filters['parent_record_id'])) {
            $parentId = (string) $filters['parent_record_id'];
            $foreignField = isset($filters['foreign_field']) ? trim((string) $filters['foreign_field']) : '';

            if ($foreignField !== '') {
                $query->where(function ($q) use ($entity, $parentId, $foreignField): void {
                    $q->where('parent_record_id', $parentId)
                        ->orWhereExists(function ($sub) use ($entity, $parentId, $foreignField): void {
                            $sub->selectRaw('1')
                                ->from('dyn_record_indexes')
                                ->whereColumn('dyn_record_indexes.record_id', 'dyn_records.id')
                                ->where('dyn_record_indexes.entity_id', $entity->id)
                                ->where('dyn_record_indexes.field_name', $foreignField)
                                ->where('dyn_record_indexes.value_string', $parentId);
                        });
                });
            } else {
                $query->where('parent_record_id', $parentId);
            }
        }

        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', '%'.$search.'%')
                    ->orWhere('source_external_id', 'like', '%'.$search.'%');
            });
        }

        if (! empty($filters['filter']) && is_array($filters['filter'])) {
            foreach ($filters['filter'] as $fieldName => $value) {
                if (is_array($value)) {
                    continue; // Advanced nested ops handled via advanced_filter_rules
                }
                if ($value === null || $value === '') {
                    continue;
                }
                $fieldName = (string) $fieldName;
                $query->whereExists(function ($sub) use ($entity, $fieldName, $value): void {
                    $sub->selectRaw('1')
                        ->from('dyn_record_indexes')
                        ->whereColumn('dyn_record_indexes.record_id', 'dyn_records.id')
                        ->where('dyn_record_indexes.entity_id', $entity->id)
                        ->where('dyn_record_indexes.field_name', $fieldName)
                        ->where('dyn_record_indexes.value_string', 'like', '%'.(string) $value.'%');
                });
            }
        }

        if (! empty($filters['advanced_filter_rules']) && is_array($filters['advanced_filter_rules'])) {
            /** @var list<array{field: string, operator: string, value?: string}> $rules */
            $rules = array_values(array_filter(
                $filters['advanced_filter_rules'],
                static fn ($rule): bool => is_array($rule) && ($rule['field'] ?? '') !== '' && ($rule['operator'] ?? '') !== '',
            ));
            if ($rules !== []) {
                DynRoleDataFilterApplier::apply($query, (string) $entity->id, [
                    ['logic' => 'and', 'rules' => $rules],
                ]);
            }
        }

        if (! empty($filters['role_data_filter_groups']) && is_array($filters['role_data_filter_groups'])) {
            DynRoleDataFilterApplier::apply($query, (string) $entity->id, $filters['role_data_filter_groups']);
        }

        if (! empty($filters['view_own_user_id'])) {
            $uid = (string) $filters['view_own_user_id'];
            $query->where(function ($q) use ($uid): void {
                $q->where('created_by', $uid)->orWhere('assigned_user_id', $uid);
            });
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(DynEntity $entity, array $data, TenantUser $actor, bool $audit = true): DynRecord
    {
        $record = DB::transaction(function () use ($entity, $data, $actor): DynRecord {
            $values = $this->normalizeValues($entity, $data['values'] ?? []);
            $values = $this->automaticIds->assignOnCreate($entity, $values);
            $this->assertRequired($entity, $values);
            $this->assertUniqueFieldValues($entity, $values, null);

            $record = DynRecord::query()->create([
                'entity_id' => $entity->id,
                'status' => isset($data['status']) ? (string) $data['status'] : ($values['status'] ?? null),
                'title' => $this->resolveTitle($entity, $values, $data['title'] ?? null),
                'values_json' => $values,
                'parent_record_id' => $data['parent_record_id'] ?? null,
                'location_id' => $data['location_id'] ?? null,
                'assigned_user_id' => $data['assigned_user_id'] ?? null,
                'source_external_id' => isset($data['source_external_id']) ? (string) $data['source_external_id'] : null,
                'is_deleted' => false,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->rebuildIndexes($entity, $record);

            return $record;
        });

        if ($audit) {
            $this->logRecordActivity(
                action: 'dyn_record.created',
                entity: $entity,
                record: $record,
                actor: $actor,
                summary: 'Record created · '.$this->recordLabel($entity, $record),
                changes: WorkspaceAuditChanges::of([
                    'status' => ['from' => null, 'to' => $record->status],
                    'title' => ['from' => null, 'to' => $record->title],
                ]),
            );
        }

        try {
            app(DynWorkflowAutoRunner::class)->afterCreate($entity, $record, $actor);
        } catch (\Throwable) {
            // Auto workflows must not block create.
        }

        return $record;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(DynRecord $record, array $data, TenantUser $actor, bool $audit = true): DynRecord
    {
        $entity = $record->entity;
        abort_unless($entity instanceof DynEntity, 404);

        $beforeTitle = $record->title;
        $beforeStatus = $record->status;
        $beforeValues = $record->values_json ?? [];

        $updated = DB::transaction(function () use ($record, $entity, $data, $actor): DynRecord {
            $existing = $record->values_json ?? [];
            $incoming = [];
            if (isset($data['values']) && is_array($data['values'])) {
                $incoming = $this->automaticIds->stripFromUpdate(
                    $entity,
                    $this->normalizeValues($entity, $data['values']),
                );
            }
            $values = array_merge($existing, $incoming);
            // On update, only enforce required for fields included in this patch
            // (avoids 422 when new schema fields are empty on older records).
            $this->assertRequired($entity, $values, array_keys($incoming));
            $this->assertUniqueFieldValues($entity, $values, $record->id, array_keys($incoming));

            if (array_key_exists('status', $data)) {
                $record->status = $data['status'] !== null ? (string) $data['status'] : null;
            } elseif (isset($values['status'])) {
                $record->status = (string) $values['status'];
            }

            if (array_key_exists('title', $data) && $data['title'] !== null) {
                $record->title = (string) $data['title'];
            } else {
                $record->title = $this->resolveTitle($entity, $values, $record->title);
            }

            if (array_key_exists('parent_record_id', $data)) {
                $record->parent_record_id = $data['parent_record_id'];
            }
            if (array_key_exists('assigned_user_id', $data)) {
                $record->assigned_user_id = $data['assigned_user_id'];
            }

            $record->values_json = $values;
            $record->updated_by = $actor->id;
            $record->save();

            $this->rebuildIndexes($entity, $record);

            return $record->refresh();
        });

        if ($audit) {
            $afterValues = $updated->values_json ?? [];
            $valueKeys = array_values(array_unique(array_merge(array_keys($beforeValues), array_keys($afterValues))));
            $valueChanges = [];
            foreach (array_slice($valueKeys, 0, 40) as $key) {
                $from = $beforeValues[$key] ?? null;
                $to = $afterValues[$key] ?? null;
                if ($from === $to) {
                    continue;
                }
                $valueChanges[$key] = ['from' => $from, 'to' => $to];
            }

            $this->logRecordActivity(
                action: 'dyn_record.updated',
                entity: $entity,
                record: $updated,
                actor: $actor,
                summary: 'Record updated · '.$this->recordLabel($entity, $updated),
                changes: WorkspaceAuditChanges::of(array_merge([
                    'status' => ['from' => $beforeStatus, 'to' => $updated->status],
                    'title' => ['from' => $beforeTitle, 'to' => $updated->title],
                ], $valueChanges)),
            );
        }

        try {
            app(DynWorkflowAutoRunner::class)->afterUpdate($entity, $updated, $actor, is_string($beforeStatus) ? $beforeStatus : null);
        } catch (\Throwable) {
            // Auto workflows must not block update.
        }

        return $updated;
    }

    public function softDelete(DynRecord $record, TenantUser $actor, bool $audit = true): void
    {
        $entity = $record->relationLoaded('entity') ? $record->entity : $record->entity()->first();
        $record->is_deleted = true;
        $record->deleted_at = now();
        $record->updated_by = $actor->id;
        $record->save();

        if ($audit && $entity instanceof DynEntity) {
            $this->logRecordActivity(
                action: 'dyn_record.deleted',
                entity: $entity,
                record: $record,
                actor: $actor,
                summary: 'Record deleted · '.$this->recordLabel($entity, $record),
                changes: WorkspaceAuditChanges::of([
                    'is_deleted' => ['from' => false, 'to' => true],
                ]),
            );
        }
    }

    public function rebuildIndexes(DynEntity $entity, DynRecord $record): void
    {
        DynRecordIndex::query()->where('record_id', $record->id)->delete();

        $fields = $entity->fields()
            ->where(function ($q): void {
                $q->where('is_filterable', true)
                    ->orWhere('show_in_table', true)
                    ->orWhere('calculate_totals', true);
            })
            ->get();
        $values = $record->values_json ?? [];

        foreach ($fields as $field) {
            if (! array_key_exists($field->name, $values)) {
                continue;
            }
            $raw = $values[$field->name];
            if ($raw === null || $raw === '') {
                continue;
            }

            $row = [
                'record_id' => $record->id,
                'entity_id' => $entity->id,
                'field_name' => $field->name,
                'value_string' => null,
                'value_number' => null,
                'value_date' => null,
            ];

            if (in_array($field->type, [DynFieldType::NUMBER, DynFieldType::DECIMAL], true) && is_numeric($raw)) {
                $row['value_number'] = $raw;
                $row['value_string'] = (string) $raw;
            } elseif (in_array($field->type, [DynFieldType::DATE, DynFieldType::DATETIME], true)) {
                $row['value_date'] = substr((string) $raw, 0, 10);
                $row['value_string'] = (string) $raw;
            } elseif (is_array($raw)) {
                $row['value_string'] = substr(implode(', ', array_map('strval', $raw)), 0, 512);
            } else {
                $row['value_string'] = substr((string) $raw, 0, 512);
            }

            DynRecordIndex::query()->create($row);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function presentListRow(DynEntity $entity, DynRecord $record): array
    {
        $entity->loadMissing('fields');
        $values = $record->values_json ?? [];
        $columns = [];
        foreach ($entity->fields as $field) {
            if ($field->is_system_field || in_array($field->name, ['actions', 'workflows', 'print', 'id', 'status'], true)) {
                continue;
            }
            $columns[$field->name] = $values[$field->name] ?? null;
        }

        return [
            'id' => $record->id,
            'entity_id' => $record->entity_id,
            'entity_slug' => $entity->slug,
            'status' => $record->status,
            'title' => $record->title,
            'parent_record_id' => $record->parent_record_id,
            'source_external_id' => $record->source_external_id,
            'columns' => $columns,
            'updated_at' => $record->updated_at?->toIso8601String(),
            'created_at' => $record->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentDetail(DynEntity $entity, DynRecord $record): array
    {
        $values = $record->values_json ?? [];
        $actorIds = array_values(array_filter([
            $record->created_by,
            $record->updated_by,
        ]));
        $actors = $actorIds === []
            ? collect()
            : TenantUser::query()->whereIn('id', $actorIds)->get(['id', 'name', 'email'])->keyBy('id');

        $createdBy = $record->created_by ? $actors->get($record->created_by) : null;
        $updatedBy = $record->updated_by ? $actors->get($record->updated_by) : null;

        return [
            'id' => $record->id,
            'entity' => $this->entityAdmin->presentEntity($entity, true),
            'status' => $record->status,
            'title' => $record->title,
            'values' => $values,
            'resolved_relations' => $this->resolveRelations($entity, $values),
            'workflow_actions' => DynEntityWorkflowActions::availableForRecord(
                $entity,
                $record->status,
                is_array($values) ? $values : [],
            ),
            'parent_record_id' => $record->parent_record_id,
            'location_id' => $record->location_id,
            'assigned_user_id' => $record->assigned_user_id,
            'source_external_id' => $record->source_external_id,
            'created_by' => $record->created_by,
            'created_by_name' => $createdBy?->name ?? $createdBy?->email,
            'updated_by' => $record->updated_by,
            'updated_by_name' => $updatedBy?->name ?? $updatedBy?->email,
            'created_at' => $record->created_at?->toIso8601String(),
            'updated_at' => $record->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, array{id: string, title: string|null, entity_slug: string|null}>
     */
    private function resolveRelations(DynEntity $entity, array $values): array
    {
        $entity->loadMissing('fields');
        /** @var array<string, list<array{field: string, id: string}>> $byTarget */
        $byTarget = [];
        foreach ($entity->fields as $field) {
            if ($field->type !== DynFieldType::RELATIONSHIP || ! $field->target_entity_id) {
                continue;
            }
            $raw = $values[$field->name] ?? null;
            if (! is_string($raw) || trim($raw) === '') {
                continue;
            }
            $byTarget[$field->target_entity_id][] = ['field' => $field->name, 'id' => trim($raw)];
        }

        if ($byTarget === []) {
            return [];
        }

        $targetEntities = DynEntity::query()
            ->whereIn('id', array_keys($byTarget))
            ->get()
            ->keyBy('id');

        $allIds = [];
        foreach ($byTarget as $pairs) {
            foreach ($pairs as $pair) {
                $allIds[] = $pair['id'];
            }
        }
        $allIds = array_values(array_unique($allIds));
        $records = DynRecord::query()
            ->whereIn('id', $allIds)
            ->where('is_deleted', false)
            ->get(['id', 'entity_id', 'title'])
            ->keyBy('id');

        $out = [];
        foreach ($byTarget as $targetEntityId => $pairs) {
            $target = $targetEntities->get($targetEntityId);
            foreach ($pairs as $pair) {
                $related = $records->get($pair['id']);
                $out[$pair['field']] = [
                    'id' => $pair['id'],
                    'title' => $related?->title,
                    'entity_slug' => $target?->slug,
                ];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalizeValues(DynEntity $entity, array $input): array
    {
        $entity->loadMissing('fields');
        $allowed = $entity->fields->pluck('name')->all();
        $out = [];
        foreach ($input as $key => $value) {
            $key = (string) $key;
            if (! in_array($key, $allowed, true)) {
                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>|null  $onlyFieldNames  When set (record updates), only these fields are required-checked.
     */
    private function assertRequired(DynEntity $entity, array $values, ?array $onlyFieldNames = null): void
    {
        $entity->loadMissing('fields');
        $errors = [];
        $only = $onlyFieldNames === null ? null : array_fill_keys($onlyFieldNames, true);

        foreach ($entity->fields as $field) {
            if (! $field->is_required || $field->is_virtual) {
                continue;
            }
            // Server-generated on create; never require client input.
            if ($field->type === DynFieldType::AUTOMATIC_ID) {
                continue;
            }
            if ($only !== null && ! isset($only[$field->name])) {
                continue;
            }
            if (! array_key_exists($field->name, $values) || $values[$field->name] === null || $values[$field->name] === '') {
                $errors[$field->name] = $field->label.' is required.';
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Enforce "Prevent duplicates" on text / textarea (and other) fields when options_json says so.
     *
     * @param  array<string, mixed>  $values
     * @param  list<string>|null  $onlyFieldNames
     */
    private function assertUniqueFieldValues(
        DynEntity $entity,
        array $values,
        ?string $exceptRecordId = null,
        ?array $onlyFieldNames = null,
    ): void {
        $entity->loadMissing('fields');
        $errors = [];
        $only = $onlyFieldNames === null ? null : array_fill_keys($onlyFieldNames, true);

        foreach ($entity->fields as $field) {
            if ($field->is_virtual) {
                continue;
            }
            if ($only !== null && ! isset($only[$field->name])) {
                continue;
            }
            if (! $this->fieldPreventsDuplicates($field)) {
                continue;
            }
            if (! array_key_exists($field->name, $values)) {
                continue;
            }
            $raw = $values[$field->name];
            if ($raw === null || $raw === '') {
                continue;
            }
            $needle = substr(trim((string) $raw), 0, 512);
            if ($needle === '') {
                continue;
            }

            $exists = DynRecordIndex::query()
                ->where('entity_id', $entity->id)
                ->where('field_name', $field->name)
                ->where('value_string', $needle)
                ->when(
                    $exceptRecordId !== null,
                    fn ($q) => $q->where('record_id', '!=', $exceptRecordId),
                )
                ->whereExists(function ($sub) use ($entity): void {
                    $sub->selectRaw('1')
                        ->from('dyn_records')
                        ->whereColumn('dyn_records.id', 'dyn_record_indexes.record_id')
                        ->where('dyn_records.entity_id', $entity->id)
                        ->where('dyn_records.is_deleted', false);
                })
                ->exists();

            if ($exists) {
                $errors[$field->name] = $field->label.' must be unique. That value is already used.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function fieldPreventsDuplicates(DynField $field): bool
    {
        $options = $field->options_json;
        if (! is_array($options)) {
            return false;
        }

        return (bool) ($options['prevent_duplicates'] ?? $options['unique'] ?? $options['is_unique'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function resolveTitle(DynEntity $entity, array $values, mixed $explicit): ?string
    {
        if (is_string($explicit) && trim($explicit) !== '') {
            return trim($explicit);
        }

        foreach (['site_name', 'project_name', 'name', 'title', 'site_code', 'project_number', 'code'] as $candidate) {
            if (! empty($values[$candidate]) && is_scalar($values[$candidate])) {
                return substr((string) $values[$candidate], 0, 255);
            }
        }

        $entity->loadMissing('fields');
        $firstText = $entity->fields->first(fn (DynField $f): bool => in_array($f->type, [DynFieldType::TEXT, DynFieldType::SELECT], true));
        if ($firstText && ! empty($values[$firstText->name]) && is_scalar($values[$firstText->name])) {
            return substr((string) $values[$firstText->name], 0, 255);
        }

        return null;
    }

    /**
     * @param  list<string>  $ids
     * @return array{deleted: int}
     */
    public function bulkSoftDelete(DynEntity $entity, array $ids, TenantUser $actor): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        abort_unless(count($ids) <= 500, 422, 'Select at most 500 records.');

        $deleted = 0;
        DB::transaction(function () use ($entity, $ids, $actor, &$deleted): void {
            $records = DynRecord::query()
                ->where('entity_id', $entity->id)
                ->where('is_deleted', false)
                ->whereIn('id', $ids)
                ->get();

            foreach ($records as $record) {
                $this->softDelete($record, $actor, false);
                $deleted++;
            }
        });

        if ($deleted > 0) {
            $this->activity->record(
                module: 'dynamic_entities',
                action: 'dyn_record.bulk_deleted',
                summary: "Bulk deleted {$deleted} record(s) · {$entity->name}",
                entityType: 'dyn_entity',
                entityId: (string) $entity->slug,
                entityLabel: (string) $entity->name,
                actor: $actor,
                metadata: [
                    'entity_slug' => (string) $entity->slug,
                    'count' => $deleted,
                ],
            );
        }

        return ['deleted' => $deleted];
    }

    /**
     * @param  list<string>  $ids
     * @return array{duplicated: int, ids: list<string>}
     */
    public function bulkDuplicate(DynEntity $entity, array $ids, TenantUser $actor): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        abort_unless(count($ids) <= 100, 422, 'Duplicate at most 100 records at a time.');

        $createdIds = [];
        DB::transaction(function () use ($entity, $ids, $actor, &$createdIds): void {
            $records = DynRecord::query()
                ->where('entity_id', $entity->id)
                ->where('is_deleted', false)
                ->whereIn('id', $ids)
                ->get();

            foreach ($records as $record) {
                $values = $record->values_json ?? [];
                $copy = $this->create($entity, [
                    'title' => $record->title ? $record->title.' (copy)' : null,
                    'status' => $record->status,
                    'values' => $values,
                    'parent_record_id' => $record->parent_record_id,
                ], $actor, false);
                $createdIds[] = (string) $copy->id;
            }
        });

        if ($createdIds !== []) {
            $this->activity->record(
                module: 'dynamic_entities',
                action: 'dyn_record.bulk_duplicated',
                summary: 'Bulk duplicated '.count($createdIds).' record(s) · '.$entity->name,
                entityType: 'dyn_entity',
                entityId: (string) $entity->slug,
                entityLabel: (string) $entity->name,
                actor: $actor,
                metadata: [
                    'entity_slug' => (string) $entity->slug,
                    'count' => count($createdIds),
                    'ids' => array_slice($createdIds, 0, 50),
                ],
            );
        }

        return ['duplicated' => count($createdIds), 'ids' => $createdIds];
    }

    /**
     * @param  list<string>  $ids
     * @param  array<string, mixed>  $data
     * @return array{updated: int}
     */
    public function bulkUpdate(DynEntity $entity, array $ids, array $data, TenantUser $actor): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        abort_unless(count($ids) <= 500, 422, 'Update at most 500 records at a time.');

        $updated = 0;
        DB::transaction(function () use ($entity, $ids, $data, $actor, &$updated): void {
            $records = DynRecord::query()
                ->where('entity_id', $entity->id)
                ->where('is_deleted', false)
                ->whereIn('id', $ids)
                ->get();

            foreach ($records as $record) {
                $this->update($record, $data, $actor, false);
                $updated++;
            }
        });

        if ($updated > 0) {
            $this->activity->record(
                module: 'dynamic_entities',
                action: 'dyn_record.bulk_updated',
                summary: "Bulk updated {$updated} record(s) · {$entity->name}",
                entityType: 'dyn_entity',
                entityId: (string) $entity->slug,
                entityLabel: (string) $entity->name,
                actor: $actor,
                metadata: [
                    'entity_slug' => (string) $entity->slug,
                    'count' => $updated,
                    'fields' => array_keys($data['values'] ?? $data),
                ],
            );
        }

        return ['updated' => $updated];
    }

    /**
     * @param  list<string>|null  $ids
     * @param  array<string, mixed>  $filters
     * @return array{headers: list<string>, rows: list<list<string>>, summary: array<string, float|int>}
     */
    public function exportRows(DynEntity $entity, ?array $ids, array $filters = [], int $max = 2000): array
    {
        $entity->loadMissing('fields');
        $columnFields = $entity->fields
            ->filter(fn (DynField $f): bool => (bool) $f->show_in_table && ! (bool) $f->is_system_field)
            ->sortBy('field_order')
            ->values();

        $query = DynRecord::query()
            ->where('entity_id', $entity->id)
            ->where('is_deleted', false);

        if (is_array($ids) && $ids !== []) {
            $query->whereIn('id', array_slice(array_values(array_unique($ids)), 0, $max));
        } else {
            if (! empty($filters['search'])) {
                $search = trim((string) $filters['search']);
                $query->where(function ($q) use ($search): void {
                    $q->where('title', 'like', '%'.$search.'%')
                        ->orWhere('source_external_id', 'like', '%'.$search.'%');
                });
            }
            if (! empty($filters['status'])) {
                $query->where('status', (string) $filters['status']);
            }
            $query->orderByDesc('updated_at')->limit($max);
        }

        $records = $query->get();
        $headers = ['id', 'title', 'status'];
        foreach ($columnFields as $field) {
            $headers[] = $field->name;
        }

        $rows = [];
        $summary = ['count' => $records->count()];
        $totalFields = $entity->fields->where('calculate_totals', true);

        foreach ($records as $record) {
            $values = $record->values_json ?? [];
            $line = [
                (string) $record->id,
                (string) ($record->title ?? ''),
                (string) ($record->status ?? ''),
            ];
            foreach ($columnFields as $field) {
                $raw = $values[$field->name] ?? '';
                $line[] = is_scalar($raw) || $raw === null ? (string) ($raw ?? '') : json_encode($raw);
            }
            $rows[] = $line;

            foreach ($totalFields as $field) {
                $raw = $values[$field->name] ?? null;
                if (is_numeric($raw)) {
                    $key = $field->name;
                    $summary[$key] = (float) ($summary[$key] ?? 0) + (float) $raw;
                }
            }
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
            'summary' => $summary,
        ];
    }

    /**
     * @param  array<string, array{from?: mixed, to?: mixed}|mixed>  $changes
     * @param  array<string, mixed>  $metadata
     */
    private function logRecordActivity(
        string $action,
        DynEntity $entity,
        DynRecord $record,
        TenantUser $actor,
        string $summary,
        array $changes = [],
        array $metadata = [],
    ): void {
        $this->activity->record(
            module: 'dynamic_entities',
            action: $action,
            summary: $summary,
            entityType: 'dyn_record',
            entityId: (string) $record->id,
            entityLabel: $this->recordLabel($entity, $record),
            actor: $actor,
            metadata: array_merge([
                'entity_slug' => (string) $entity->slug,
                'entity_name' => (string) $entity->name,
            ], $metadata),
            changes: $changes,
        );
    }

    private function recordLabel(DynEntity $entity, DynRecord $record): string
    {
        $title = trim((string) ($record->title ?? ''));
        if ($title !== '') {
            return $entity->name.' · '.$title;
        }

        return $entity->name.' · '.substr((string) $record->id, 0, 8);
    }
}
