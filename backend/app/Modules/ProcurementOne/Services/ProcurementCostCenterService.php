<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Models\ProcurementCostCenter;
use Illuminate\Validation\ValidationException;

final class ProcurementCostCenterService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listActive(): array
    {
        return ProcurementCostCenter::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(fn (ProcurementCostCenter $row) => $this->asPayload($row))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): ProcurementCostCenter
    {
        $normalized = $this->validatePayload($input);

        return ProcurementCostCenter::query()->create($normalized);
    }

    public function find(string $id): ?ProcurementCostCenter
    {
        return ProcurementCostCenter::query()->find($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function asPayload(ProcurementCostCenter $center): array
    {
        return [
            'id' => (string) $center->id,
            'code' => $center->code,
            'name' => $center->name,
            'is_active' => (bool) $center->is_active,
            'notes' => $center->notes,
            'created_at' => $center->created_at?->toIso8601String(),
            'updated_at' => $center->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validatePayload(array $input): array
    {
        $code = strtoupper(trim((string) ($input['code'] ?? '')));
        if ($code === '' || ! preg_match('/^[A-Z0-9_-]+$/', $code)) {
            throw ValidationException::withMessages([
                'code' => [__('Cost center code must use uppercase letters, numbers, underscores, or hyphens.')],
            ]);
        }

        if (ProcurementCostCenter::query()->where('code', $code)->exists()) {
            throw ValidationException::withMessages([
                'code' => [__('Cost center code is already in use.')],
            ]);
        }

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => [__('Cost center name is required.')],
            ]);
        }

        return [
            'code' => $code,
            'name' => $name,
            'is_active' => (bool) ($input['is_active'] ?? true),
            'notes' => $input['notes'] ?? null,
        ];
    }
}
