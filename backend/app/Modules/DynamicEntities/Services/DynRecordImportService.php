<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynField;
use App\Modules\DynamicEntities\Models\DynRecord;
use App\Modules\DynamicEntities\Support\AtcMetacoresoftExportMap;
use App\Modules\DynamicEntities\Support\DynFieldType;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Workspace\Services\TenantActivityLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DynRecordImportService
{
    private const IGNORE = '__ignore__';

    public function __construct(
        private readonly DynRecordService $records,
        private readonly TenantActivityLogger $activity,
    ) {}

    /**
     * @return list<array{
     *   name: string,
     *   label: string,
     *   type: string,
     *   is_required: bool,
     *   options: list<string>,
     *   target_entity_id: string|null
     * }>
     */
    public function importableFields(DynEntity $entity): array
    {
        $entity->loadMissing('fields');

        return $entity->fields
            ->filter(fn (DynField $f): bool => ! $f->is_system_field && ! $f->is_virtual)
            ->filter(fn (DynField $f): bool => $f->type !== DynFieldType::FILE)
            ->filter(fn (DynField $f): bool => ! in_array($f->name, ['actions', 'workflows', 'print', 'id'], true))
            ->sortBy('field_order')
            ->values()
            ->map(fn (DynField $f): array => [
                'name' => $f->name,
                'label' => $f->label,
                'type' => $f->type,
                'is_required' => (bool) $f->is_required,
                'options' => $this->choiceOptions($f->options_json),
                'target_entity_id' => $f->target_entity_id ? (string) $f->target_entity_id : null,
            ])
            ->all();
    }

    /**
     * @return array{filename: string, headers: list<string>, csv: string}
     */
    public function buildTemplate(DynEntity $entity): array
    {
        $fields = $this->importableFields($entity);
        $byName = [];
        foreach ($fields as $f) {
            $byName[$f['name']] = $f;
        }

        // Prefer Metacoresoft export labels/order so ATC CSV templates import without remapping.
        $metaColumns = AtcMetacoresoftExportMap::columnsFor((string) $entity->slug);
        $headers = [];
        $usedFields = [];
        if ($metaColumns !== []) {
            foreach ($metaColumns as $label => $fieldName) {
                if ($fieldName === 'title' || $fieldName === 'status') {
                    $headers[] = $label;
                    $usedFields[$fieldName] = true;

                    continue;
                }
                if (! isset($byName[$fieldName])) {
                    continue;
                }
                $headers[] = $label;
                $usedFields[$fieldName] = true;
            }
            foreach ($fields as $f) {
                if (isset($usedFields[$f['name']])) {
                    continue;
                }
                $headers[] = $f['label'] !== '' ? $f['label'] : $f['name'];
            }
        } else {
            $headers = array_map(static fn (array $f): string => $f['label'] !== '' ? $f['label'] : $f['name'], $fields);
        }

        if ($headers === []) {
            $headers = ['Title', 'Status'];
        }

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'Unable to build template.']);
        }
        fputcsv($handle, $headers);
        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return [
            'filename' => $entity->slug.'-import-template.csv',
            'headers' => $headers,
            'csv' => $csv,
        ];
    }

    /**
     * @return array{
     *   headers: list<string>,
     *   suggested_map: array<string, string>,
     *   fields: list<array{name: string, label: string, type: string, is_required: bool, options: list<string>, target_entity_id: string|null}>,
     *   sample_rows: list<array<string, string>>,
     *   row_count: int
     * }
     */
    public function analyze(DynEntity $entity, UploadedFile $file): array
    {
        [$headers, $rows] = $this->readCsv($file, 6);
        $fields = $this->importableFields($entity);
        $suggested = $this->suggestMap($headers, $fields, (string) $entity->slug);

        $samples = [];
        foreach (array_slice($rows, 0, 5) as $row) {
            $sample = [];
            foreach ($headers as $i => $header) {
                $sample[$header] = (string) ($row[$i] ?? '');
            }
            $samples[] = $sample;
        }

        // Count remaining rows without loading all into memory again for small files we already have all.
        $rowCount = count($rows);
        if ($rowCount >= 5) {
            $rowCount = $this->countDataRows($file);
        }

        return [
            'headers' => $headers,
            'suggested_map' => $suggested,
            'fields' => $fields,
            'sample_rows' => $samples,
            'row_count' => $rowCount,
        ];
    }

    /**
     * @param  array<string, string>  $columnMap  csv header => field name | __ignore__
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
    public function import(
        DynEntity $entity,
        UploadedFile $file,
        array $columnMap,
        ?string $upsertField,
        TenantUser $actor,
        bool $dryRun = false,
    ): array {
        [$headers, $rows] = $this->readCsv($file, 2001);
        if (count($rows) > 2000) {
            throw ValidationException::withMessages(['file' => 'Import supports up to 2,000 data rows per file.']);
        }

        $fields = $this->importableFields($entity);
        $fieldNames = array_column($fields, 'name');
        $fieldByName = [];
        foreach ($fields as $f) {
            $fieldByName[$f['name']] = $f;
        }

        $normalizedMap = [];
        foreach ($columnMap as $csvHeader => $target) {
            $csvHeader = (string) $csvHeader;
            $target = (string) $target;
            if ($target === '' || $target === self::IGNORE) {
                $normalizedMap[$csvHeader] = self::IGNORE;

                continue;
            }
            if (! in_array($target, $fieldNames, true) && ! in_array($target, ['title', 'status'], true)) {
                throw ValidationException::withMessages([
                    'column_map' => "Unknown entity field “{$target}”.",
                ]);
            }
            $normalizedMap[$csvHeader] = $target;
        }

        if ($upsertField !== null && $upsertField !== '') {
            if (! in_array($upsertField, $fieldNames, true) && ! in_array($upsertField, ['title', 'status'], true)) {
                throw ValidationException::withMessages(['upsert_field' => 'Invalid upsert field.']);
            }
        } else {
            $upsertField = null;
        }

        $headerIndex = [];
        foreach ($headers as $i => $h) {
            $headerIndex[$h] = $i;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $wouldCreate = 0;
        $wouldUpdate = 0;
        $wouldSkip = 0;
        $errors = [];

        foreach ($rows as $offset => $row) {
            $rowNum = $offset + 2; // 1-based + header
            try {
                $parsed = $this->parseRow($normalizedMap, $headerIndex, $row, $fieldByName);
                $values = $parsed['values'];
                $title = $parsed['title'];
                $status = $parsed['status'];

                if ($values === [] && $title === null && $status === null) {
                    if ($dryRun) {
                        $wouldSkip++;
                    } else {
                        $skipped++;
                    }

                    continue;
                }

                $existing = null;
                if ($upsertField !== null) {
                    $key = $upsertField === 'title'
                        ? $title
                        : ($upsertField === 'status' ? $status : ($values[$upsertField] ?? null));
                    if ($key !== null && $key !== '') {
                        $existing = $this->findByUpsertKey($entity, $upsertField, $key);
                    }
                }

                $isUpdate = $existing instanceof DynRecord;
                $this->assertRowRequired($fields, $values, $isUpdate ? array_keys($values) : null);

                if ($dryRun) {
                    if ($isUpdate) {
                        $wouldUpdate++;
                    } else {
                        $wouldCreate++;
                    }

                    continue;
                }

                if ($isUpdate) {
                    $this->records->update($existing, [
                        'title' => $title,
                        'status' => $status,
                        'values' => $values,
                    ], $actor, false);
                    $updated++;
                } else {
                    $this->records->create($entity, [
                        'title' => $title,
                        'status' => $status,
                        'values' => $values,
                    ], $actor, false);
                    $created++;
                }
            } catch (ValidationException $e) {
                $msg = collect($e->errors())->flatten()->first() ?: 'Validation failed.';
                $errors[] = ['row' => $rowNum, 'message' => (string) $msg];
                if ($dryRun) {
                    $wouldSkip++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $errors[] = ['row' => $rowNum, 'message' => $e->getMessage()];
                if ($dryRun) {
                    $wouldSkip++;
                } else {
                    $skipped++;
                }
            }
        }

        $errorRows = count($errors);
        $validRows = $dryRun
            ? ($wouldCreate + $wouldUpdate)
            : ($created + $updated);

        if (! $dryRun && ($created > 0 || $updated > 0 || $errorRows > 0)) {
            $this->activity->record(
                module: 'dynamic_entities',
                action: 'dyn_record.imported',
                summary: "CSV import · {$entity->name}: {$created} created, {$updated} updated, {$skipped} skipped",
                entityType: 'dyn_entity',
                entityId: (string) $entity->slug,
                entityLabel: (string) $entity->name,
                actor: $actor,
                metadata: [
                    'entity_slug' => (string) $entity->slug,
                    'created' => $created,
                    'updated' => $updated,
                    'skipped' => $skipped,
                    'error_rows' => $errorRows,
                    'upsert_field' => $upsertField,
                    'filename' => $file->getClientOriginalName(),
                ],
            );
        }

        return [
            'dry_run' => $dryRun,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'would_create' => $wouldCreate,
            'would_update' => $wouldUpdate,
            'would_skip' => $wouldSkip,
            'valid_rows' => $validRows,
            'error_rows' => $errorRows,
            'errors' => array_slice($errors, 0, 50),
        ];
    }

    /**
     * @param  list<array{name: string, label: string, type: string, is_required: bool}>  $fields
     * @param  list<string>  $headers
     * @return array<string, string>
     */
    /**
     * @param  list<array{name: string, label: string, type: string, is_required: bool}>  $fields
     * @param  list<string>  $headers
     * @return array<string, string>
     */
    private function suggestMap(array $headers, array $fields, ?string $entitySlug = null): array
    {
        $aliases = [];
        foreach ($fields as $f) {
            $aliases[$this->norm($f['name'])] = $f['name'];
            $aliases[$this->norm($f['label'])] = $f['name'];
        }
        $aliases['title'] = 'title';
        $aliases['status'] = 'status';

        // Prefer known Metacoresoft Excel labels for this entity (exact ATC export parity).
        $metaByNorm = [];
        if ($entitySlug !== null && $entitySlug !== '') {
            foreach (AtcMetacoresoftExportMap::columnsFor($entitySlug) as $label => $fieldName) {
                $metaByNorm[$this->norm($label)] = $fieldName;
            }
        }

        $used = [];
        $map = [];
        foreach ($headers as $header) {
            $key = $this->norm($header);
            $match = $metaByNorm[$key] ?? $aliases[$key] ?? null;
            if ($match !== null && ! isset($used[$match])) {
                $map[$header] = $match;
                $used[$match] = true;
            } else {
                $map[$header] = self::IGNORE;
            }
        }

        return $map;
    }

    private function norm(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
        $value = strtolower(trim($value, " \t\n\r\0\x0B\"'"));
        $value = preg_replace('/[^a-z0-9]+/', '', $value) ?? $value;

        return $value;
    }

    /**
     * @param  array<string, string>  $normalizedMap
     * @param  array<string, int>  $headerIndex
     * @param  list<string>  $row
     * @param  array<string, array{name: string, label: string, type: string, is_required: bool, options: list<string>, target_entity_id: string|null}>  $fieldByName
     * @return array{values: array<string, mixed>, title: string|null, status: string|null}
     */
    private function parseRow(array $normalizedMap, array $headerIndex, array $row, array $fieldByName): array
    {
        $values = [];
        $title = null;
        $status = null;

        foreach ($normalizedMap as $csvHeader => $target) {
            if ($target === self::IGNORE) {
                continue;
            }
            $idx = $headerIndex[$csvHeader] ?? null;
            if ($idx === null) {
                continue;
            }
            $raw = trim((string) ($row[$idx] ?? ''));
            if ($target === 'title') {
                $title = $raw !== '' ? $raw : null;

                continue;
            }
            if ($target === 'status') {
                $status = $raw !== '' ? $raw : null;

                continue;
            }
            $meta = $fieldByName[$target] ?? null;
            if ($meta === null) {
                continue;
            }
            $values[$target] = $this->parseAndValidateValue($raw, $meta);
        }

        return ['values' => $values, 'title' => $title, 'status' => $status];
    }

    /**
     * @param  list<array{name: string, label: string, type: string, is_required: bool, options: list<string>, target_entity_id: string|null}>  $fields
     * @param  array<string, mixed>  $values
     * @param  list<string>|null  $onlyFieldNames
     */
    private function assertRowRequired(array $fields, array $values, ?array $onlyFieldNames = null): void
    {
        $errors = [];
        $only = $onlyFieldNames === null ? null : array_fill_keys($onlyFieldNames, true);

        foreach ($fields as $field) {
            if (! $field['is_required']) {
                continue;
            }
            if ($only !== null && ! isset($only[$field['name']])) {
                continue;
            }
            if (! array_key_exists($field['name'], $values) || $values[$field['name']] === null || $values[$field['name']] === '') {
                $errors[$field['name']] = $field['label'].' is required.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array{name: string, label: string, type: string, is_required: bool, options: list<string>, target_entity_id: string|null}  $meta
     */
    private function parseAndValidateValue(string $raw, array $meta): mixed
    {
        if ($raw === '') {
            return null;
        }

        $label = $meta['label'];
        $type = $meta['type'];

        return match ($type) {
            DynFieldType::NUMBER => $this->parseNumber($raw, $label, false),
            DynFieldType::DECIMAL => $this->parseNumber($raw, $label, true),
            DynFieldType::BOOLEAN => $this->parseBoolean($raw, $label),
            DynFieldType::DATE => $this->parseDate($raw, $label, false),
            DynFieldType::DATETIME => $this->parseDate($raw, $label, true),
            DynFieldType::EMAIL => $this->parseEmail($raw, $label),
            DynFieldType::PHONE => $this->parsePhone($raw, $label),
            DynFieldType::SELECT => $this->parseSelect($raw, $label, $meta['options']),
            DynFieldType::MULTISELECT => $this->parseMultiselect($raw, $label, $meta['options']),
            DynFieldType::RELATIONSHIP => $this->parseRelationship($raw, $label, $meta['target_entity_id']),
            DynFieldType::FILE => throw ValidationException::withMessages([
                $meta['name'] => "{$label} cannot be imported via CSV.",
            ]),
            default => $raw,
        };
    }

    private function parseNumber(string $raw, string $label, bool $allowDecimal): float|int
    {
        $normalized = str_replace([',', ' '], '', $raw);
        if (! is_numeric($normalized)) {
            throw ValidationException::withMessages([
                'value' => "{$label} must be a ".($allowDecimal ? 'number' : 'whole number').". Got “{$raw}”.",
            ]);
        }
        if (! $allowDecimal && str_contains($normalized, '.')) {
            $asFloat = (float) $normalized;
            if ($asFloat != (int) $asFloat) {
                throw ValidationException::withMessages([
                    'value' => "{$label} must be a whole number. Got “{$raw}”.",
                ]);
            }

            return (int) $asFloat;
        }

        return $allowDecimal ? (0 + $normalized) : (int) (0 + $normalized);
    }

    private function parseBoolean(string $raw, string $label): bool
    {
        $key = strtolower($raw);
        if (in_array($key, ['1', 'true', 'yes', 'y', 'on'], true)) {
            return true;
        }
        if (in_array($key, ['0', 'false', 'no', 'n', 'off'], true)) {
            return false;
        }

        throw ValidationException::withMessages([
            'value' => "{$label} must be true/false (or yes/no). Got “{$raw}”.",
        ]);
    }

    private function parseDate(string $raw, string $label, bool $withTime): string
    {
        $formats = $withTime
            ? ['Y-m-d H:i:s', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i:sP', 'Y-m-d H:i', 'm/d/Y H:i', 'd/m/Y H:i', 'Y-m-d']
            : ['Y-m-d', 'm/d/Y', 'd/m/Y', 'Y/m/d'];

        foreach ($formats as $format) {
            $dt = \DateTimeImmutable::createFromFormat('!'.$format, $raw);
            if (! $dt instanceof \DateTimeImmutable) {
                continue;
            }
            $errors = \DateTimeImmutable::getLastErrors();
            $warningCount = is_array($errors) ? (int) ($errors['warning_count'] ?? 0) : 0;
            $errorCount = is_array($errors) ? (int) ($errors['error_count'] ?? 0) : 0;
            if ($warningCount === 0 && $errorCount === 0) {
                return $withTime ? $dt->format('Y-m-d H:i:s') : $dt->format('Y-m-d');
            }
        }

        $hint = $withTime ? 'YYYY-MM-DD HH:MM:SS' : 'YYYY-MM-DD';
        throw ValidationException::withMessages([
            'value' => "{$label} must be a valid date ({$hint}). Got “{$raw}”.",
        ]);
    }

    private function parseEmail(string $raw, string $label): string
    {
        if (! filter_var($raw, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'value' => "{$label} must be a valid email. Got “{$raw}”.",
            ]);
        }

        return strtolower($raw);
    }

    private function parsePhone(string $raw, string $label): string
    {
        if (! preg_match('/^[+\d][\d\s().\-]{5,30}$/', $raw)) {
            throw ValidationException::withMessages([
                'value' => "{$label} must be a valid phone number. Got “{$raw}”.",
            ]);
        }

        return $raw;
    }

    /**
     * @param  list<string>  $options
     */
    private function parseSelect(string $raw, string $label, array $options): string
    {
        if ($options === []) {
            return $raw;
        }

        foreach ($options as $option) {
            if (strcasecmp($option, $raw) === 0) {
                return $option;
            }
        }

        $allowed = implode(', ', array_slice($options, 0, 8));
        throw ValidationException::withMessages([
            'value' => "{$label} must be one of: {$allowed}. Got “{$raw}”.",
        ]);
    }

    /**
     * @param  list<string>  $options
     * @return list<string>
     */
    private function parseMultiselect(string $raw, string $label, array $options): array
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', $raw)), static fn (string $p): bool => $p !== ''));
        if ($parts === []) {
            return [];
        }

        $out = [];
        foreach ($parts as $part) {
            $out[] = $this->parseSelect($part, $label, $options);
        }

        return array_values(array_unique($out));
    }

    private function parseRelationship(string $raw, string $label, ?string $targetEntityId): string
    {
        if ($targetEntityId === null || $targetEntityId === '') {
            return $raw;
        }

        $query = DynRecord::query()
            ->where('entity_id', $targetEntityId)
            ->where('is_deleted', false);

        $match = (clone $query)->whereKey($raw)->first()
            ?? (clone $query)->where('title', $raw)->first()
            ?? (clone $query)->where('source_external_id', $raw)->first();

        if (! $match instanceof DynRecord) {
            throw ValidationException::withMessages([
                'value' => "{$label}: no matching related record for “{$raw}” (use record id, title, or external id).",
            ]);
        }

        return (string) $match->id;
    }

    /**
     * @return list<string>
     */
    private function choiceOptions(mixed $optionsJson): array
    {
        if (is_array($optionsJson)) {
            if (array_is_list($optionsJson)) {
                return array_values(array_filter(array_map(
                    static fn ($v): string => trim((string) $v),
                    $optionsJson,
                ), static fn (string $v): bool => $v !== ''));
            }
            if (isset($optionsJson['choices']) && is_array($optionsJson['choices'])) {
                return array_values(array_filter(array_map(
                    static fn ($v): string => trim((string) $v),
                    $optionsJson['choices'],
                ), static fn (string $v): bool => $v !== ''));
            }
        }

        return [];
    }

    private function findByUpsertKey(DynEntity $entity, string $field, mixed $key): ?DynRecord
    {
        $needle = is_scalar($key) ? (string) $key : '';
        if ($needle === '') {
            return null;
        }

        if ($field === 'title') {
            return DynRecord::query()
                ->where('entity_id', $entity->id)
                ->where('is_deleted', false)
                ->where('title', $needle)
                ->first();
        }

        if ($field === 'status') {
            return DynRecord::query()
                ->where('entity_id', $entity->id)
                ->where('is_deleted', false)
                ->where('status', $needle)
                ->first();
        }

        $indexed = DB::table('dyn_record_indexes')
            ->where('entity_id', $entity->id)
            ->where('field_name', $field)
            ->where('value_string', $needle)
            ->value('record_id');

        if ($indexed) {
            return DynRecord::query()
                ->whereKey((string) $indexed)
                ->where('is_deleted', false)
                ->first();
        }

        // Fallback scan for non-indexed values (small tenants / new fields).
        return DynRecord::query()
            ->where('entity_id', $entity->id)
            ->where('is_deleted', false)
            ->where("values_json->{$field}", $needle)
            ->first();
    }

    /**
     * @return array{0: list<string>, 1: list<list<string>>}
     */
    private function readCsv(UploadedFile $file, int $maxRows): array
    {
        $path = $file->getRealPath();
        if ($path === false) {
            throw ValidationException::withMessages(['file' => 'Could not read upload.']);
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'Could not read upload.']);
        }

        // Strip UTF-8 BOM
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle);
        if (! is_array($header) || $header === []) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => 'CSV header row is required.']);
        }

        $headers = [];
        $seen = [];
        foreach ($header as $col) {
            $name = (string) $col;
            $name = preg_replace('/^\xEF\xBB\xBF/', '', $name) ?? $name;
            $name = trim($name, " \t\n\r\0\x0B\"'");
            if ($name === '') {
                $name = 'Column_'.(count($headers) + 1);
            }
            $base = $name;
            $n = 2;
            while (isset($seen[strtolower($name)])) {
                $name = $base.'_'.$n;
                $n++;
            }
            $seen[strtolower($name)] = true;
            $headers[] = $name;
        }

        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if (! is_array($line)) {
                continue;
            }
            $allEmpty = true;
            foreach ($line as $cell) {
                if (trim((string) $cell) !== '') {
                    $allEmpty = false;
                    break;
                }
            }
            if ($allEmpty) {
                continue;
            }
            $rows[] = array_map(static fn ($c): string => (string) $c, $line);
            if (count($rows) >= $maxRows) {
                break;
            }
        }
        fclose($handle);

        return [$headers, $rows];
    }

    private function countDataRows(UploadedFile $file): int
    {
        $path = $file->getRealPath();
        if ($path === false) {
            return 0;
        }
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return 0;
        }
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }
        fgetcsv($handle);
        $count = 0;
        while (($line = fgetcsv($handle)) !== false) {
            if (! is_array($line)) {
                continue;
            }
            foreach ($line as $cell) {
                if (trim((string) $cell) !== '') {
                    $count++;
                    break;
                }
            }
        }
        fclose($handle);

        return $count;
    }
}
