<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Services;

use App\Core\Support\ExcelWorkbookReader;
use App\Core\Support\ExcelWorkbookWriter;
use App\Modules\AiAssistant\Services\AssistantSchemaMapper;
use App\Modules\AssetManagement\Models\Asset;
use App\Modules\AssetManagement\Support\AssetStatus;
use App\Modules\AssetManagement\Support\AssetType;
use App\Modules\DynamicEntities\Services\RecordActionHistoryService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Validation\ValidationException;

final class AssetImportExportService
{
    /** @var list<string> */
    public const HEADERS = [
        'Asset Tag',
        'Serial Number',
        'Manufacturer',
        'Product Name',
        'Product Version',
        'Computer Name',
        'Employee Email',
        'Department',
        'Status',
        'Type',
        'Purchase Date',
        'Warranty End',
        'RAM',
        'Processor',
        'Operating System',
        'MAC Address',
    ];

    public function __construct(
        private readonly AssetRegistryService $registry,
        private readonly AssetWorkflowService $workflow,
        private readonly ExcelWorkbookReader $reader,
        private readonly AssetSchemaService $schema,
        private readonly RecordActionHistoryService $history,
    ) {}

    public function templateBinary(): string
    {
        $book = new ExcelWorkbookWriter;
        $book->addSheet('Assets', [self::HEADERS]);

        return $book->toBinaryString();
    }

    /**
     * @return array{total: int, valid: int, warnings: int, errors: int, rows: list<array<string, mixed>>}
     */
    public function preview(string $filename, string $contents): array
    {
        $rows = $this->parse($filename, $contents);
        $report = [];
        $valid = 0;
        $warnings = 0;
        $errors = 0;
        $seen = [];
        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $issues = [];
            $level = 'valid';
            $serial = strtoupper(trim((string) ($row['Serial Number'] ?? '')));
            if ($serial === '') {
                $issues[] = 'Serial number is missing.';
                $level = 'error';
            } elseif (isset($seen[$serial]) || Asset::query()->where('serial_number', $serial)->exists()) {
                $issues[] = 'Duplicate serial number.';
                $level = 'error';
            }
            $seen[$serial] = true;
            $status = strtoupper(trim((string) ($row['Status'] ?? 'AVAILABLE')));
            if ($status !== '' && ! in_array($status, AssetStatus::values(), true)) {
                $issues[] = 'Invalid asset status.';
                $level = 'error';
            }
            $type = strtolower(trim((string) ($row['Type'] ?? 'laptop')));
            if (! in_array($type, AssetType::values(), true)) {
                $issues[] = 'Invalid asset type.';
                $level = 'error';
            }
            $email = strtolower(trim((string) ($row['Employee Email'] ?? '')));
            if ($email !== '' && TenantUser::findByEmail($email) === null) {
                $issues[] = 'Employee email was not found. The asset will import without an assignment.';
                if ($level !== 'error') {
                    $level = 'warning';
                }
            }
            if ($level === 'valid') {
                $valid++;
            } elseif ($level === 'warning') {
                $warnings++;
                $valid++;
            } else {
                $errors++;
            }
            $report[] = [
                'line' => $line,
                'level' => $level,
                'issues' => $issues,
                'values' => $row,
            ];
        }

        return [
            'total' => count($rows),
            'valid' => $valid,
            'warnings' => $warnings,
            'errors' => $errors,
            'rows' => $report,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{imported: int, skipped: int}
     */
    public function import(array $rows, bool $confirmed, bool $partial, TenantUser $actor): array
    {
        if (! $confirmed) {
            throw ValidationException::withMessages([
                'confirm' => [__('Preview the file, then confirm the import.')],
            ]);
        }
        $preview = $this->preview('import.csv', $this->rowsToCsv($rows));
        if ($preview['errors'] > 0 && ! $partial) {
            throw ValidationException::withMessages([
                'import' => [__('The file has :count error(s). Fix them, or confirm a partial import of the valid rows.', ['count' => $preview['errors']])],
            ]);
        }
        $imported = 0;
        $skipped = 0;
        $createdIds = [];
        foreach ($preview['rows'] as $row) {
            if ($row['level'] === 'error') {
                $skipped++;

                continue;
            }
            /** @var array<string, mixed> $values */
            $values = $row['values'];
            $asset = $this->registry->create([
                'serial_number' => $values['Serial Number'] ?? '',
                'asset_type' => $values['Type'] ?? 'laptop',
                'status' => in_array(strtoupper((string) ($values['Status'] ?? 'AVAILABLE')), [AssetStatus::New->value, AssetStatus::Available->value], true)
                    ? strtoupper((string) ($values['Status'] ?? 'AVAILABLE'))
                    : AssetStatus::Available->value,
                'manufacturer' => $values['Manufacturer'] ?? null,
                'product_name' => $values['Product Name'] ?? null,
                'product_version' => $values['Product Version'] ?? null,
                'hostname' => $values['Computer Name'] ?? null,
                'purchase_date' => $values['Purchase Date'] ?? null,
                'warranty_end' => $values['Warranty End'] ?? null,
                'installed_ram' => $values['RAM'] ?? null,
                'processor' => $values['Processor'] ?? null,
                'operating_system' => $values['Operating System'] ?? null,
            ], $actor, false);
            $createdIds[] = (string) $asset->id;
            $email = strtolower(trim((string) ($values['Employee Email'] ?? '')));
            $employee = $email !== '' ? TenantUser::findByEmail($email) : null;
            if ($employee instanceof TenantUser && $asset->status === AssetStatus::Available->value) {
                $this->workflow->assign($asset, $employee, $actor);
            }
            $imported++;
        }

        $this->history->recordImport('assets', 'it_asset', 'Assets', $actor, $createdIds, []);

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    public function csvTemplate(): string
    {
        $headers = array_map(
            static fn (array $field): string => $field['label'] !== '' ? $field['label'] : $field['name'],
            $this->importFields(),
        );

        return $this->csvFromRows([$headers]);
    }

    /**
     * @return array{
     *   headers: list<string>,
     *   suggested_map: array<string, string>,
     *   fields: list<array{name: string, label: string, type: string, is_required: bool, options: list<string>, target_entity_id: null}>,
     *   sample_rows: list<array<string, string>>,
     *   row_count: int
     * }
     */
    public function analyze(string $filename, string $contents, ?TenantUser $actor = null): array
    {
        $parsed = $this->parse($filename, $contents);
        $headers = $parsed === [] ? $this->headerLine($filename, $contents) : array_keys($parsed[0]);
        $fields = $this->importFields();
        $suggested = [];
        $used = [];
        foreach ($headers as $header) {
            $match = $this->suggestField($header, $fields);
            if ($match !== '__ignore__' && isset($used[$match])) {
                $match = '__ignore__';
            }
            if ($match !== '__ignore__') {
                $used[$match] = true;
            }
            $suggested[$header] = $match;
        }
        $samples = [];
        foreach (array_slice($parsed, 0, 5) as $row) {
            $sample = [];
            foreach ($headers as $header) {
                $sample[$header] = (string) ($row[$header] ?? '');
            }
            $samples[] = $sample;
        }

        if ($actor instanceof TenantUser) {
            $suggested = app(AssistantSchemaMapper::class)
                ->fillColumnMap($actor, $headers, $fields, $suggested, $samples);
        }

        return [
            'headers' => $headers,
            'suggested_map' => $suggested,
            'fields' => $fields,
            'sample_rows' => $samples,
            'row_count' => count($parsed),
        ];
    }

    /**
     * @param  array<string, string>  $columnMap
     * @return array{
     *   dry_run: bool,
     *   created: int,
     *   updated: int,
     *   skipped: int,
     *   would_create: int,
     *   would_update: int,
     *   would_skip: int,
     *   valid_rows: int,
     *   error_rows: int,
     *   errors: list<array{row: int, message: string}>
     * }
     */
    public function importMapped(
        string $filename,
        string $contents,
        array $columnMap,
        ?string $upsertField,
        bool $dryRun,
        TenantUser $actor,
    ): array {
        $rows = $this->parse($filename, $contents);
        if (count($rows) > 2000) {
            throw ValidationException::withMessages([
                'file' => [__('Import supports up to 2,000 data rows per file.')],
            ]);
        }
        $fields = $this->importFields();
        $byName = [];
        foreach ($fields as $field) {
            $byName[$field['name']] = $field;
        }
        $map = [];
        foreach ($columnMap as $header => $target) {
            $target = (string) $target;
            if ($target === '' || $target === '__ignore__') {
                $map[(string) $header] = '__ignore__';

                continue;
            }
            if (! isset($byName[$target])) {
                throw ValidationException::withMessages([
                    'column_map' => [__('Unknown asset field “:field”.', ['field' => $target])],
                ]);
            }
            $map[(string) $header] = $target;
        }
        if ($upsertField !== null && $upsertField !== '' && ! isset($byName[$upsertField])) {
            throw ValidationException::withMessages([
                'upsert_field' => [__('Choose a mapped asset field for updates.')],
            ]);
        }
        if ($upsertField === '') {
            $upsertField = null;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $createdIds = [];
        $updateSnapshots = [];
        $wouldCreate = 0;
        $wouldUpdate = 0;
        $wouldSkip = 0;
        $errors = [];
        $seen = [];
        /** @var array<string, true> $stagedSerials Serials an earlier row in this file will create. */
        $stagedSerials = [];

        foreach ($rows as $offset => $row) {
            $line = $offset + 2;
            try {
                $payload = $this->payloadFromRow($row, $map, $byName);
                if ($payload === []) {
                    $dryRun ? $wouldSkip++ : $skipped++;

                    continue;
                }
                $target = $this->resolveImportAsset($upsertField, $payload, $stagedSerials);
                $existing = $target instanceof Asset ? $target : null;
                $updating = $existing instanceof Asset || $target === 'staged';
                $this->assertImportPayload($payload, $updating, $seen);
                $serial = strtoupper(trim((string) ($payload['serial_number'] ?? '')));
                if ($serial !== '' && ! $updating) {
                    $stagedSerials[$serial] = true;
                }
                $key = $this->dedupeKey($upsertField, $payload);
                if ($key !== null) {
                    $seen[$key] = true;
                }
                if ($dryRun) {
                    $updating ? $wouldUpdate++ : $wouldCreate++;

                    continue;
                }
                if ($existing instanceof Asset) {
                    $before = RecordActionHistoryService::assetSnapshot($existing);
                    $saved = $this->writeUpdate($existing, $payload, $actor);
                    $updateSnapshots[] = [
                        'id' => (string) $saved->id,
                        'before' => $before,
                        'after' => RecordActionHistoryService::assetSnapshot($saved),
                    ];
                    $updated++;
                } else {
                    $saved = $this->writeCreate($payload, $actor);
                    $createdIds[] = (string) $saved->id;
                    $created++;
                }
            } catch (ValidationException $exception) {
                $message = collect($exception->errors())->flatten()->first() ?: 'Validation failed.';
                $errors[] = ['row' => $line, 'message' => (string) $message];
                $dryRun ? $wouldSkip++ : $skipped++;
            }
        }

        $valid = $dryRun ? ($wouldCreate + $wouldUpdate) : ($created + $updated);

        if (! $dryRun) {
            $this->history->recordImport('assets', 'it_asset', 'Assets', $actor, $createdIds, $updateSnapshots);
        }

        return [
            'dry_run' => $dryRun,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'would_create' => $wouldCreate,
            'would_update' => $wouldUpdate,
            'would_skip' => $wouldSkip,
            'valid_rows' => $valid,
            'error_rows' => count($errors),
            'errors' => array_slice($errors, 0, 50),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportBinary(array $filters, string $format): string
    {
        $assets = Asset::query()->with(['assignee', 'networkAdapters'])->orderBy('asset_tag');
        $ids = collect(explode(',', (string) ($filters['ids'] ?? '')))
            ->map(static fn (string $id): string => trim($id))
            ->filter()
            ->take(500)
            ->values();
        if ($ids->isNotEmpty()) {
            $assets->whereIn('id', $ids->all());
        }
        if (! empty($filters['status'])) {
            $assets->where('status', $filters['status']);
        }
        if (! empty($filters['asset_type'])) {
            $assets->where('asset_type', $filters['asset_type']);
        }
        if (! empty($filters['department'])) {
            $department = (string) $filters['department'];
            $assets->whereHas('assignee', fn ($query) => $query->where('department', $department));
        }
        $rows = [self::HEADERS];
        foreach ($assets->get() as $asset) {
            $rows[] = [
                $asset->asset_tag,
                $asset->serial_number,
                $asset->manufacturer,
                $asset->product_name,
                $asset->product_version,
                $asset->hostname,
                $asset->assignee?->email,
                $asset->assignee?->department,
                $asset->status,
                $asset->asset_type,
                $asset->purchase_date?->toDateString(),
                $asset->warranty_end?->toDateString(),
                $asset->installed_ram,
                $asset->processor,
                $asset->operating_system,
                $asset->networkAdapters->first()?->mac_address,
            ];
        }
        if ($format === 'csv') {
            $handle = fopen('php://temp', 'r+');
            if ($handle === false) {
                throw ValidationException::withMessages(['export' => [__('Could not build the export.')]]);
            }
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            rewind($handle);
            $csv = stream_get_contents($handle);
            fclose($handle);

            return is_string($csv) ? $csv : '';
        }
        $book = new ExcelWorkbookWriter;
        $book->addSheet('Assets', $rows);

        return $book->toBinaryString();
    }

    /**
     * @return list<array<string, string>>
     */
    private function parse(string $filename, string $contents): array
    {
        $lower = strtolower($filename);
        if (str_ends_with($lower, '.xlsx')) {
            $grid = $this->reader->readFirstSheet($contents);
        } else {
            $grid = $this->csvGrid($contents);
        }
        if ($grid === []) {
            return [];
        }
        $headers = array_map(static fn ($header): string => trim((string) $header), $grid[0]);
        $rows = [];
        foreach (array_slice($grid, 1) as $line) {
            if (implode('', $line) === '') {
                continue;
            }
            $assoc = [];
            foreach ($headers as $index => $header) {
                $assoc[$header] = trim((string) ($line[$index] ?? ''));
            }
            $rows[] = $assoc;
        }

        return $rows;
    }

    /**
     * @return list<list<string>>
     */
    private function csvGrid(string $contents): array
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return [];
        }
        fwrite($handle, $contents);
        rewind($handle);
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = array_map(static fn ($value): string => (string) $value, $row);
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function rowsToCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }
        fputcsv($handle, self::HEADERS);
        foreach ($rows as $row) {
            $line = [];
            foreach (self::HEADERS as $header) {
                $line[] = (string) ($row[$header] ?? '');
            }
            fputcsv($handle, $line);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return is_string($csv) ? $csv : '';
    }

    /**
     * @return list<array{name: string, label: string, type: string, is_required: bool, is_system: bool, options: list<string>, target_entity_id: null}>
     */
    private function importFields(): array
    {
        $schema = $this->schema->show();
        $fields = [];
        foreach ($schema['fields'] as $field) {
            if (! is_array($field)) {
                continue;
            }
            $name = (string) ($field['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $fields[] = [
                'name' => $name,
                'label' => (string) ($field['label'] ?? $name),
                'type' => (string) ($field['type'] ?? 'text'),
                'is_required' => $name !== 'asset_tag' && (bool) ($field['is_required'] ?? false),
                'is_system' => (bool) ($field['is_system_field'] ?? false),
                'options' => [],
                'target_entity_id' => null,
            ];
        }
        $fields[] = [
            'name' => 'employee_email',
            'label' => 'Employee email',
            'type' => 'email',
            'is_required' => false,
            'is_system' => true,
            'options' => [],
            'target_entity_id' => null,
        ];

        return $fields;
    }

    /**
     * @param  list<array{name: string, label: string, type: string, is_required: bool, is_system: bool, options: list<string>, target_entity_id: null}>  $fields
     */
    private function suggestField(string $header, array $fields): string
    {
        $key = strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $header));
        $aliases = [
            'serialnumber' => 'serial_number',
            'serial' => 'serial_number',
            'assettag' => 'asset_tag',
            'tag' => 'asset_tag',
            'type' => 'asset_type',
            'assettype' => 'asset_type',
            'computername' => 'hostname',
            'hostname' => 'hostname',
            'productname' => 'product_name',
            'productversion' => 'product_version',
            'purchasedate' => 'purchase_date',
            'warrantyend' => 'warranty_end',
            'commissiondate' => 'commission_date',
            'ram' => 'installed_ram',
            'installedram' => 'installed_ram',
            'operatingsystem' => 'operating_system',
            'os' => 'operating_system',
            'bios' => 'bios_version',
            'biosversion' => 'bios_version',
            'employeeemail' => 'employee_email',
            'email' => 'employee_email',
            'assignee' => 'employee_email',
        ];
        $names = array_column($fields, 'name');
        if (isset($aliases[$key]) && in_array($aliases[$key], $names, true)) {
            return $aliases[$key];
        }
        foreach ($fields as $field) {
            $nameKey = strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $field['name']));
            $labelKey = strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $field['label']));
            if ($key !== '' && ($key === $nameKey || $key === $labelKey)) {
                return $field['name'];
            }
        }

        return '__ignore__';
    }

    /**
     * @return list<string>
     */
    private function headerLine(string $filename, string $contents): array
    {
        $lower = strtolower($filename);
        $grid = str_ends_with($lower, '.xlsx') ? $this->reader->readFirstSheet($contents) : $this->csvGrid($contents);
        if ($grid === []) {
            throw ValidationException::withMessages(['file' => [__('CSV header row is required.')]]);
        }

        return array_values(array_filter(
            array_map(static fn ($header): string => trim((string) $header), $grid[0]),
            static fn (string $header): bool => $header !== '',
        ));
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, string>  $map
     * @param  array<string, array{name: string, label: string, type: string, is_required: bool, is_system: bool, options: list<string>, target_entity_id: null}>  $byName
     * @return array<string, mixed>
     */
    private function payloadFromRow(array $row, array $map, array $byName): array
    {
        $payload = [];
        $custom = [];
        foreach ($map as $header => $target) {
            if ($target === '__ignore__' || ! isset($byName[$target])) {
                continue;
            }
            $value = trim((string) ($row[$header] ?? ''));
            if ($value === '') {
                continue;
            }
            if ($target === 'employee_email') {
                $payload['employee_email'] = strtolower($value);

                continue;
            }
            if ($byName[$target]['is_system']) {
                $payload[$target] = $value;
            } else {
                $custom[$target] = $value;
            }
        }
        if ($custom !== []) {
            $payload['custom_values'] = $custom;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, bool>  $seen
     */
    private function assertImportPayload(array $payload, bool $updating, array $seen): void
    {
        $serial = strtoupper(trim((string) ($payload['serial_number'] ?? '')));
        $tag = trim((string) ($payload['asset_tag'] ?? ''));
        if (! $updating && $serial === '') {
            throw ValidationException::withMessages(['serial_number' => [__('Serial number is missing.')]]);
        }
        if ($serial !== '' && isset($seen['serial:'.$serial]) && ! $updating) {
            throw ValidationException::withMessages(['serial_number' => [__('Duplicate serial number.')]]);
        }
        if (! $updating && $serial !== '' && Asset::query()->where('serial_number', $serial)->exists()) {
            throw ValidationException::withMessages(['serial_number' => [__('Duplicate serial number.')]]);
        }
        if ($tag !== '' && isset($seen['tag:'.$tag])) {
            throw ValidationException::withMessages(['asset_tag' => [__('Duplicate asset tag.')]]);
        }
        $type = strtolower(trim((string) ($payload['asset_type'] ?? '')));
        if (! $updating && $type === '') {
            throw ValidationException::withMessages(['asset_type' => [__('Type is missing.')]]);
        }
        if ($type !== '' && ! in_array($type, AssetType::values(), true)) {
            throw ValidationException::withMessages(['asset_type' => [__('Invalid asset type.')]]);
        }
        $status = strtoupper(trim((string) ($payload['status'] ?? '')));
        if ($status !== '' && ! in_array($status, AssetStatus::values(), true)) {
            throw ValidationException::withMessages(['status' => [__('Invalid asset status.')]]);
        }
        if (! $updating && $status !== '' && ! in_array($status, [AssetStatus::New->value, AssetStatus::Available->value, AssetStatus::Assigned->value], true)) {
            throw ValidationException::withMessages(['status' => [__('A new asset starts as New, Available, or Assigned.')]]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dedupeKey(?string $upsertField, array $payload): ?string
    {
        if ($upsertField === 'serial_number' || isset($payload['serial_number'])) {
            $serial = strtoupper(trim((string) ($payload['serial_number'] ?? '')));
            if ($serial !== '') {
                return 'serial:'.$serial;
            }
        }
        if ($upsertField === 'asset_tag') {
            $tag = trim((string) ($payload['asset_tag'] ?? ''));
            if ($tag !== '') {
                return 'tag:'.$tag;
            }
        }

        return null;
    }

    /**
     * A serial number is one laptop. A later row, in this file or already on the register, is a reissue.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, true>  $stagedSerials
     * @return Asset|'staged'|null
     */
    private function resolveImportAsset(?string $upsertField, array $payload, array $stagedSerials): Asset|string|null
    {
        $found = $this->findUpsert($upsertField, $payload);
        if ($found instanceof Asset) {
            return $found;
        }
        $serial = strtoupper(trim((string) ($payload['serial_number'] ?? '')));
        if ($serial === '') {
            return null;
        }
        $bySerial = Asset::query()->where('serial_number', $serial)->first();
        if ($bySerial instanceof Asset) {
            return $bySerial;
        }
        if (isset($stagedSerials[$serial])) {
            return 'staged';
        }

        return null;
    }

    private function findUpsert(?string $upsertField, array $payload): ?Asset
    {
        if ($upsertField === null) {
            return null;
        }
        if ($upsertField === 'serial_number') {
            $serial = strtoupper(trim((string) ($payload['serial_number'] ?? '')));

            return $serial === '' ? null : Asset::query()->where('serial_number', $serial)->first();
        }
        if ($upsertField === 'asset_tag') {
            $tag = trim((string) ($payload['asset_tag'] ?? ''));

            return $tag === '' ? null : Asset::query()->where('asset_tag', $tag)->first();
        }
        $value = $payload[$upsertField] ?? ($payload['custom_values'][$upsertField] ?? null);
        if (! is_string($value) || $value === '') {
            return null;
        }
        if (in_array($upsertField, ['hostname', 'manufacturer', 'product_name'], true)) {
            return Asset::query()->where($upsertField, $value)->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeCreate(array $payload, TenantUser $actor): Asset
    {
        $status = strtoupper(trim((string) ($payload['status'] ?? 'AVAILABLE')));
        $assign = $status === AssetStatus::Assigned->value;
        $asset = $this->registry->create([
            'serial_number' => $payload['serial_number'] ?? '',
            'asset_type' => $payload['asset_type'] ?? 'laptop',
            'status' => $assign ? AssetStatus::Available->value : ($status !== '' ? $status : AssetStatus::Available->value),
            'manufacturer' => $payload['manufacturer'] ?? null,
            'product_name' => $payload['product_name'] ?? null,
            'product_version' => $payload['product_version'] ?? null,
            'hostname' => $payload['hostname'] ?? null,
            'purchase_date' => $payload['purchase_date'] ?? null,
            'warranty_end' => $payload['warranty_end'] ?? null,
            'commission_date' => $payload['commission_date'] ?? null,
            'installed_ram' => $payload['installed_ram'] ?? null,
            'processor' => $payload['processor'] ?? null,
            'operating_system' => $payload['operating_system'] ?? null,
            'bios_version' => $payload['bios_version'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'custom_values' => $payload['custom_values'] ?? [],
        ], $actor, false);
        $this->assignIfPresent($asset, $payload, $actor);

        return $asset;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeUpdate(Asset $asset, array $payload, TenantUser $actor): Asset
    {
        $data = $payload;
        unset($data['employee_email'], $data['asset_tag']);
        if (isset($data['custom_values']) && is_array($data['custom_values'])) {
            $current = is_array($asset->custom_values_json) ? $asset->custom_values_json : [];
            $data['custom_values'] = array_merge($current, $data['custom_values']);
        }
        $updated = $this->registry->update($asset, $data, $actor, false);
        $this->assignIfPresent($updated, $payload, $actor);

        return $updated;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assignIfPresent(Asset $asset, array $payload, TenantUser $actor): void
    {
        $email = strtolower(trim((string) ($payload['employee_email'] ?? '')));
        if ($email === '') {
            return;
        }
        $employee = TenantUser::findByEmail($email);
        if (! $employee instanceof TenantUser) {
            return;
        }
        $asset->refresh();
        if ((string) $asset->assigned_user_id === (string) $employee->id && $asset->status === AssetStatus::Assigned->value) {
            return;
        }
        $this->workflow->assign($asset, $employee, $actor);
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function csvFromRows(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return is_string($csv) ? $csv : '';
    }
}
