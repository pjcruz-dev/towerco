<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Services;

use App\Modules\AdminOne\Support\RoleAccessMatrix;
use App\Modules\AssetManagement\Models\Asset;
use App\Modules\AssetManagement\Models\AssetFieldIndex;
use App\Modules\AssetManagement\Models\DepartmentCode;
use App\Modules\AssetManagement\Support\AssetHostnamePolicy;
use App\Modules\AssetManagement\Support\AssetStatus;
use App\Modules\AssetManagement\Support\AssetType;
use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynField;
use App\Modules\DynamicEntities\Services\DynRoleAccessService;
use App\Modules\DynamicEntities\Services\ManagedSeriesService;
use App\Modules\DynamicEntities\Services\RecordActionHistoryService;
use App\Modules\Identity\Models\TenantUser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AssetRegistryService
{
    public function __construct(
        private readonly AssetRecorder $recorder,
        private readonly AssetSchemaService $schema,
        private readonly DynRoleAccessService $access,
        private readonly RecordActionHistoryService $history,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, TenantUser $actor, bool $history = true): Asset
    {
        $asset = DB::connection('tenant')->transaction(function () use ($data, $actor): Asset {
            $data = $this->withoutLockedFields($data, $actor);
            $type = AssetType::parse((string) $data['asset_type']);
            $status = AssetStatus::parse((string) ($data['status'] ?? AssetStatus::Available->value));
            if (! in_array($status, [AssetStatus::New, AssetStatus::Available], true)) {
                throw ValidationException::withMessages([
                    'status' => [__('A new asset starts as NEW or AVAILABLE.')],
                ]);
            }

            $serial = $this->normalizeSerial((string) $data['serial_number']);
            $this->assertSerialAvailable($serial, null);
            $columns = $this->identityColumns($data);
            if (isset($columns['hostname']) && is_string($columns['hostname']) && $columns['hostname'] !== '') {
                $columns['hostname'] = AssetHostnamePolicy::assertLength($columns['hostname']);
                $this->assertHostnameAvailable($columns['hostname'], null);
            }

            $asset = Asset::query()->create([
                'id' => (string) Str::uuid(),
                'asset_tag' => $this->nextTag($type),
                'serial_number' => $serial,
                'asset_type' => $type->value,
                'status' => $status->value,
                ...$columns,
                'custom_values_json' => $this->customValues($data),
            ]);

            $this->syncFieldIndexes($asset);
            $this->recorder->event($asset, 'RECEIVED', null, 'IT Storage', $actor, [
                'reason' => 'Asset registered',
            ]);

            return $asset->refresh();
        });

        if ($history) {
            $this->history->recordAssetCreated($asset, $actor);
        }

        return $asset;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Asset $asset, array $data, TenantUser $actor, bool $history = true): Asset
    {
        $before = RecordActionHistoryService::assetSnapshot($asset);

        $updated = DB::connection('tenant')->transaction(function () use ($asset, $data, $actor): Asset {
            $data = $this->withoutLockedFields($data, $actor, $asset);
            $before = $asset->only(['serial_number', 'status', 'hostname', 'manufacturer', 'product_name']);
            if (isset($data['serial_number'])) {
                $serial = $this->normalizeSerial((string) $data['serial_number']);
                $this->assertSerialAvailable($serial, (string) $asset->id);
                $asset->serial_number = $serial;
            }
            if (isset($data['asset_type'])) {
                $asset->asset_type = AssetType::parse((string) $data['asset_type'])->value;
            }
            if (isset($data['status'])) {
                $next = AssetStatus::parse((string) $data['status']);
                AssetStatus::parse((string) $asset->status)->assertCanTransitionTo($next, (string) $asset->asset_tag);
                $asset->status = $next->value;
            }
            if (isset($data['hostname']) && $data['hostname'] !== null && $data['hostname'] !== '') {
                $hostname = AssetHostnamePolicy::assertLength((string) $data['hostname']);
                $this->assertHostnameAvailable($hostname, (string) $asset->id);
                $asset->hostname = $hostname;
            }
            foreach ($this->identityColumns($data) as $column => $value) {
                if ($column === 'hostname') {
                    continue;
                }
                $asset->{$column} = $value;
            }
            if (array_key_exists('custom_values', $data) || array_key_exists('custom_values_json', $data)) {
                $asset->custom_values_json = $this->customValues($data);
            }
            $asset->save();
            $this->syncFieldIndexes($asset);
            $this->recorder->event($asset, 'UPDATED', null, null, $actor, ['before' => $before]);

            return $asset->refresh();
        });

        if ($history) {
            $this->history->recordAssetUpdated($updated, $actor, $before);
        }

        return $updated;
    }

    public function findByTagOrId(string $key): Asset
    {
        $asset = Asset::query()
            ->where('id', $key)
            ->orWhere('asset_tag', $key)
            ->first();
        if (! $asset instanceof Asset) {
            abort(404);
        }

        return $asset;
    }

    public function normalizeSerial(string $serial): string
    {
        $normalized = strtoupper(trim($serial));
        if ($normalized === '') {
            throw ValidationException::withMessages([
                'serial_number' => [__('Serial number is required.')],
            ]);
        }

        return $normalized;
    }

    public function assertSerialAvailable(string $serial, ?string $exceptId): void
    {
        $query = Asset::query()->where('serial_number', $serial);
        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }
        $existing = $query->first();
        if ($existing instanceof Asset) {
            throw ValidationException::withMessages([
                'serial_number' => [__('Serial number :serial is already registered as :tag. Update that asset instead of creating another one.', [
                    'serial' => $serial,
                    'tag' => $existing->asset_tag,
                ])],
            ]);
        }
    }

    public function assertHostnameAvailable(string $hostname, ?string $exceptId): void
    {
        $query = Asset::query()->where('hostname', $hostname);
        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }
        $existing = $query->first();
        if ($existing instanceof Asset) {
            throw ValidationException::withMessages([
                'hostname' => [__('Computer name :name is already used by :tag. Rename is blocked.', [
                    'name' => $hostname,
                    'tag' => $existing->asset_tag,
                ])],
            ]);
        }
    }

    public function nextTag(AssetType $type): string
    {
        return app(ManagedSeriesService::class)->issueAssetTag($type);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function identityColumns(array $data): array
    {
        $columns = [];
        foreach ([
            'manufacturer', 'product_name', 'product_version', 'hostname', 'old_hostname',
            'processor', 'installed_ram', 'operating_system', 'system_type', 'bios_version', 'notes',
        ] as $column) {
            if (array_key_exists($column, $data)) {
                $value = $data[$column];
                $columns[$column] = $value === null || $value === '' ? null : (string) $value;
            }
        }
        foreach (['purchase_date', 'warranty_end', 'commission_date'] as $column) {
            if (array_key_exists($column, $data)) {
                $columns[$column] = $data[$column] === null || $data[$column] === '' ? null : $data[$column];
            }
        }

        return $columns;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    public function customValues(array $data): ?array
    {
        $values = $data['custom_values'] ?? $data['custom_values_json'] ?? null;
        if (! is_array($values)) {
            return null;
        }
        $this->schema->ensure();
        $entity = DynEntity::query()->where('slug', AssetSchemaService::SLUG)->first();
        if (! $entity instanceof DynEntity) {
            return $values;
        }
        $system = DynField::query()->where('entity_id', $entity->id)->where('is_system_field', true)->pluck('name')->all();
        foreach ($system as $name) {
            unset($values[$name]);
        }

        return $values;
    }

    public function syncFieldIndexes(Asset $asset): void
    {
        AssetFieldIndex::query()->where('asset_id', $asset->id)->delete();
        $values = $asset->custom_values_json ?? [];
        if (! is_array($values)) {
            return;
        }
        foreach ($values as $name => $value) {
            if (! is_string($name) || $value === null || $value === '') {
                continue;
            }
            $row = [
                'id' => (string) Str::uuid(),
                'asset_id' => $asset->id,
                'field_name' => $name,
                'value_string' => is_scalar($value) ? mb_substr((string) $value, 0, 512) : null,
                'value_number' => is_numeric($value) ? $value : null,
                'value_date' => $this->dateOrNull($value),
            ];
            AssetFieldIndex::query()->create($row);
        }
    }

    public function lifecycle(Asset $asset): array
    {
        $start = $asset->commission_date ?? $asset->purchase_date;
        if ($start === null) {
            return ['months' => null, 'band' => null, 'label' => 'Unknown'];
        }
        $months = (int) CarbonImmutable::parse($start)->diffInMonths(CarbonImmutable::now());
        $band = match (true) {
            $months >= 48 => 'EOL',
            $months >= 42 => 'REPLACEMENT_PLANNING',
            $months >= 36 => 'LIFECYCLE_REVIEW',
            default => 'ACTIVE',
        };
        $label = match ($band) {
            'EOL' => 'EOL',
            'REPLACEMENT_PLANNING' => 'Replacement planning',
            'LIFECYCLE_REVIEW' => 'Lifecycle review',
            default => 'Active',
        };

        return ['months' => $months, 'band' => $band, 'label' => $label];
    }

    public function departmentCodeFor(?string $department): ?DepartmentCode
    {
        if ($department === null || trim($department) === '') {
            return null;
        }

        return DepartmentCode::query()->where('department_name', trim($department))->first();
    }

    /**
     * Drop columns and custom values the role cannot fill in.
     * On update, locked custom values already stored on the asset are kept.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withoutLockedFields(array $data, TenantUser $actor, ?Asset $existing = null): array
    {
        $slug = AssetSchemaService::SLUG;
        $blocked = function (string $field) use ($actor, $slug): bool {
            return ! RoleAccessMatrix::editableOnForm($this->access->fieldLevel($actor, $slug, $field));
        };

        foreach (array_keys($data) as $key) {
            if (! is_string($key) || $key === 'custom_values' || $key === 'custom_values_json') {
                continue;
            }
            if ($blocked($key)) {
                unset($data[$key]);
            }
        }

        foreach (['custom_values', 'custom_values_json'] as $bag) {
            if (! isset($data[$bag]) || ! is_array($data[$bag])) {
                continue;
            }
            foreach (array_keys($data[$bag]) as $name) {
                if (is_string($name) && $blocked($name)) {
                    unset($data[$bag][$name]);
                }
            }
            if ($existing instanceof Asset && is_array($existing->custom_values_json)) {
                foreach ($existing->custom_values_json as $name => $value) {
                    if (is_string($name) && $blocked($name)) {
                        $data[$bag][$name] = $value;
                    }
                }
            }
        }

        return $data;
    }

    private function dateOrNull(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return null;
        }

        return substr($value, 0, 10);
    }
}
