<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Rollout\Models\RolloutGeographyLookup;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\TenantPublicHoliday;
use App\Modules\Rollout\Support\PhilippinesRolloutGeographyCatalog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class RolloutGeographyLookupService
{
    /**
     * @return list<array{id: string, kind: string, code: string, label: string, sort_order: int, is_active: bool}>
     */
    public function list(?string $kind = null, bool $activeOnly = false): array
    {
        if (! Schema::connection('tenant')->hasTable('rollout_geography_lookups')) {
            return [];
        }

        $query = RolloutGeographyLookup::query()
            ->orderBy('kind')
            ->orderBy('sort_order')
            ->orderBy('code');

        if ($kind !== null && $kind !== '') {
            $query->where('kind', $kind);
        }

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->get()
            ->map(fn (RolloutGeographyLookup $row) => $this->present($row))
            ->values()
            ->all();
    }

    /**
     * @param  array{kind: string, code: string, label: string, sort_order?: int|null, is_active?: bool|null}  $data
     */
    public function create(array $data): RolloutGeographyLookup
    {
        $kind = $this->normalizeKind((string) $data['kind']);
        $code = $this->normalizeCode((string) $data['code']);

        try {
            /** @var RolloutGeographyLookup $row */
            $row = RolloutGeographyLookup::query()->create([
                'kind' => $kind,
                'code' => $code,
                'label' => trim((string) $data['label']),
                'sort_order' => (int) ($data['sort_order'] ?? $this->nextSortOrder($kind)),
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            ]);
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'Duplicate') || str_contains($exception->getMessage(), 'UNIQUE')) {
                throw ValidationException::withMessages([
                    'code' => [__('A lookup with this code already exists for this type.')],
                ]);
            }

            throw $exception;
        }

        return $row;
    }

    /**
     * @param  array{code?: string, label?: string, sort_order?: int|null, is_active?: bool|null}  $data
     */
    public function update(RolloutGeographyLookup $row, array $data): RolloutGeographyLookup
    {
        if (array_key_exists('code', $data) && $data['code'] !== null) {
            $row->code = $this->normalizeCode((string) $data['code']);
        }

        if (array_key_exists('label', $data) && $data['label'] !== null) {
            $row->label = trim((string) $data['label']);
        }

        if (array_key_exists('sort_order', $data) && $data['sort_order'] !== null) {
            $row->sort_order = (int) $data['sort_order'];
        }

        if (array_key_exists('is_active', $data)) {
            $row->is_active = (bool) $data['is_active'];
        }

        try {
            $row->save();
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'Duplicate') || str_contains($exception->getMessage(), 'UNIQUE')) {
                throw ValidationException::withMessages([
                    'code' => [__('A lookup with this code already exists for this type.')],
                ]);
            }

            throw $exception;
        }

        return $row->fresh() ?? $row;
    }

    public function delete(RolloutGeographyLookup $row): void
    {
        if ($this->isCodeInUse($row)) {
            throw ValidationException::withMessages([
                'code' => [__('This code is in use on rollouts or holidays. Deactivate it instead of deleting.')],
            ]);
        }

        $row->delete();
    }

    public function isCodeInUse(RolloutGeographyLookup $row): bool
    {
        $code = strtoupper(trim((string) $row->code));
        if ($code === '') {
            return false;
        }

        if ($row->kind === RolloutGeographyLookup::KIND_TERRITORY) {
            $onRollouts = Schema::connection('tenant')->hasTable('rollout_programs')
                && RolloutProgram::query()->whereRaw('UPPER(territory) = ?', [$code])->exists();

            $onHolidays = Schema::connection('tenant')->hasTable('tenant_public_holidays')
                && TenantPublicHoliday::query()->whereRaw('UPPER(region) = ?', [$code])->exists();

            return $onRollouts || $onHolidays;
        }

        return Schema::connection('tenant')->hasTable('rollout_programs')
            && RolloutProgram::query()->whereRaw('UPPER(region) = ?', [$code])->exists();
    }

    /**
     * Upsert catalog defaults without wiping tenant customizations (create missing only).
     *
     * @return array{created: int, total: int}
     */
    public function seedDefaults(): array
    {
        if (! Schema::connection('tenant')->hasTable('rollout_geography_lookups')) {
            throw ValidationException::withMessages([
                'geography' => [__('Geography lookup table is not available. Run tenant migrations first.')],
            ]);
        }

        $created = 0;
        foreach (PhilippinesRolloutGeographyCatalog::defaults() as $entry) {
            $exists = RolloutGeographyLookup::query()
                ->where('kind', $entry['kind'])
                ->where('code', $entry['code'])
                ->exists();

            if ($exists) {
                continue;
            }

            RolloutGeographyLookup::query()->create([
                'kind' => $entry['kind'],
                'code' => $entry['code'],
                'label' => $entry['label'],
                'sort_order' => $entry['sort_order'],
                'is_active' => true,
            ]);
            $created++;
        }

        return [
            'created' => $created,
            'total' => count($this->list()),
        ];
    }

    /**
     * @return array{id: string, kind: string, code: string, label: string, sort_order: int, is_active: bool}
     */
    public function present(RolloutGeographyLookup $row): array
    {
        return [
            'id' => (string) $row->id,
            'kind' => (string) $row->kind,
            'code' => (string) $row->code,
            'label' => (string) $row->label,
            'sort_order' => (int) $row->sort_order,
            'is_active' => (bool) $row->is_active,
        ];
    }

    private function normalizeKind(string $kind): string
    {
        $normalized = strtolower(trim($kind));
        if (! in_array($normalized, [RolloutGeographyLookup::KIND_REGION, RolloutGeographyLookup::KIND_TERRITORY], true)) {
            throw ValidationException::withMessages([
                'kind' => [__('Kind must be region or territory.')],
            ]);
        }

        return $normalized;
    }

    private function normalizeCode(string $code): string
    {
        $trimmed = strtoupper(trim($code));
        if ($trimmed === '') {
            throw ValidationException::withMessages([
                'code' => [__('Code is required.')],
            ]);
        }

        return $trimmed;
    }

    private function nextSortOrder(string $kind): int
    {
        $max = (int) RolloutGeographyLookup::query()->where('kind', $kind)->max('sort_order');

        return $max + 1;
    }
}
