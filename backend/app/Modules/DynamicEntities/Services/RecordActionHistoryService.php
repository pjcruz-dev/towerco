<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\AssetManagement\Models\Asset;
use App\Modules\AssetManagement\Services\AssetRegistryService;
use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynRecord;
use App\Modules\DynamicEntities\Models\RecordActionHistory;
use App\Modules\DynamicEntities\Support\RecordActionHistoryAccess;
use App\Modules\DynamicEntities\Support\RecordActionHistoryCopy;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Reversible snapshots for record creates, edits, deletes, and imports.
 * Writes are ignored when the tenant table has not been migrated yet.
 */
final class RecordActionHistoryService
{
    private const MAX_SNAPSHOTS = 200;

    /** @var list<string> */
    private const ASSET_COLUMNS = [
        'asset_tag',
        'serial_number',
        'asset_type',
        'status',
        'manufacturer',
        'product_name',
        'product_version',
        'hostname',
        'old_hostname',
        'processor',
        'installed_ram',
        'operating_system',
        'system_type',
        'bios_version',
        'purchase_date',
        'warranty_end',
        'commission_date',
        'assigned_user_id',
        'notes',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function dynSnapshot(DynRecord $record): array
    {
        return [
            'id' => (string) $record->id,
            'status' => $record->status,
            'title' => $record->title,
            'values' => is_array($record->values_json) ? $record->values_json : [],
            'parent_record_id' => $record->parent_record_id,
            'assigned_user_id' => $record->assigned_user_id,
            'location_id' => $record->location_id,
            'is_deleted' => (bool) $record->is_deleted,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function assetSnapshot(Asset $asset): array
    {
        $columns = [];
        foreach (self::ASSET_COLUMNS as $column) {
            $value = $asset->{$column};
            $columns[$column] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
        }

        return [
            'id' => (string) $asset->id,
            'columns' => $columns,
            'values' => is_array($asset->custom_values_json) ? $asset->custom_values_json : [],
            'custom_values' => is_array($asset->custom_values_json) ? $asset->custom_values_json : [],
        ];
    }

    public function recordDynCreated(DynEntity $entity, DynRecord $record, TenantUser $actor): void
    {
        $after = self::dynSnapshot($record);
        $this->store(
            module: 'dynamic_entities',
            slug: (string) $entity->slug,
            label: (string) $entity->name,
            kind: 'created',
            count: 1,
            recordId: (string) $record->id,
            fieldLabel: null,
            actor: $actor,
            before: null,
            after: $after,
        );
    }

    /**
     * @param  array<string, mixed>  $before
     */
    public function recordDynUpdated(DynEntity $entity, DynRecord $record, TenantUser $actor, array $before): void
    {
        $after = self::dynSnapshot($record);
        if ($this->same($before, $after)) {
            return;
        }
        $this->store(
            module: 'dynamic_entities',
            slug: (string) $entity->slug,
            label: (string) $entity->name,
            kind: 'updated',
            count: 1,
            recordId: (string) $record->id,
            fieldLabel: $this->fieldLabel($before, $after),
            actor: $actor,
            before: $before,
            after: $after,
        );
    }

    /**
     * @param  array<string, mixed>  $before
     */
    public function recordDynDeleted(DynEntity $entity, TenantUser $actor, array $before): void
    {
        $this->store(
            module: 'dynamic_entities',
            slug: (string) $entity->slug,
            label: (string) $entity->name,
            kind: 'deleted',
            count: 1,
            recordId: isset($before['id']) ? (string) $before['id'] : null,
            fieldLabel: null,
            actor: $actor,
            before: $before,
            after: null,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $snapshots
     */
    public function recordDynBulkDeleted(DynEntity $entity, TenantUser $actor, array $snapshots): void
    {
        $count = count($snapshots);
        if ($count === 0) {
            return;
        }
        $stored = array_slice($snapshots, 0, self::MAX_SNAPSHOTS);
        $this->store(
            module: 'dynamic_entities',
            slug: (string) $entity->slug,
            label: (string) $entity->name,
            kind: 'deleted',
            count: $count,
            recordId: null,
            fieldLabel: null,
            actor: $actor,
            before: ['records' => $stored],
            after: null,
        );
    }

    public function recordAssetCreated(Asset $asset, TenantUser $actor): void
    {
        $this->store(
            module: 'assets',
            slug: 'it_asset',
            label: 'Assets',
            kind: 'created',
            count: 1,
            recordId: (string) $asset->id,
            fieldLabel: null,
            actor: $actor,
            before: null,
            after: self::assetSnapshot($asset),
        );
    }

    /**
     * @param  array<string, mixed>  $before
     */
    public function recordAssetUpdated(Asset $asset, TenantUser $actor, array $before): void
    {
        $after = self::assetSnapshot($asset);
        if ($this->same($before, $after)) {
            return;
        }
        $this->store(
            module: 'assets',
            slug: 'it_asset',
            label: 'Assets',
            kind: 'updated',
            count: 1,
            recordId: (string) $asset->id,
            fieldLabel: $this->fieldLabel($before, $after),
            actor: $actor,
            before: $before,
            after: $after,
        );
    }

    /**
     * @param  list<string>  $createdIds
     * @param  list<array{id: string, before: array<string, mixed>, after: array<string, mixed>}>  $updates
     */
    public function recordImport(
        string $module,
        string $slug,
        string $label,
        TenantUser $actor,
        array $createdIds,
        array $updates,
    ): void {
        $count = count($createdIds) + count($updates);
        if ($count === 0) {
            return;
        }
        $payload = [
            'created_ids' => array_slice(array_values($createdIds), 0, self::MAX_SNAPSHOTS),
            'updates' => array_slice(array_values($updates), 0, self::MAX_SNAPSHOTS),
        ];
        $this->store(
            module: $module,
            slug: $slug,
            label: $label,
            kind: 'imported',
            count: $count,
            recordId: null,
            fieldLabel: null,
            actor: $actor,
            before: $payload,
            after: $payload,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(string $slug, ?string $viewerId, string $scope): array
    {
        if (! $this->ready()) {
            return [];
        }

        $query = RecordActionHistory::query()
            ->where('entity_slug', $slug)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(50);
        if ($scope === 'mine' && $viewerId !== null && $viewerId !== '') {
            $query->where('actor_user_id', $viewerId);
        }

        return $this->presentOrdered($query, $viewerId);
    }

    /**
     * Recent actions across dynamic entities, assets, and ticketing.
     *
     * @return list<array<string, mixed>>
     */
    public function listRecent(?string $viewerId, string $scope, bool $dynamicEntities, bool $assets, bool $ticketing): array
    {
        if (! $this->ready() || (! $dynamicEntities && ! $assets && ! $ticketing)) {
            return [];
        }

        $query = RecordActionHistory::query()
            ->where(function ($inner) use ($dynamicEntities, $assets, $ticketing): void {
                if ($dynamicEntities) {
                    $inner->orWhere('module', 'dynamic_entities');
                } elseif ($ticketing) {
                    $inner->orWhere(function ($tickets): void {
                        $tickets->where('module', 'dynamic_entities')
                            ->whereIn('entity_slug', RecordActionHistoryAccess::TICKETING_SLUGS);
                    });
                }
                if ($assets) {
                    $inner->orWhere('module', 'assets');
                }
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(50);
        if ($scope === 'mine' && $viewerId !== null && $viewerId !== '') {
            $query->where('actor_user_id', $viewerId);
        }

        return $this->presentOrdered($query, $viewerId);
    }

    public function revert(RecordActionHistory $row): RecordActionHistory
    {
        return $this->flip($row, reverting: true);
    }

    public function reapply(RecordActionHistory $row): RecordActionHistory
    {
        return $this->flip($row, reverting: false);
    }

    public function undo(string $slug, TenantUser $actor): RecordActionHistory
    {
        $this->assertReady();
        $row = RecordActionHistory::query()
            ->where('entity_slug', $slug)
            ->where('actor_user_id', $actor->id)
            ->where('status', 'applied')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
        if (! $row instanceof RecordActionHistory) {
            throw ValidationException::withMessages(['history' => ['Nothing to undo.']]);
        }

        return $this->revert($row);
    }

    public function redo(string $slug, TenantUser $actor): RecordActionHistory
    {
        $this->assertReady();
        $row = RecordActionHistory::query()
            ->where('entity_slug', $slug)
            ->where('actor_user_id', $actor->id)
            ->where('status', 'reverted')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
        if (! $row instanceof RecordActionHistory) {
            throw ValidationException::withMessages(['history' => ['Nothing to put back.']]);
        }

        return $this->reapply($row);
    }

    public function findForEntity(string $slug, string $id): RecordActionHistory
    {
        $this->assertReady();
        $row = RecordActionHistory::query()
            ->where('entity_slug', $slug)
            ->where('id', $id)
            ->first();
        if (! $row instanceof RecordActionHistory) {
            abort(404);
        }

        return $row;
    }

    /**
     * Order by the key only, then load the 50 snapshots. A filesort of the
     * full row (before/after JSON) exceeds MySQL's sort buffer.
     *
     * @param  Builder<RecordActionHistory>  $query
     * @return list<array<string, mixed>>
     */
    private function presentOrdered(Builder $query, ?string $viewerId): array
    {
        $ids = $query->clone()->select('id')->pluck('id');
        if ($ids->isEmpty()) {
            return [];
        }

        $byId = RecordActionHistory::query()
            ->whereIn('id', $ids->all())
            ->get()
            ->keyBy(fn (RecordActionHistory $row): string => (string) $row->id);

        $rows = [];
        foreach ($ids as $id) {
            $row = $byId->get((string) $id);
            if ($row instanceof RecordActionHistory) {
                $rows[] = $this->present($row, $viewerId);
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function present(RecordActionHistory $row, ?string $viewerId): array
    {
        $before = is_array($row->before_json) ? $row->before_json : null;
        $after = is_array($row->after_json) ? $row->after_json : null;
        $reversible = RecordActionHistoryCopy::reversible(
            (string) $row->module,
            (string) $row->kind,
            $before,
            $after,
            (int) $row->record_count,
        );
        $you = $viewerId !== null && (string) $row->actor_user_id === $viewerId;

        return [
            'id' => (string) $row->id,
            'entity_slug' => (string) $row->entity_slug,
            'module' => (string) $row->module,
            'kind' => (string) $row->kind,
            'status' => (string) $row->status,
            'title' => RecordActionHistoryCopy::title(
                (string) $row->kind,
                (string) $row->entity_label,
                (int) $row->record_count,
                $row->field_label,
            ),
            'detail' => RecordActionHistoryCopy::detail(
                (string) $row->kind,
                (int) $row->record_count,
                (string) $row->entity_label,
            ),
            'actor_name' => $you ? 'You' : (string) ($row->actor_name ?: 'Someone'),
            'created_at' => $row->created_at?->toIso8601String(),
            'reversible' => $reversible,
            'blocked_reason' => RecordActionHistoryCopy::blockedReason((string) $row->module, (string) $row->kind, $reversible),
        ];
    }

    private function flip(RecordActionHistory $row, bool $reverting): RecordActionHistory
    {
        $this->assertReady();
        $expected = $reverting ? 'applied' : 'reverted';
        if ((string) $row->status !== $expected) {
            throw ValidationException::withMessages([
                'history' => [$reverting ? 'That action is already reverted.' : 'That action is already applied.'],
            ]);
        }
        $before = is_array($row->before_json) ? $row->before_json : null;
        $after = is_array($row->after_json) ? $row->after_json : null;
        if (! RecordActionHistoryCopy::reversible((string) $row->module, (string) $row->kind, $before, $after, (int) $row->record_count)) {
            throw ValidationException::withMessages([
                'history' => [RecordActionHistoryCopy::blockedReason((string) $row->module, (string) $row->kind, false) ?? 'This action cannot be reverted.'],
            ]);
        }

        DB::connection('tenant')->transaction(function () use ($row, $reverting): void {
            $this->apply($row, $reverting);
            $row->status = $reverting ? 'reverted' : 'applied';
            $row->save();
        });

        return $row->refresh();
    }

    private function apply(RecordActionHistory $row, bool $reverting): void
    {
        $before = is_array($row->before_json) ? $row->before_json : [];
        $after = is_array($row->after_json) ? $row->after_json : [];

        if ((string) $row->module === 'assets') {
            $this->applyAsset((string) $row->kind, $before, $after, $reverting);

            return;
        }

        match ((string) $row->kind) {
            'created' => $this->writeDyn($after, $reverting),
            'updated' => $this->writeDyn($reverting ? $before : $after, false),
            'deleted' => $this->applyDynDeleted($before, ! $reverting),
            'imported' => $this->applyDynImport($before, $reverting),
            default => throw ValidationException::withMessages(['history' => ['This action cannot be reverted.']]),
        };
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function applyAsset(string $kind, array $before, array $after, bool $reverting): void
    {
        if ($kind === 'updated') {
            $snapshot = $reverting ? $before : $after;
            $this->writeAsset($snapshot);

            return;
        }
        if ($kind === 'imported') {
            $updates = is_array($before['updates'] ?? null) ? $before['updates'] : [];
            foreach ($updates as $pair) {
                if (! is_array($pair)) {
                    continue;
                }
                $snapshot = $reverting ? ($pair['before'] ?? null) : ($pair['after'] ?? null);
                if (is_array($snapshot)) {
                    $this->writeAsset($snapshot);
                }
            }

            return;
        }

        throw ValidationException::withMessages(['history' => ['This action cannot be reverted.']]);
    }

    /**
     * @param  array<string, mixed>  $before
     */
    private function applyDynDeleted(array $before, bool $deleted): void
    {
        $records = $before['records'] ?? null;
        if (is_array($records)) {
            foreach ($records as $snapshot) {
                if (is_array($snapshot)) {
                    $this->writeDyn($snapshot, $deleted);
                }
            }

            return;
        }
        $this->writeDyn($before, $deleted);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyDynImport(array $payload, bool $reverting): void
    {
        $ids = is_array($payload['created_ids'] ?? null) ? $payload['created_ids'] : [];
        foreach ($ids as $id) {
            $record = DynRecord::query()->find((string) $id);
            if (! $record instanceof DynRecord) {
                continue;
            }
            $record->is_deleted = $reverting;
            $record->deleted_at = $reverting ? now() : null;
            $record->save();
        }
        $updates = is_array($payload['updates'] ?? null) ? $payload['updates'] : [];
        foreach ($updates as $pair) {
            if (! is_array($pair)) {
                continue;
            }
            $snapshot = $reverting ? ($pair['before'] ?? null) : ($pair['after'] ?? null);
            if (is_array($snapshot)) {
                $this->writeDyn($snapshot, false);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function writeDyn(array $snapshot, bool $deleted): void
    {
        $id = (string) ($snapshot['id'] ?? '');
        if ($id === '') {
            return;
        }
        $record = DynRecord::query()->find($id);
        if (! $record instanceof DynRecord) {
            return;
        }
        $record->status = isset($snapshot['status']) ? (is_scalar($snapshot['status']) ? (string) $snapshot['status'] : null) : null;
        $record->title = isset($snapshot['title']) && is_scalar($snapshot['title']) ? (string) $snapshot['title'] : null;
        $record->values_json = is_array($snapshot['values'] ?? null) ? $snapshot['values'] : [];
        $record->parent_record_id = $snapshot['parent_record_id'] ?? null;
        $record->assigned_user_id = $snapshot['assigned_user_id'] ?? null;
        $record->location_id = $snapshot['location_id'] ?? null;
        $record->is_deleted = $deleted;
        $record->deleted_at = $deleted ? now() : null;
        try {
            $record->save();
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'history' => ['Could not restore that record. A unique value may already be in use.'],
            ]);
        }
        $entity = $record->entity;
        if ($entity instanceof DynEntity) {
            app(DynRecordService::class)->rebuildIndexes($entity, $record);
        }
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function writeAsset(array $snapshot): void
    {
        $id = (string) ($snapshot['id'] ?? '');
        if ($id === '') {
            return;
        }
        $asset = Asset::query()->find($id);
        if (! $asset instanceof Asset) {
            return;
        }
        $columns = is_array($snapshot['columns'] ?? null) ? $snapshot['columns'] : [];
        foreach (self::ASSET_COLUMNS as $column) {
            if (! array_key_exists($column, $columns)) {
                continue;
            }
            $asset->{$column} = $columns[$column];
        }
        if (array_key_exists('custom_values', $snapshot)) {
            $asset->custom_values_json = is_array($snapshot['custom_values']) ? $snapshot['custom_values'] : [];
        }
        try {
            $asset->save();
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'history' => ['Could not restore that asset. A serial number or hostname may already be in use.'],
            ]);
        }
        app(AssetRegistryService::class)->syncFieldIndexes($asset);
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private function store(
        string $module,
        string $slug,
        string $label,
        string $kind,
        int $count,
        ?string $recordId,
        ?string $fieldLabel,
        TenantUser $actor,
        ?array $before,
        ?array $after,
    ): void {
        if (! $this->ready()) {
            return;
        }
        try {
            RecordActionHistory::query()->create([
                'module' => $module,
                'entity_slug' => $slug,
                'entity_label' => $label,
                'kind' => $kind,
                'record_count' => max(1, $count),
                'record_id' => $recordId,
                'field_label' => $fieldLabel,
                'summary' => RecordActionHistoryCopy::title($kind, $label, $count, $fieldLabel),
                'actor_user_id' => $actor->id,
                'actor_name' => is_string($actor->name) ? $actor->name : null,
                'before_json' => $before,
                'after_json' => $after,
                'status' => 'applied',
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // History must not block the edit that already succeeded.
        }
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function fieldLabel(array $before, array $after): ?string
    {
        $fromValues = RecordActionHistoryCopy::changedFieldLabel($before, $after);
        $beforeColumns = is_array($before['columns'] ?? null) ? $before['columns'] : [];
        $afterColumns = is_array($after['columns'] ?? null) ? $after['columns'] : [];
        $fromColumns = RecordActionHistoryCopy::changedFieldLabel(
            ['values' => $beforeColumns],
            ['values' => $afterColumns],
        );
        if ($fromValues !== null && $fromColumns !== null) {
            return null;
        }

        return $fromValues ?? $fromColumns;
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function same(array $before, array $after): bool
    {
        unset($before['is_deleted'], $after['is_deleted']);

        return json_encode($before) === json_encode($after);
    }

    private function ready(): bool
    {
        try {
            return Schema::connection('tenant')->hasTable('record_action_histories');
        } catch (\Throwable) {
            return false;
        }
    }

    private function assertReady(): void
    {
        if (! $this->ready()) {
            throw ValidationException::withMessages([
                'history' => ['Action history is not available until this workspace is migrated.'],
            ]);
        }
    }
}
