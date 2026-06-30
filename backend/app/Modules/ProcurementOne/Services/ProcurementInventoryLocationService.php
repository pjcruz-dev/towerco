<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Models\ProcurementInventoryLocation;
use App\Modules\ProcurementOne\Support\ProcurementInventoryLocationKind;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProcurementInventoryLocationService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listActive(?string $kind = null): array
    {
        $query = ProcurementInventoryLocation::query()
            ->where('is_active', true)
            ->orderBy('name');

        if ($kind !== null && $kind !== '' && $kind !== 'all') {
            $query->where('location_kind', $kind);
        }

        return $query->get()->map(fn (ProcurementInventoryLocation $row) => $this->asPayload($row))->all();
    }

    public function find(string $id): ?ProcurementInventoryLocation
    {
        return ProcurementInventoryLocation::query()->find($id);
    }

    public function defaultReceiptLocation(): ?ProcurementInventoryLocation
    {
        $policy = app(ProcurementInventoryPolicyService::class)->policy();
        if ($policy['default_receipt_location_id'] !== null) {
            $configured = $this->find($policy['default_receipt_location_id']);
            if ($configured instanceof ProcurementInventoryLocation && $configured->is_active) {
                return $configured;
            }
        }

        return ProcurementInventoryLocation::query()
            ->where('is_active', true)
            ->where('is_default_receipt', true)
            ->orderBy('name')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input, TenantUser $actor): ProcurementInventoryLocation
    {
        return DB::connection('tenant')->transaction(function () use ($input): ProcurementInventoryLocation {
            $normalized = $this->validatePayload($input);
            if ($normalized['is_default_receipt']) {
                $this->clearDefaultReceiptFlags();
            }

            return ProcurementInventoryLocation::query()->create($normalized);
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(ProcurementInventoryLocation $location, array $input): ProcurementInventoryLocation
    {
        return DB::connection('tenant')->transaction(function () use ($location, $input): ProcurementInventoryLocation {
            $normalized = $this->validatePayload($input, (string) $location->id);
            if ($normalized['is_default_receipt']) {
                $this->clearDefaultReceiptFlags((string) $location->id);
            }

            $location->fill($normalized);
            $location->save();

            return $location->refresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function asPayload(ProcurementInventoryLocation $location): array
    {
        return [
            'id' => (string) $location->id,
            'code' => $location->code,
            'name' => $location->name,
            'location_kind' => $location->location_kind,
            'location_kind_label' => ProcurementInventoryLocationKind::label((string) $location->location_kind),
            'site_id' => $location->site_id,
            'is_default_receipt' => (bool) $location->is_default_receipt,
            'is_active' => (bool) $location->is_active,
            'notes' => $location->notes,
            'created_at' => $location->created_at?->toIso8601String(),
            'updated_at' => $location->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validatePayload(array $input, ?string $ignoreId = null): array
    {
        $code = strtoupper(trim((string) ($input['code'] ?? '')));
        if ($code === '' || ! preg_match('/^[A-Z0-9_-]+$/', $code)) {
            throw ValidationException::withMessages([
                'code' => [__('Location code must use uppercase letters, numbers, underscores, or hyphens.')],
            ]);
        }

        $exists = ProcurementInventoryLocation::query()
            ->where('code', $code)
            ->when($ignoreId !== null, static fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'code' => [__('Location code is already in use.')],
            ]);
        }

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => [__('Location name is required.')],
            ]);
        }

        $kind = (string) ($input['location_kind'] ?? ProcurementInventoryLocationKind::WAREHOUSE);
        if (! ProcurementInventoryLocationKind::isValid($kind)) {
            throw ValidationException::withMessages([
                'location_kind' => [__('Location kind is invalid.')],
            ]);
        }

        return [
            'code' => $code,
            'name' => $name,
            'location_kind' => $kind,
            'site_id' => $input['site_id'] ?? null,
            'is_default_receipt' => (bool) ($input['is_default_receipt'] ?? false),
            'is_active' => (bool) ($input['is_active'] ?? true),
            'notes' => $input['notes'] ?? null,
        ];
    }

    private function clearDefaultReceiptFlags(?string $exceptId = null): void
    {
        ProcurementInventoryLocation::query()
            ->when($exceptId !== null, static fn ($q) => $q->where('id', '!=', $exceptId))
            ->update(['is_default_receipt' => false]);
    }
}
