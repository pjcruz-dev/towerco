<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Services;

use App\Modules\AssetManagement\Models\Asset;
use App\Modules\AssetManagement\Models\AssetApplication;
use App\Modules\AssetManagement\Models\AssetDisk;
use App\Modules\AssetManagement\Models\AssetInventorySubmission;
use App\Modules\AssetManagement\Models\AssetNetworkAdapter;
use App\Modules\AssetManagement\Models\AssetVideoCard;
use App\Modules\AssetManagement\Support\AssetMacAddress;
use App\Modules\AssetManagement\Support\AssetStatus;
use App\Modules\AssetManagement\Support\AssetType;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AssetInventoryService
{
    public function __construct(
        private readonly AssetRegistryService $registry,
        private readonly AssetRecorder $recorder,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function verify(array $data): array
    {
        $tag = strtoupper(trim((string) ($data['asset_tag'] ?? '')));
        $detected = $this->registry->normalizeSerial((string) ($data['detected_serial'] ?? ''));
        $asset = Asset::query()->where('asset_tag', $tag)->first();
        if (! $asset instanceof Asset) {
            throw ValidationException::withMessages([
                'asset_tag' => [__('No asset is tagged :tag.', ['tag' => $tag])],
            ]);
        }
        $expected = (string) $asset->serial_number;
        $match = $expected === $detected;
        if (! $match && ($data['block'] ?? true)) {
            throw ValidationException::withMessages([
                'serial_number' => [__('Serial mismatch. Expected :expected. Detected :detected. Inventory submission is blocked.', [
                    'expected' => $expected,
                    'detected' => $detected,
                ])],
            ]);
        }

        return [
            'asset_tag' => $asset->asset_tag,
            'expected_serial' => $expected,
            'detected_serial' => $detected,
            'serial_match' => $match,
            'asset_match' => $match,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{asset: Asset, created: bool, idempotent: bool}
     */
    public function submit(array $data, TenantUser $actor): array
    {
        $clientKey = trim((string) ($data['client_key'] ?? ''));
        if ($clientKey === '') {
            throw ValidationException::withMessages([
                'client_key' => [__('An inventory transaction id is required so a retry does not create a second asset.')],
            ]);
        }
        $existing = AssetInventorySubmission::query()->where('client_key', $clientKey)->first();
        if ($existing instanceof AssetInventorySubmission && $existing->asset_id !== null) {
            return [
                'asset' => Asset::query()->findOrFail($existing->asset_id),
                'created' => false,
                'idempotent' => true,
            ];
        }

        if (! empty($data['asset_tag'])) {
            $this->verify([
                'asset_tag' => $data['asset_tag'],
                'detected_serial' => $data['serial_number'] ?? '',
                'block' => true,
            ]);
        }

        return DB::connection('tenant')->transaction(function () use ($data, $actor, $clientKey): array {
            $serial = $this->registry->normalizeSerial((string) $data['serial_number']);
            $asset = Asset::query()->where('serial_number', $serial)->lockForUpdate()->first();
            $created = false;
            if (! $asset instanceof Asset) {
                $asset = $this->registry->create([
                    'asset_type' => $data['asset_type'] ?? AssetType::Laptop->value,
                    'status' => AssetStatus::Available->value,
                    'serial_number' => $serial,
                    'manufacturer' => $data['manufacturer'] ?? null,
                    'product_name' => $data['product_name'] ?? null,
                    'product_version' => $data['product_version'] ?? null,
                    'hostname' => $data['hostname'] ?? null,
                    'processor' => $data['processor'] ?? null,
                    'installed_ram' => $data['installed_ram'] ?? null,
                    'operating_system' => $data['operating_system'] ?? null,
                    'system_type' => $data['system_type'] ?? null,
                    'bios_version' => $data['bios_version'] ?? null,
                ], $actor);
                $created = true;
            } else {
                $asset->fill(array_filter([
                    'manufacturer' => $data['manufacturer'] ?? null,
                    'product_name' => $data['product_name'] ?? null,
                    'product_version' => $data['product_version'] ?? null,
                    'processor' => $data['processor'] ?? null,
                    'installed_ram' => $data['installed_ram'] ?? null,
                    'operating_system' => $data['operating_system'] ?? null,
                    'system_type' => $data['system_type'] ?? null,
                    'bios_version' => $data['bios_version'] ?? null,
                ], static fn ($value): bool => $value !== null && $value !== ''));
                if (! empty($data['hostname']) && $data['hostname'] !== $asset->hostname) {
                    $asset->old_hostname = $asset->hostname;
                }
                $asset->save();
            }

            $this->replaceChildren($asset, $data);
            AssetInventorySubmission::query()->create([
                'id' => (string) Str::uuid(),
                'client_key' => $clientKey,
                'asset_id' => $asset->id,
                'windows_identity' => $data['windows_identity'] ?? null,
                'matched_user_id' => $data['matched_user_id'] ?? null,
                'submitted_by' => $actor->id,
                'payload_json' => $data,
            ]);
            $this->recorder->event($asset, 'INVENTORY_SCAN', null, (string) ($data['windows_identity'] ?? 'scanner'), $actor, [
                'client_key' => $clientKey,
            ]);

            return ['asset' => $asset->refresh(), 'created' => $created, 'idempotent' => false];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function replaceChildren(Asset $asset, array $data): void
    {
        $now = now();
        $asset->networkAdapters()->delete();
        foreach ($data['network_adapters'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            AssetNetworkAdapter::query()->create([
                'id' => (string) Str::uuid(),
                'asset_id' => $asset->id,
                'adapter_name' => $row['adapter_name'] ?? null,
                'adapter_type' => $row['adapter_type'] ?? null,
                'mac_address' => AssetMacAddress::normalize(isset($row['mac_address']) ? (string) $row['mac_address'] : null),
                'ip_address' => $row['ip_address'] ?? null,
                'is_active' => (bool) ($row['is_active'] ?? true),
                'last_detected' => $now,
            ]);
        }
        $asset->disks()->delete();
        foreach ($data['disks'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            AssetDisk::query()->create([
                'id' => (string) Str::uuid(),
                'asset_id' => $asset->id,
                'model' => $row['model'] ?? null,
                'serial_number' => $row['serial_number'] ?? null,
                'size_label' => $row['size_label'] ?? null,
                'interface' => $row['interface'] ?? null,
                'last_detected' => $now,
            ]);
        }
        $asset->videoCards()->delete();
        foreach ($data['video_cards'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            AssetVideoCard::query()->create([
                'id' => (string) Str::uuid(),
                'asset_id' => $asset->id,
                'name' => (string) ($row['name'] ?? 'Video'),
                'driver_version' => $row['driver_version'] ?? null,
                'last_detected' => $now,
            ]);
        }
        $asset->applications()->delete();
        foreach ($data['applications'] ?? [] as $row) {
            if (! is_array($row) || trim((string) ($row['application_name'] ?? '')) === '') {
                continue;
            }
            AssetApplication::query()->create([
                'id' => (string) Str::uuid(),
                'asset_id' => $asset->id,
                'application_name' => (string) $row['application_name'],
                'version' => $row['version'] ?? null,
                'publisher' => $row['publisher'] ?? null,
                'installed_date' => $row['installed_date'] ?? null,
                'last_detected' => $now,
            ]);
        }
    }
}
