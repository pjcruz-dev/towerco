<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalMasterDataRow;
use App\Modules\EApproval\Models\EApprovalMasterDataSet;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class EApprovalMasterDataService
{
    public function __construct(
        private readonly EApprovalVendorMasterDataMapper $vendorMasterDataMapper,
    ) {}
    /**
     * @return list<array<string, mixed>>
     */
    public function listSets(): array
    {
        return EApprovalMasterDataSet::query()
            ->orderBy('name')
            ->get()
            ->map(static fn (EApprovalMasterDataSet $set) => [
                'id' => (string) $set->id,
                'key' => $set->key,
                'name' => $set->name,
                'status' => $set->status,
                'row_count' => $set->rows()->count(),
                'updated_at' => $set->updated_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function createSet(array $input): EApprovalMasterDataSet
    {
        $key = $this->normalizeKey((string) ($input['key'] ?? ''));
        if ($key === '') {
            throw ValidationException::withMessages(['key' => [__('Key is required.')]]);
        }

        if (EApprovalMasterDataSet::query()->where('key', $key)->exists()) {
            throw ValidationException::withMessages(['key' => [__('This key already exists.')]]);
        }

        return EApprovalMasterDataSet::query()->create([
            'id' => (string) Str::uuid(),
            'key' => $key,
            'name' => trim((string) ($input['name'] ?? $key)),
            'status' => (string) ($input['status'] ?? 'active'),
            'config_json' => is_array($input['config_json'] ?? null) ? $input['config_json'] : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function updateSet(EApprovalMasterDataSet $set, array $input): EApprovalMasterDataSet
    {
        if (isset($input['name'])) {
            $set->name = trim((string) $input['name']);
        }
        if (isset($input['status'])) {
            $set->status = (string) $input['status'];
        }
        if (array_key_exists('config_json', $input)) {
            $set->config_json = is_array($input['config_json']) ? $input['config_json'] : null;
        }
        $set->save();

        return $set;
    }

    public function deleteSet(EApprovalMasterDataSet $set): void
    {
        $set->delete();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRows(EApprovalMasterDataSet $set): array
    {
        return $set->rows()
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->map(static fn (EApprovalMasterDataRow $row) => [
                'id' => (string) $row->id,
                'code' => $row->code,
                'label' => $row->label,
                'data' => $row->data_json,
                'sort_order' => $row->sort_order,
                'is_active' => $row->is_active,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function createRow(EApprovalMasterDataSet $set, array $input): EApprovalMasterDataRow
    {
        return EApprovalMasterDataRow::query()->create([
            'id' => (string) Str::uuid(),
            'set_id' => $set->id,
            'code' => isset($input['code']) ? trim((string) $input['code']) : null,
            'label' => trim((string) ($input['label'] ?? '')),
            'data_json' => is_array($input['data'] ?? null) ? $input['data'] : (is_array($input['data_json'] ?? null) ? $input['data_json'] : null),
            'sort_order' => (int) ($input['sort_order'] ?? 0),
            'is_active' => (bool) ($input['is_active'] ?? true),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{created: int}
     */
    public function bulkImportRows(EApprovalMasterDataSet $set, array $rows): array
    {
        $created = 0;
        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $this->createRow($set, [
                'code' => $row['code'] ?? null,
                'label' => $row['label'] ?? $row['code'] ?? ('Row '.($index + 1)),
                'data' => $row['data'] ?? $row['data_json'] ?? null,
                'sort_order' => $row['sort_order'] ?? $index,
                'is_active' => $row['is_active'] ?? true,
            ]);
            $created++;
        }

        return ['created' => $created];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function updateRow(EApprovalMasterDataRow $row, array $input): EApprovalMasterDataRow
    {
        if (array_key_exists('code', $input)) {
            $row->code = $input['code'] !== null ? trim((string) $input['code']) : null;
        }
        if (isset($input['label'])) {
            $row->label = trim((string) $input['label']);
        }
        if (array_key_exists('data', $input) || array_key_exists('data_json', $input)) {
            $row->data_json = is_array($input['data'] ?? $input['data_json'] ?? null)
                ? ($input['data'] ?? $input['data_json'])
                : null;
        }
        if (isset($input['sort_order'])) {
            $row->sort_order = (int) $input['sort_order'];
        }
        if (isset($input['is_active'])) {
            $row->is_active = (bool) $input['is_active'];
        }
        $row->save();

        return $row;
    }

    public function deleteRow(EApprovalMasterDataRow $row): void
    {
        $row->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function lookupByKey(string $key): array
    {
        $set = EApprovalMasterDataSet::query()->where('key', $key)->where('status', 'active')->first();
        if ($set === null) {
            throw ValidationException::withMessages(['key' => [__('Master data set not found.')]]);
        }

        $rows = $set->rows()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        return [
            'key' => $set->key,
            'name' => $set->name,
            'status' => $set->status,
            'options' => $rows->map(function (EApprovalMasterDataRow $r) use ($key) {
                $data = is_array($r->data_json) ? $r->data_json : [];

                return [
                    'id' => (string) $r->id,
                    'code' => (string) ($r->code ?? ''),
                    'label' => $r->label,
                    'subtitle' => $key === EApprovalVendorRegistrationMasterDataService::VENDORS_SET_KEY
                        ? $this->vendorMasterDataMapper->lookupSubtitle($data)
                        : null,
                    'value' => (string) ($r->code ?: $r->label),
                    'data' => $r->data_json,
                    'sort_order' => $r->sort_order,
                ];
            })->values()->all(),
        ];
    }

    private function normalizeKey(string $key): string
    {
        $key = strtolower(trim($key));
        $key = preg_replace('/[^a-z0-9_-]+/', '-', $key) ?? $key;

        return trim($key, '-');
    }
}
