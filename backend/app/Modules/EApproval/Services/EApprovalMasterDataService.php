<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\Documents\Models\Site;
use App\Modules\EApproval\Models\EApprovalMasterDataRow;
use App\Modules\EApproval\Models\EApprovalMasterDataSet;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class EApprovalMasterDataService
{
    public function __construct(
        private readonly EApprovalAuditLogger $audit,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listSets(): array
    {
        return EApprovalMasterDataSet::query()
            ->withCount('rows')
            ->orderBy('name')
            ->get()
            ->map(static fn (EApprovalMasterDataSet $set) => [
                'id' => (string) $set->id,
                'key' => $set->key,
                'name' => $set->name,
                'status' => $set->status,
                'row_count' => (int) ($set->rows_count ?? 0),
                'config_json' => is_array($set->config_json) ? $set->config_json : null,
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
            'config_json' => $this->normalizeSetConfig($input['config_json'] ?? null),
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
            $incoming = is_array($input['config_json']) ? $input['config_json'] : null;
            if (is_array($incoming) && is_array($set->config_json)) {
                $incoming = array_merge($set->config_json, $incoming);
            }
            $set->config_json = $this->normalizeSetConfig($incoming);
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
        $label = trim((string) ($input['label'] ?? ''));
        $code = $this->blankToNull($input['code'] ?? null);
        if ($label === '') {
            throw ValidationException::withMessages([
                'label' => [__('Label is required.')],
            ]);
        }
        $this->assertUniqueRow($set, $code, $label);

        return $this->insertRow($set, $code, $label, $input);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{created: int, errors: list<array{row: int, message: string}>}
     */
    public function bulkImportRows(EApprovalMasterDataSet $set, array $rows, bool $dryRun = false): array
    {
        $created = 0;
        $errors = [];
        $seenLabels = [];
        $seenCodes = [];
        $existingLabels = [];
        $existingCodes = [];

        foreach ($set->rows()->get(['code', 'label']) as $existing) {
            $existingLabel = mb_strtolower(trim((string) $existing->label));
            if ($existingLabel !== '') {
                $existingLabels[$existingLabel] = true;
            }
            $existingCode = $this->blankToNull($existing->code);
            if ($existingCode !== null) {
                $existingCodes[mb_strtolower($existingCode)] = true;
            }
        }

        $pending = [];

        foreach ($rows as $index => $row) {
            $rowNumber = is_array($row) && isset($row['row_number']) ? (int) $row['row_number'] : $index + 1;
            if (! is_array($row)) {
                $errors[] = ['row' => $rowNumber, 'message' => __('Row :row is not a record.', ['row' => $rowNumber])];

                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            $code = $this->blankToNull($row['code'] ?? null);
            if ($label === '') {
                $errors[] = ['row' => $rowNumber, 'message' => __('Row :row needs a label.', ['row' => $rowNumber])];

                continue;
            }

            $labelKey = mb_strtolower($label);
            $codeKey = $code === null ? '' : mb_strtolower($code);
            if (isset($seenLabels[$labelKey])) {
                $errors[] = ['row' => $rowNumber, 'message' => __('Row :row repeats the label ":label".', ['row' => $rowNumber, 'label' => $label])];

                continue;
            }
            if ($codeKey !== '' && isset($seenCodes[$codeKey])) {
                $errors[] = ['row' => $rowNumber, 'message' => __('Row :row repeats the code ":code".', ['row' => $rowNumber, 'code' => $code])];

                continue;
            }

            if (isset($existingLabels[$labelKey])) {
                $errors[] = ['row' => $rowNumber, 'message' => __('Row :row: :message', [
                    'row' => $rowNumber,
                    'message' => __('A row labeled ":label" already exists.', ['label' => $label]),
                ])];

                continue;
            }
            if ($codeKey !== '' && isset($existingCodes[$codeKey])) {
                $errors[] = ['row' => $rowNumber, 'message' => __('Row :row: :message', [
                    'row' => $rowNumber,
                    'message' => __('A row with code ":code" already exists.', ['code' => $code]),
                ])];

                continue;
            }

            $seenLabels[$labelKey] = true;
            $existingLabels[$labelKey] = true;
            if ($codeKey !== '') {
                $seenCodes[$codeKey] = true;
                $existingCodes[$codeKey] = true;
            }

            if ($dryRun) {
                continue;
            }

            $pending[] = [
                'code' => $code,
                'label' => $label,
                'data' => $row['data'] ?? $row['data_json'] ?? null,
                'sort_order' => $row['sort_order'] ?? $index,
                'is_active' => $row['is_active'] ?? true,
            ];
        }

        if ($pending !== []) {
            DB::transaction(function () use ($set, $pending, &$created): void {
                foreach ($pending as $input) {
                    $this->insertRow(
                        $set,
                        $input['code'],
                        $input['label'],
                        $input,
                    );
                    $created++;
                }
            });
        }

        return ['created' => $created, 'errors' => $errors];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function insertRow(EApprovalMasterDataSet $set, ?string $code, string $label, array $input): EApprovalMasterDataRow
    {
        return EApprovalMasterDataRow::query()->create([
            'id' => (string) Str::uuid(),
            'set_id' => $set->id,
            'code' => $code,
            'label' => $label,
            'data_json' => is_array($input['data'] ?? null) ? $input['data'] : (is_array($input['data_json'] ?? null) ? $input['data_json'] : null),
            'sort_order' => (int) ($input['sort_order'] ?? 0),
            'is_active' => (bool) ($input['is_active'] ?? true),
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function updateRow(EApprovalMasterDataRow $row, array $input): EApprovalMasterDataRow
    {
        $nextCode = array_key_exists('code', $input)
            ? $this->blankToNull($input['code'])
            : $this->blankToNull($row->code);
        $nextLabel = isset($input['label']) ? trim((string) $input['label']) : trim((string) $row->label);
        if ($nextLabel === '') {
            throw ValidationException::withMessages([
                'label' => [__('Label is required.')],
            ]);
        }
        $set = $row->set;
        if (! $set instanceof EApprovalMasterDataSet) {
            throw ValidationException::withMessages([
                'set' => [__('Master data set not found.')],
            ]);
        }
        $this->assertUniqueRow($set, $nextCode, $nextLabel, (string) $row->id);
        if (array_key_exists('code', $input)) {
            $row->code = $nextCode;
        }
        if (isset($input['label'])) {
            $row->label = $nextLabel;
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

    public function deleteAllRows(EApprovalMasterDataSet $set, ?Authenticatable $actor = null): int
    {
        $count = $set->rows()->count();
        if ($count === 0) {
            return 0;
        }

        $set->rows()->delete();
        $this->audit->log(
            'master_data.rows_cleared',
            (string) $set->id,
            'Deleted '.$count.' rows from '.$set->name,
            $actor,
            ['row_count' => ['from' => $count, 'to' => 0]],
            entityType: 'e_approval_master_data_set',
            entityLabel: (string) $set->name,
        );

        return $count;
    }

    private function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function assertUniqueRow(EApprovalMasterDataSet $set, ?string $code, string $label, ?string $ignoreId = null): void
    {
        $labelKey = mb_strtolower(trim($label));
        $codeKey = $code === null ? '' : mb_strtolower($code);
        $existing = $set->rows()->get(['id', 'code', 'label']);
        foreach ($existing as $row) {
            if ($ignoreId !== null && (string) $row->id === $ignoreId) {
                continue;
            }
            if ($labelKey !== '' && mb_strtolower(trim((string) $row->label)) === $labelKey) {
                throw ValidationException::withMessages([
                    'label' => [__('A row labeled ":label" already exists.', ['label' => $label])],
                ]);
            }
            if ($codeKey !== '' && mb_strtolower(trim((string) $row->code)) === $codeKey) {
                throw ValidationException::withMessages([
                    'code' => [__('A row with code ":code" already exists.', ['code' => $code])],
                ]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function lookupByKey(string $key, ?string $query = null, int $limit = 10, ?string $subsidiary = null): array
    {
        $this->ensureFinanceCatalog($key);

        if ($this->normalizeKey($key) === 'sites' && trim((string) $subsidiary) !== '') {
            return $this->subsidiarySiteLookup((string) $subsidiary, $query, $limit);
        }

        $builtin = $this->builtinLookup($key, $query, $limit);
        if ($builtin !== null) {
            return $builtin;
        }

        $normalized = $this->normalizeKey($key);
        $set = EApprovalMasterDataSet::query()->where('key', $normalized)->where('status', 'active')->first();
        if ($set === null && in_array($normalized, ['expense_types', 'departments'], true)) {
            (new EApprovalFinanceCatalogService)->seed();
            $set = EApprovalMasterDataSet::query()->where('key', $normalized)->first();
        }
        if ($set === null) {
            $fallback = $this->fallbackFinanceOptions($normalized);
            if ($fallback !== null) {
                return $fallback;
            }

            throw ValidationException::withMessages(['key' => [__('Master data set not found.')]]);
        }

        $rows = $set->rows()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        $options = $rows->map(static function (EApprovalMasterDataRow $r) {
            return [
                'id' => (string) $r->id,
                'code' => (string) ($r->code ?? ''),
                'label' => $r->label,
                'subtitle' => null,
                'value' => (string) ($r->code ?: $r->label),
                'data' => $r->data_json,
                'sort_order' => $r->sort_order,
            ];
        })->values()->all();

        return [
            'key' => $set->key,
            'name' => $set->name,
            'status' => $set->status,
            'options' => $options,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeSetConfig(mixed $config): ?array
    {
        if (! is_array($config)) {
            return null;
        }

        $columns = $config['columns'] ?? null;
        if (! is_array($columns)) {
            return $config;
        }

        $clean = [];
        $seen = [];
        foreach ($columns as $column) {
            if (! is_array($column)) {
                continue;
            }
            $key = $this->normalizeKey(str_replace('-', '_', (string) ($column['key'] ?? '')));
            $key = str_replace('-', '_', $key);
            if ($key === '' || in_array($key, ['label', 'code', 'id', 'data'], true) || isset($seen[$key])) {
                continue;
            }
            $type = (string) ($column['type'] ?? 'text');
            if (! in_array($type, ['text', 'number', 'boolean'], true)) {
                $type = 'text';
            }
            $label = trim((string) ($column['label'] ?? $key));
            $clean[] = [
                'key' => $key,
                'label' => $label !== '' ? $label : $key,
                'type' => $type,
            ];
            $seen[$key] = true;
            if (count($clean) >= 12) {
                break;
            }
        }

        $config['columns'] = $clean;

        return $config;
    }

    private function normalizeKey(string $key): string
    {
        $key = strtolower(trim($key));
        $key = preg_replace('/[^a-z0-9_-]+/', '-', $key) ?? $key;

        return trim($key, '-');
    }

    private function ensureFinanceCatalog(string $key): void
    {
        $normalized = $this->normalizeKey($key);
        if (! in_array($normalized, ['expense_types', 'departments', 'sites'], true)) {
            return;
        }

        $missingCatalog = ! EApprovalMasterDataSet::query()->where('key', 'expense_types')->exists()
            || ! EApprovalMasterDataSet::query()->where('key', 'departments')->exists();

        if ($missingCatalog) {
            (new EApprovalFinanceCatalogService)->seed();
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function builtinLookup(string $key, ?string $query = null, int $limit = 10): ?array
    {
        return match ($this->normalizeKey($key)) {
            'sites' => $this->sitesLookup($query, $limit),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array{address: string, name: string, fields: array<string, string>}
     */
    private function siteOptionData(string $address, string $name, array $extra = []): array
    {
        $fields = [];
        $address = trim($address);
        $name = trim($name);
        if ($address !== '') {
            $fields['address'] = $address;
        }
        if ($name !== '') {
            $fields['name'] = $name;
        }
        foreach ($this->scalarMasterFields($extra) as $key => $value) {
            if (($fields[$key] ?? '') === '') {
                $fields[$key] = $value;
            }
        }

        return [
            'address' => $address,
            'name' => $name,
            'fields' => $fields,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function scalarMasterFields(array $data): array
    {
        $fields = [];
        foreach ($data as $key => $value) {
            if (! is_string($key) || $key === '' || $key === 'fields') {
                continue;
            }
            if (is_bool($value)) {
                $fields[$key] = $value ? 'Yes' : 'No';

                continue;
            }
            if (is_int($value) || is_float($value)) {
                $fields[$key] = (string) $value;

                continue;
            }
            if (is_string($value) && trim($value) !== '') {
                $fields[$key] = trim($value);
            }
        }

        return $fields;
    }

    /**
     * Extra columns stored on the Sites master data set, keyed by site ID.
     * The row label is the site ID. The row code is the place line when the
     * site record itself has no address.
     *
     * @return array<string, array<string, string>>
     */
    private function masterSiteFieldsByCode(): array
    {
        $set = EApprovalMasterDataSet::query()->where('key', 'sites')->where('status', 'active')->first();
        if ($set === null) {
            return [];
        }

        $map = [];
        foreach ($set->rows()->where('is_active', true)->get() as $row) {
            if (! $row instanceof EApprovalMasterDataRow) {
                continue;
            }
            $data = is_array($row->data_json) ? $row->data_json : [];
            $fields = $this->scalarMasterFields($data);
            $place = trim(str_replace('_', ' ', (string) ($row->code ?? '')));
            $label = trim((string) $row->label);
            if ($place !== '' && ($fields['address'] ?? '') === '' && strcasecmp($place, $label) !== 0) {
                $fields['address'] = $place;
            }

            $keys = [];
            foreach ([$label, (string) ($data['site_code'] ?? ''), (string) ($row->code ?? '')] as $raw) {
                $key = strtoupper(trim($raw));
                if ($key !== '' && ! in_array($key, $keys, true)) {
                    $keys[] = $key;
                }
            }
            foreach ($keys as $key) {
                $map[$key] = $fields;
            }
        }

        return $map;
    }

    /**
     * @return array<string, mixed>
     */
    private function sitesLookup(?string $query, int $limit): array
    {
        $limit = max(1, min(10, $limit));
        $needle = trim((string) $query);

        try {
            if (! Schema::connection('tenant')->hasTable('sites')) {
                return $this->mappedSiteLookup($needle, $limit);
            }
        } catch (QueryException) {
            return $this->mappedSiteLookup($needle, $limit);
        }

        try {
            $sites = Site::query()
                ->when($needle !== '', function ($builder) use ($needle): void {
                    $like = '%'.$needle.'%';
                    $builder->where(function ($inner) use ($like): void {
                        $inner->where('site_code', 'like', $like)
                            ->orWhere('name', 'like', $like)
                            ->orWhere('full_address', 'like', $like);
                    });
                })
                ->orderBy('site_code')
                ->limit($limit)
                ->get();
        } catch (QueryException) {
            return $this->mappedSiteLookup($needle, $limit);
        }

        $masterByCode = $this->masterSiteFieldsByCode();
        $options = $sites->map(function (Site $site) use ($masterByCode): array {
            $code = (string) $site->site_code;
            $name = trim((string) $site->name);

            return [
                'id' => (string) $site->id,
                'code' => $code,
                'label' => $name !== '' ? $code.' — '.$name : $code,
                'subtitle' => $site->full_address,
                'value' => $code,
                'data' => $this->siteOptionData(
                    (string) ($site->full_address ?? ''),
                    $name,
                    $masterByCode[strtoupper($code)] ?? [],
                ),
            ];
        })->values()->all();

        if ($options !== []) {
            return [
                'key' => 'sites',
                'name' => 'Sites',
                'status' => 'active',
                'options' => $options,
            ];
        }

        return $this->mappedSiteLookup($needle, $limit);
    }

    /**
     * Site IDs from master data `sites`. `*` lists every subsidiary.
     *
     * @return array<string, mixed>
     */
    private function subsidiarySiteLookup(string $subsidiary, ?string $query, int $limit): array
    {
        $limit = max(1, min(10, $limit));
        $subsidiary = strtoupper(trim($subsidiary));
        $needle = strtolower(trim((string) $query));
        $set = EApprovalMasterDataSet::query()->where('key', 'sites')->where('status', 'active')->first();
        $options = [];

        if ($set !== null) {
            $rows = $set->rows()->where('is_active', true);
            if ($subsidiary !== '*') {
                $rows->whereRaw(
                    "UPPER(JSON_UNQUOTE(JSON_EXTRACT(data_json, '$.subsidiary'))) = ?",
                    [$subsidiary],
                );
            }
            if ($needle !== '') {
                $like = '%'.addcslashes($needle, '%_\\').'%';
                $rows->where(function ($inner) use ($like): void {
                    $inner->whereRaw('LOWER(label) LIKE ?', [$like])
                        ->orWhereRaw("LOWER(REPLACE(COALESCE(code, ''), '_', ' ')) LIKE ?", [$like]);
                });
            }
            foreach ($rows->orderBy('label')->limit($limit)->get() as $row) {
                if (! $row instanceof EApprovalMasterDataRow) {
                    continue;
                }
                $data = is_array($row->data_json) ? $row->data_json : [];
                $siteId = trim((string) ($row->label !== '' ? $row->label : ($data['name'] ?? $row->code)));
                if ($siteId === '') {
                    continue;
                }
                $place = trim(str_replace('_', ' ', (string) ($row->code ?? '')));
                $options[] = [
                    'id' => (string) $row->id,
                    'code' => $siteId,
                    'label' => $siteId,
                    'subtitle' => $place !== '' && strcasecmp($place, $siteId) !== 0 ? $place : null,
                    'value' => $siteId,
                    'data' => $this->siteOptionData($place, trim((string) ($data['name'] ?? '')), $data),
                ];
                if (count($options) >= $limit) {
                    break;
                }
            }
        }

        return [
            'key' => 'sites',
            'name' => 'Sites',
            'status' => 'active',
            'options' => $options,
        ];
    }

    /**
     * Sites edited on E-Forms → Master data (key `sites`).
     *
     * @return array<string, mixed>
     */
    private function mappedSiteLookup(string $needle, int $limit): array
    {
        (new EApprovalFinanceCatalogService)->seed();

        $set = EApprovalMasterDataSet::query()->where('key', 'sites')->where('status', 'active')->first();
        if ($set === null) {
            return $this->sampleSiteLookup($needle, $limit);
        }

        $needle = strtolower($needle);
        $options = [];
        foreach ($set->rows()->where('is_active', true)->orderBy('sort_order')->orderBy('label')->get() as $row) {
            if (! $row instanceof EApprovalMasterDataRow) {
                continue;
            }
            $data = is_array($row->data_json) ? $row->data_json : [];
            $address = trim((string) ($data['address'] ?? ''));
            $haystack = strtolower($row->code.' '.$row->label.' '.$address);
            if ($needle !== '' && ! str_contains($haystack, $needle)) {
                continue;
            }
            $code = (string) ($row->code ?: $row->label);
            $options[] = [
                'id' => (string) $row->id,
                'code' => $code,
                'label' => (string) $row->label,
                'subtitle' => $address !== '' ? $address : null,
                'value' => $code,
                'data' => $this->siteOptionData($address, trim((string) ($data['name'] ?? '')), $data),
                'sort_order' => $row->sort_order,
            ];
            if (count($options) >= $limit) {
                break;
            }
        }

        if ($options === []) {
            return $this->sampleSiteLookup($needle, $limit);
        }

        return [
            'key' => 'sites',
            'name' => 'Sites',
            'status' => 'active',
            'options' => $options,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fallbackFinanceOptions(string $key): ?array
    {
        $rows = match ($key) {
            'expense_types' => [
                ['value' => 'land', 'label' => 'Transportation - Land', 'data' => ['vatable' => true]],
                ['value' => 'sea', 'label' => 'Transportation - Sea', 'data' => ['vatable' => true]],
                ['value' => 'air', 'label' => 'Transportation - Air', 'data' => ['vatable' => true]],
                ['value' => 'gasoline', 'label' => 'Gasoline', 'data' => ['vatable' => true]],
                ['value' => 'lodging', 'label' => 'Lodging', 'data' => ['vatable' => true]],
                ['value' => 'per_diem', 'label' => 'Per Diem', 'data' => ['vatable' => false, 'per_diem' => true]],
            ],
            'departments' => [
                ['value' => 'operations', 'label' => 'Operations', 'data' => []],
                ['value' => 'finance', 'label' => 'Finance', 'data' => []],
                ['value' => 'engineering', 'label' => 'Engineering', 'data' => []],
                ['value' => 'hr', 'label' => 'Human resources', 'data' => []],
            ],
            default => null,
        };

        if ($rows === null) {
            return null;
        }

        $options = [];
        foreach ($rows as $index => $row) {
            $options[] = [
                'id' => $row['value'],
                'code' => $row['value'],
                'label' => $row['label'],
                'subtitle' => null,
                'value' => $row['value'],
                'data' => $row['data'],
                'sort_order' => $index + 1,
            ];
        }

        return [
            'key' => $key,
            'name' => $key,
            'status' => 'active',
            'options' => $options,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleSiteLookup(string $needle, int $limit): array
    {
        $needle = strtolower($needle);
        $matches = [];
        foreach (EApprovalFinanceCatalogService::sampleSites() as $site) {
            $haystack = strtolower($site['site_code'].' '.$site['name'].' '.$site['full_address']);
            if ($needle !== '' && ! str_contains($haystack, $needle)) {
                continue;
            }
            $matches[] = [
                'id' => $site['site_code'],
                'code' => $site['site_code'],
                'label' => $site['site_code'].' — '.$site['name'],
                'subtitle' => $site['full_address'],
                'value' => $site['site_code'],
                'data' => $this->siteOptionData($site['full_address'], $site['name']),
            ];
            if (count($matches) >= $limit) {
                break;
            }
        }

        return [
            'key' => 'sites',
            'name' => 'Sites',
            'status' => 'active',
            'options' => $matches,
        ];
    }
}
