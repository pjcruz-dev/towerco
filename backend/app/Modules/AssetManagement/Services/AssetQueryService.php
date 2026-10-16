<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Services;

use App\Modules\AssetManagement\Models\Accountability;
use App\Modules\AssetManagement\Models\AccountabilityItem;
use App\Modules\AssetManagement\Models\Asset;
use App\Modules\AssetManagement\Models\AssetRepair;
use App\Modules\AssetManagement\Models\Offboarding;
use App\Modules\AssetManagement\Support\AssetStatus;
use App\Modules\DynamicEntities\Models\DynField;
use App\Modules\DynamicEntities\Support\DynFieldType;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class AssetQueryService
{
    public function __construct(
        private readonly AssetRegistryService $registry,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, ?string $onlyUserId = null): LengthAwarePaginator
    {
        $query = Asset::query()->with('assignee:id,name,email,department,job_title');
        if ($onlyUserId !== null) {
            $query->where('assigned_user_id', $onlyUserId);
        }
        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('asset_tag', 'like', $like)
                    ->orWhere('serial_number', 'like', $like)
                    ->orWhere('hostname', 'like', $like)
                    ->orWhere('manufacturer', 'like', $like)
                    ->orWhere('product_name', 'like', $like)
                    ->orWhereHas('assignee', function (Builder $user) use ($like): void {
                        $user->where('name', 'like', $like)->orWhere('department', 'like', $like);
                    })
                    ->orWhereHas('networkAdapters', function (Builder $adapter) use ($like): void {
                        $adapter->where('mac_address', 'like', $like);
                    });
            });
        }
        foreach (['asset_type', 'manufacturer'] as $column) {
            if (! empty($filters[$column])) {
                $query->where($column, $filters[$column]);
            }
        }
        $statuses = $this->statusTokens((string) ($filters['status'] ?? ''));
        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }
        if (! empty($filters['department'])) {
            $department = (string) $filters['department'];
            $query->whereHas('assignee', fn (Builder $user) => $user->where('department', $department));
        }
        if (! empty($filters['lifecycle'])) {
            $this->applyLifecycle($query, (string) $filters['lifecycle']);
        }
        if (($filters['attention'] ?? '') === 'accountability') {
            $query->whereIn('id', $this->pendingAccountabilityAssetIds());
        }
        if (($filters['repair'] ?? '') === 'PENDING_VENDOR') {
            $query->whereIn('id', AssetRepair::query()->select('asset_id')->where('result', 'PENDING_VENDOR'));
        }
        $sort = (string) ($filters['sort'] ?? '-updated_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $sortable = [
            'asset_tag',
            'serial_number',
            'asset_type',
            'status',
            'manufacturer',
            'product_name',
            'product_version',
            'hostname',
            'purchase_date',
            'warranty_end',
            'commission_date',
            'processor',
            'installed_ram',
            'operating_system',
            'bios_version',
            'notes',
            'updated_at',
        ];
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 25)));

        if ($column === 'assignee') {
            return $query
                ->orderBy(
                    TenantUser::query()->select('name')->whereColumn('users.id', 'it_assets.assigned_user_id'),
                    $direction,
                )
                ->paginate($perPage);
        }

        if (! in_array($column, $sortable, true)) {
            if ($this->orderByCustomField($query, $column, $direction)) {
                return $query->paginate($perPage);
            }
            $column = 'updated_at';
            $direction = 'desc';
        }

        return $query->orderBy($column, $direction)->paginate($perPage);
    }

    private function orderByCustomField(Builder $query, string $column, string $direction): bool
    {
        if (in_array($column, ['id', 'created_at', 'assigned_user_id'], true)) {
            return false;
        }
        if (! preg_match('/\A[a-z][a-z0-9_]{0,63}\z/', $column)) {
            return false;
        }

        $field = DynField::query()
            ->where('name', $column)
            ->where('is_system_field', false)
            ->whereHas('entity', fn (Builder $entity) => $entity->where('slug', AssetSchemaService::SLUG))
            ->first();
        if (! $field instanceof DynField) {
            return false;
        }

        $directionSql = $direction === 'asc' ? 'asc' : 'desc';
        $path = '$.'.$column;
        $driver = $query->getConnection()->getDriverName();
        $extract = $driver === 'sqlite'
            ? 'json_extract(custom_values_json, ?)'
            : 'JSON_UNQUOTE(JSON_EXTRACT(custom_values_json, ?))';
        if (in_array($field->type, [DynFieldType::NUMBER, DynFieldType::DECIMAL], true)) {
            $cast = $driver === 'sqlite' ? 'REAL' : 'DECIMAL(20, 6)';
            $extract = 'CAST('.$extract.' AS '.$cast.')';
        }
        $query->orderByRaw($extract.' '.$directionSql, [$path]);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $counts = Asset::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $near = $this->lifecycleQuery('LIFECYCLE_REVIEW,REPLACEMENT_PLANNING')->count();

        return [
            'updated_at' => now()->toIso8601String(),
            'cards' => [
                ['key' => 'total', 'label' => 'Total assets', 'value' => Asset::query()->count(), 'href' => '/assets'],
                ['key' => 'assigned', 'label' => 'Assigned', 'value' => (int) ($counts['ASSIGNED'] ?? 0), 'href' => '/assets?status=ASSIGNED'],
                ['key' => 'available', 'label' => 'Available', 'value' => (int) ($counts['AVAILABLE'] ?? 0), 'href' => '/assets?status=AVAILABLE'],
                ['key' => 'repair', 'label' => 'Under repair', 'value' => (int) ($counts['UNDER_REPAIR'] ?? 0), 'href' => '/assets?status=UNDER_REPAIR'],
                ['key' => 'accountability', 'label' => 'Pending accountability', 'value' => $this->pendingAccountabilityAssetCount(), 'href' => '/assets?attention=accountability'],
                ['key' => 'offboarding', 'label' => 'Pending offboarding', 'value' => Offboarding::query()->whereIn('status', ['OPEN', 'INCOMPLETE'])->count(), 'href' => '/assets/offboarding'],
                ['key' => 'near_eol', 'label' => 'Near EOL', 'value' => $near, 'href' => '/assets?lifecycle=LIFECYCLE_REVIEW,REPLACEMENT_PLANNING'],
                ['key' => 'eol', 'label' => 'EOL', 'value' => $this->lifecycleQuery('EOL')->count(), 'href' => '/assets?lifecycle=EOL'],
                ['key' => 'lost', 'label' => 'Lost', 'value' => (int) ($counts['LOST'] ?? 0) + (int) ($counts['STOLEN'] ?? 0), 'href' => '/assets?status=LOST,STOLEN'],
                ['key' => 'vendor', 'label' => 'Vendor repair pending', 'value' => $this->pendingVendorAssetCount(), 'href' => '/assets?repair=PENDING_VENDOR'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Asset $asset): array
    {
        $asset->loadMissing([
            'assignee:id,name,email,department,job_title,given_name,surname',
            'assignments.employee:id,name,email',
            'events',
            'networkAdapters',
            'disks',
            'videoCards',
            'applications',
            'repairs.replacements',
            'hostnameHistory',
        ]);
        $lifecycle = $this->registry->lifecycle($asset);
        $auditActors = $this->auditActorNames($asset);
        $accountability = Accountability::query()
            ->with('items')
            ->whereHas('items', fn (Builder $items) => $items->where('asset_id', $asset->id))
            ->latest('created_at')
            ->first();
        $employeeName = $asset->assignee?->name;
        if ($accountability instanceof Accountability) {
            $employee = TenantUser::query()->find($accountability->employee_id, ['id', 'name']);
            if ($employee instanceof TenantUser && is_string($employee->name) && $employee->name !== '') {
                $employeeName = $employee->name;
            }
        }

        return [
            'id' => $asset->id,
            'asset_tag' => $asset->asset_tag,
            'serial_number' => $asset->serial_number,
            'asset_type' => $asset->asset_type,
            'status' => $asset->status,
            'manufacturer' => $asset->manufacturer,
            'product_name' => $asset->product_name,
            'product_version' => $asset->product_version,
            'hostname' => $asset->hostname,
            'old_hostname' => $asset->old_hostname,
            'processor' => $asset->processor,
            'installed_ram' => $asset->installed_ram,
            'operating_system' => $asset->operating_system,
            'system_type' => $asset->system_type,
            'bios_version' => $asset->bios_version,
            'purchase_date' => $asset->purchase_date?->toDateString(),
            'warranty_end' => $asset->warranty_end?->toDateString(),
            'commission_date' => $asset->commission_date?->toDateString(),
            'notes' => $asset->notes,
            'custom_values' => $asset->custom_values_json ?? [],
            'created_at' => $asset->created_at?->toIso8601String(),
            'updated_at' => $asset->updated_at?->toIso8601String(),
            'created_by_name' => $auditActors['created_by_name'],
            'updated_by_name' => $auditActors['updated_by_name'],
            'lifecycle' => $lifecycle,
            'accountability' => $accountability instanceof Accountability ? [
                'id' => $accountability->id,
                'number' => $accountability->number,
                'status' => $accountability->status,
                'employee_id' => $accountability->employee_id,
                'employee_name' => $employeeName,
                'items' => $this->accountabilityLines($asset, $accountability),
            ] : null,
            'qr_path' => '/assets/'.$asset->asset_tag,
            'assignee' => $this->person($asset->assignee),
            'assignments' => $asset->assignments->map(fn ($row): array => [
                'id' => $row->id,
                'status' => $row->status,
                'assigned_at' => $row->assigned_at?->toIso8601String(),
                'returned_at' => $row->returned_at?->toIso8601String(),
                'employee' => $this->person($row->employee),
                'notes' => $row->notes,
            ])->values()->all(),
            'events' => $asset->events->sortByDesc('occurred_at')->values()->map(fn ($row): array => [
                'id' => $row->id,
                'event_type' => $row->event_type,
                'from_place' => $row->from_place,
                'to_place' => $row->to_place,
                'occurred_at' => $row->occurred_at?->toIso8601String(),
                'payload' => $row->payload_json,
            ])->all(),
            'network_adapters' => $asset->networkAdapters->map(fn ($row): array => [
                'id' => $row->id,
                'adapter_name' => $row->adapter_name,
                'adapter_type' => $row->adapter_type,
                'mac_address' => $row->mac_address,
                'ip_address' => $row->ip_address,
                'is_active' => $row->is_active,
            ])->values()->all(),
            'disks' => $asset->disks->map(fn ($row): array => [
                'id' => $row->id,
                'model' => $row->model,
                'serial_number' => $row->serial_number,
                'size_label' => $row->size_label,
            ])->values()->all(),
            'video_cards' => $asset->videoCards->map(fn ($row): array => [
                'id' => $row->id,
                'name' => $row->name,
                'driver_version' => $row->driver_version,
            ])->values()->all(),
            'applications' => $asset->applications->map(fn ($row): array => [
                'id' => $row->id,
                'application_name' => $row->application_name,
                'version' => $row->version,
                'publisher' => $row->publisher,
            ])->values()->all(),
            'repairs' => $asset->repairs->map(fn ($row): array => [
                'id' => $row->id,
                'status' => $row->status,
                'usable' => $row->usable,
                'problem' => $row->problem,
                'result' => $row->result,
                'vendor_email_subject' => $row->vendor_email_subject,
                'vendor_email_body' => $row->vendor_email_body,
                'replacements' => $row->replacements->map(fn ($link): array => [
                    'id' => $link->id,
                    'replacement_asset_id' => $link->replacement_asset_id,
                    'replacement_type' => $link->replacement_type,
                    'status' => $link->status,
                ])->values()->all(),
            ])->values()->all(),
            'hostname_history' => $asset->hostnameHistory->map(fn ($row): array => [
                'old_hostname' => $row->old_hostname,
                'new_hostname' => $row->new_hostname,
                'reason' => $row->reason,
                'changed_at' => $row->changed_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /**
     * @return array{created_by_name: string|null, updated_by_name: string|null}
     */
    private function auditActorNames(Asset $asset): array
    {
        $timeline = $asset->events->sortBy(fn ($row) => $row->occurred_at?->getTimestamp() ?? 0)->values();
        $created = $timeline->first();
        $updated = $timeline->last();
        $ids = collect([$created?->actor_user_id, $updated?->actor_user_id])
            ->filter(fn ($id) => is_string($id) && $id !== '')
            ->unique()
            ->values();
        $names = $ids->isEmpty()
            ? collect()
            : TenantUser::query()->whereIn('id', $ids->all())->pluck('name', 'id');

        return [
            'created_by_name' => is_string($created?->actor_user_id) ? ($names[$created->actor_user_id] ?? null) : null,
            'updated_by_name' => is_string($updated?->actor_user_id) ? ($names[$updated->actor_user_id] ?? null) : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function person(?TenantUser $user): ?array
    {
        if (! $user instanceof TenantUser) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'department' => $user->department,
            'job_title' => $user->job_title,
            'given_name' => $user->given_name,
            'surname' => $user->surname,
        ];
    }

    private function applyLifecycle(Builder $query, string $raw): void
    {
        $bands = [];
        foreach (explode(',', $raw) as $part) {
            $band = strtoupper(trim($part));
            if (in_array($band, ['ACTIVE', 'LIFECYCLE_REVIEW', 'REPLACEMENT_PLANNING', 'EOL'], true) && ! in_array($band, $bands, true)) {
                $bands[] = $band;
            }
        }
        if ($bands === []) {
            return;
        }
        if (count($bands) === 1) {
            $this->applyLifecycleBand($query, $bands[0]);

            return;
        }
        $query->where(function (Builder $outer) use ($bands): void {
            foreach ($bands as $index => $band) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $outer->{$method}(function (Builder $inner) use ($band): void {
                    $this->applyLifecycleBand($inner, $band);
                });
            }
        });
    }

    private function applyLifecycleBand(Builder $query, string $band): void
    {
        $now = now();
        [$olderThan, $newerThan] = match ($band) {
            'ACTIVE' => [null, $now->copy()->subMonths(36)],
            'LIFECYCLE_REVIEW' => [$now->copy()->subMonths(36), $now->copy()->subMonths(42)],
            'REPLACEMENT_PLANNING' => [$now->copy()->subMonths(42), $now->copy()->subMonths(48)],
            'EOL' => [$now->copy()->subMonths(48), null],
            default => [null, null],
        };
        $query->where(function (Builder $outer) use ($olderThan, $newerThan): void {
            foreach (['commission_date', 'purchase_date'] as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $outer->{$method}(function (Builder $inner) use ($column, $olderThan, $newerThan, $index): void {
                    $inner->whereNotNull($column);
                    if ($index === 1) {
                        $inner->whereNull('commission_date');
                    }
                    if ($olderThan !== null) {
                        $inner->whereDate($column, '<=', $olderThan->toDateString());
                    }
                    if ($newerThan !== null) {
                        $inner->whereDate($column, '>', $newerThan->toDateString());
                    }
                });
            }
        });
    }

    /**
     * @return list<string>
     */
    private function statusTokens(string $raw): array
    {
        $allowed = AssetStatus::values();
        $tokens = [];
        foreach (explode(',', $raw) as $part) {
            $token = strtoupper(trim($part));
            if (in_array($token, $allowed, true) && ! in_array($token, $tokens, true)) {
                $tokens[] = $token;
            }
        }

        return $tokens;
    }

    private function pendingAccountabilityAssetCount(): int
    {
        return (int) AccountabilityItem::query()
            ->whereNotNull('asset_id')
            ->whereIn('accountability_id', Accountability::query()->select('id')->where('status', 'PENDING_SIGNATURE'))
            ->selectRaw('count(distinct asset_id) as aggregate')
            ->value('aggregate');
    }

    /** @return Builder<AccountabilityItem> */
    private function pendingAccountabilityAssetIds(): Builder
    {
        return AccountabilityItem::query()
            ->select('asset_id')
            ->whereNotNull('asset_id')
            ->whereIn('accountability_id', Accountability::query()->select('id')->where('status', 'PENDING_SIGNATURE'));
    }

    private function pendingVendorAssetCount(): int
    {
        return (int) AssetRepair::query()
            ->where('result', 'PENDING_VENDOR')
            ->selectRaw('count(distinct asset_id) as aggregate')
            ->value('aggregate');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchPeople(string $term): array
    {
        $term = trim($term);
        $query = TenantUser::query()->where('is_active', true)->orderBy('name');
        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('department', 'like', $like);
            });
        }

        return $query->limit(20)->get(['id', 'name', 'email', 'department', 'job_title', 'given_name', 'surname'])
            ->map(fn (TenantUser $user): array => $this->person($user))
            ->filter()
            ->values()
            ->all();
    }

    private function lifecycleQuery(string $band): Builder
    {
        $query = Asset::query();
        $this->applyLifecycle($query, $band);

        return $query;
    }

    /**
     * @return list<array{quantity: string, item: string, brand: string, serial_number: string, asset_tag: string}>
     */
    private function accountabilityLines(Asset $asset, Accountability $accountability): array
    {
        $ids = $accountability->items->pluck('asset_id')->filter()->all();
        $linked = $ids === []
            ? collect()
            : Asset::query()->whereIn('id', $ids)->get()->keyBy('id');
        $lines = [];
        foreach ($accountability->items as $item) {
            $linkedAsset = $item->asset_id ? $linked->get($item->asset_id) : null;
            if ($linkedAsset instanceof Asset) {
                $lines[] = $this->assetLine($linkedAsset);

                continue;
            }
            $lines[] = [
                'quantity' => '1',
                'item' => (string) $item->label,
                'brand' => '',
                'serial_number' => '',
                'asset_tag' => '',
            ];
        }

        return $lines !== [] ? $lines : [$this->assetLine($asset)];
    }

    /**
     * @return array{quantity: string, item: string, brand: string, serial_number: string, asset_tag: string}
     */
    private function assetLine(Asset $asset): array
    {
        $product = trim((string) ($asset->product_name ?? ''));

        return [
            'quantity' => '1',
            'item' => $product !== '' ? $product : $this->typeLabel((string) $asset->asset_type),
            'brand' => (string) ($asset->manufacturer ?? ''),
            'serial_number' => (string) ($asset->serial_number ?? ''),
            'asset_tag' => (string) $asset->asset_tag,
        ];
    }

    private function typeLabel(string $type): string
    {
        return match (strtolower($type)) {
            'laptop' => 'Laptop',
            'desktop' => 'Desktop',
            'monitor' => 'Monitor',
            'dock' => 'Dock',
            'phone' => 'Phone',
            'peripheral' => 'Peripheral',
            default => $type === '' ? 'Item' : ucfirst(strtolower(str_replace('_', ' ', $type))),
        };
    }
}
