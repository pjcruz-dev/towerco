<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynHtmlReport;
use App\Modules\DynamicEntities\Models\DynRecord;
use App\Modules\DynamicEntities\Support\DynReportBuilderHtmlGenerator;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DynReportBuilderService
{
    public function __construct(
        private readonly DynRecordService $records,
    ) {}

    /**
     * @param  array<string, mixed>  $def
     * @return array{columns: list<array{key: string, label: string, numeric: bool}>, rows: list<array<string, mixed>>, totals: array<string, float|int>, meta: array<string, mixed>}
     */
    public function preview(array $def, TenantUser $actor): array
    {
        $entitySlug = trim((string) ($def['entity_slug'] ?? ''));
        if ($entitySlug === '') {
            throw ValidationException::withMessages(['entity_slug' => ['Choose a source entity.']]);
        }

        $entity = DynEntity::query()->where('slug', $entitySlug)->first();
        if (! $entity) {
            throw ValidationException::withMessages(['entity_slug' => ['Entity not found.']]);
        }

        $format = strtolower((string) ($def['format'] ?? 'summary'));
        if (! in_array($format, ['summary', 'detail', 'matrix'], true)) {
            $format = 'summary';
        }

        $metric = strtolower((string) ($def['metric'] ?? 'count'));
        $metricField = trim((string) ($def['metric_field'] ?? ''));
        $groupBy = trim((string) ($def['group_by'] ?? ''));
        $groupBy2 = trim((string) ($def['group_by_2'] ?? ''));
        $matrixCol = trim((string) ($def['matrix_column'] ?? $groupBy2));
        $limit = max(1, min(2000, (int) ($def['row_limit'] ?? 500)));
        $sortBy = strtolower((string) ($def['sort_by'] ?? 'metric'));
        $direction = strtolower((string) ($def['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $dateGrouping = strtolower((string) ($def['date_grouping'] ?? 'exact'));

        $filters = is_array($def['filters'] ?? null) ? $def['filters'] : [];
        $queryFilters = $this->mapFilters($filters);
        $fetchLimit = max($limit, min(2000, max(500, $limit * 4)));
        $rawRows = $this->fetchAssociativeRows($entity, $queryFilters, $fetchLimit);

        return match ($format) {
            'detail' => $this->buildDetail($rawRows, $groupBy, $metric, $metricField, $limit, $sortBy, $direction, $dateGrouping),
            'matrix' => $this->buildMatrix($rawRows, $groupBy, $matrixCol !== '' ? $matrixCol : $groupBy2, $metric, $metricField, $limit, $dateGrouping),
            default => $this->buildSummary($rawRows, $groupBy, $groupBy2, $metric, $metricField, $limit, $sortBy, $direction, $dateGrouping),
        };
    }

    /**
     * @param  array<string, mixed>  $def
     * @return array{html: string, css: string, js: string}
     */
    public function generateSources(array $def): array
    {
        return DynReportBuilderHtmlGenerator::generate($def);
    }

    /**
     * @param  array<string, mixed>  $def
     * @return array<string, mixed>
     */
    public function saveAsHtmlReport(array $def, TenantUser $actor, ?DynHtmlReport $existing = null): array
    {
        $title = trim((string) ($def['title'] ?? ''));
        if ($title === '') {
            throw ValidationException::withMessages(['title' => ['Report title is required.']]);
        }

        $htmlService = app(DynHtmlReportService::class);
        $def['saved_slug'] = $existing
            ? (string) $existing->slug
            : (string) ($def['slug'] ?? $def['saved_slug'] ?? '');

        $sources = $this->generateSources($def);
        $payload = [
            'name' => $title,
            'slug' => trim((string) ($def['slug'] ?? '')) ?: null,
            'description' => trim((string) ($def['caption'] ?? '')) ?: null,
            'html_source' => $sources['html'],
            'css_source' => $sources['css'],
            'js_source' => $sources['js'],
            'builder_json' => $def,
        ];

        if ($existing) {
            $report = $htmlService->update($existing, $payload, $actor);
        } else {
            $report = $htmlService->create($payload, $actor);
        }

        // Persist saved_slug onto builder_json after slug is known.
        $def['saved_slug'] = (string) ($report['slug'] ?? '');
        $sources = $this->generateSources($def);
        $id = (string) ($report['id'] ?? '');
        if ($id !== '') {
            $model = DynHtmlReport::query()->find($id);
            if ($model) {
                return $htmlService->update($model, [
                    'html_source' => $sources['html'],
                    'css_source' => $sources['css'],
                    'js_source' => $sources['js'],
                    'builder_json' => $def,
                ], $actor);
            }
        }

        return $report;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{columns: list<array{key: string, label: string, numeric: bool}>, rows: list<array<string, mixed>>, totals: array<string, float|int>, meta: array<string, mixed>}
     */
    private function buildSummary(
        array $rows,
        string $groupBy,
        string $groupBy2,
        string $metric,
        string $metricField,
        int $limit,
        string $sortBy,
        string $direction,
        string $dateGrouping,
    ): array {
        $buckets = [];
        foreach ($rows as $row) {
            $g1 = $this->groupValue($row, $groupBy, $dateGrouping);
            $g2 = $groupBy2 !== '' ? $this->groupValue($row, $groupBy2, $dateGrouping) : null;
            $key = $g2 !== null ? $g1.'||'.$g2 : $g1;
            if (! isset($buckets[$key])) {
                $buckets[$key] = [
                    'group' => $g1,
                    'group_2' => $g2,
                    'count' => 0,
                    'sum' => 0.0,
                ];
            }
            $buckets[$key]['count']++;
            if ($metric === 'sum' && $metricField !== '') {
                $buckets[$key]['sum'] += $this->numeric($row[$metricField] ?? 0);
            }
        }

        $metricKey = $metric === 'sum' ? 'metric' : 'metric';
        $out = [];
        foreach ($buckets as $b) {
            $metricVal = $metric === 'sum' ? $b['sum'] : $b['count'];
            $row = ['group' => $b['group'], $metricKey => $metricVal];
            if ($groupBy2 !== '') {
                $row['group_2'] = $b['group_2'];
            }
            $out[] = $row;
        }

        usort($out, static function (array $a, array $b) use ($sortBy, $direction): int {
            $av = $sortBy === 'group' ? (string) ($a['group'] ?? '') : (float) ($a['metric'] ?? 0);
            $bv = $sortBy === 'group' ? (string) ($b['group'] ?? '') : (float) ($b['metric'] ?? 0);
            if ($av == $bv) {
                return 0;
            }
            $cmp = $av <=> $bv;

            return $direction === 'asc' ? $cmp : -$cmp;
        });

        $out = array_slice($out, 0, $limit);
        $totalMetric = 0.0;
        foreach ($out as $r) {
            $totalMetric += (float) ($r['metric'] ?? 0);
        }

        $columns = [
            ['key' => 'group', 'label' => $groupBy !== '' ? Str::headline($groupBy) : 'Group', 'numeric' => false],
        ];
        if ($groupBy2 !== '') {
            $columns[] = ['key' => 'group_2', 'label' => Str::headline($groupBy2), 'numeric' => false];
        }
        $columns[] = [
            'key' => 'metric',
            'label' => $metric === 'sum' ? ('Sum of '.Str::headline($metricField)) : 'Count of records',
            'numeric' => true,
        ];

        return [
            'columns' => $columns,
            'rows' => $out,
            'totals' => ['metric' => $metric === 'sum' ? $totalMetric : (int) $totalMetric],
            'meta' => ['format' => 'summary', 'row_count' => count($out)],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{columns: list<array{key: string, label: string, numeric: bool}>, rows: list<array<string, mixed>>, totals: array<string, float|int>, meta: array<string, mixed>}
     */
    private function buildDetail(
        array $rows,
        string $groupBy,
        string $metric,
        string $metricField,
        int $limit,
        string $sortBy,
        string $direction,
        string $dateGrouping,
    ): array {
        $out = [];
        foreach (array_slice($rows, 0, $limit) as $row) {
            $item = [
                'group' => $groupBy !== '' ? $this->groupValue($row, $groupBy, $dateGrouping) : ($row['title'] ?? $row['id'] ?? '—'),
            ];
            foreach ($row as $k => $v) {
                if (is_scalar($v) || $v === null) {
                    $item[$k] = $v;
                }
            }
            if ($metric === 'sum' && $metricField !== '') {
                $item['metric'] = $this->numeric($row[$metricField] ?? 0);
            }
            $out[] = $item;
        }

        // Prefer a compact set of columns
        $preferred = array_values(array_unique(array_filter([
            'group',
            $groupBy !== '' ? $groupBy : null,
            'status',
            'title',
            $metricField !== '' ? $metricField : null,
            'metric',
        ])));

        $columns = [];
        foreach ($preferred as $key) {
            $columns[] = [
                'key' => $key,
                'label' => Str::headline($key),
                'numeric' => $key === 'metric' || $key === $metricField,
            ];
        }
        if ($columns === []) {
            $columns[] = ['key' => 'group', 'label' => 'Record', 'numeric' => false];
        }

        $total = 0.0;
        if ($metric === 'sum' && $metricField !== '') {
            foreach ($out as $r) {
                $total += $this->numeric($r[$metricField] ?? $r['metric'] ?? 0);
            }
        }

        return [
            'columns' => $columns,
            'rows' => $out,
            'totals' => $metric === 'sum' ? ['metric' => $total, $metricField => $total] : [],
            'meta' => ['format' => 'detail', 'row_count' => count($out)],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{columns: list<array{key: string, label: string, numeric: bool}>, rows: list<array<string, mixed>>, totals: array<string, float|int>, meta: array<string, mixed>}
     */
    private function buildMatrix(
        array $rows,
        string $rowField,
        string $colField,
        string $metric,
        string $metricField,
        int $limit,
        string $dateGrouping,
    ): array {
        if ($rowField === '' || $colField === '') {
            throw ValidationException::withMessages([
                'group_by' => ['Matrix reports need a row grouping and a column field (Then by / matrix column).'],
            ]);
        }

        $matrix = [];
        $colKeys = [];
        foreach ($rows as $row) {
            $r = $this->groupValue($row, $rowField, $dateGrouping);
            $c = $this->groupValue($row, $colField, $dateGrouping);
            $colKeys[$c] = true;
            if (! isset($matrix[$r])) {
                $matrix[$r] = [];
            }
            if (! isset($matrix[$r][$c])) {
                $matrix[$r][$c] = ['count' => 0, 'sum' => 0.0];
            }
            $matrix[$r][$c]['count']++;
            if ($metric === 'sum' && $metricField !== '') {
                $matrix[$r][$c]['sum'] += $this->numeric($row[$metricField] ?? 0);
            }
        }

        $colList = array_keys($colKeys);
        sort($colList);
        $colList = array_slice($colList, 0, 24);

        $columns = [['key' => 'group', 'label' => Str::headline($rowField), 'numeric' => false]];
        foreach ($colList as $c) {
            $safe = 'c_'.substr(sha1($c), 0, 8);
            $columns[] = ['key' => $safe, 'label' => $c, 'numeric' => true, 'col_value' => $c];
        }
        $columns[] = ['key' => 'row_total', 'label' => 'Total', 'numeric' => true];

        $out = [];
        $totals = ['row_total' => 0.0];
        foreach ($colList as $c) {
            $safe = 'c_'.substr(sha1($c), 0, 8);
            $totals[$safe] = 0.0;
        }

        foreach ($matrix as $rLabel => $cols) {
            $line = ['group' => $rLabel];
            $rowTotal = 0.0;
            foreach ($colList as $c) {
                $safe = 'c_'.substr(sha1($c), 0, 8);
                $cell = $cols[$c] ?? ['count' => 0, 'sum' => 0.0];
                $val = $metric === 'sum' ? $cell['sum'] : $cell['count'];
                $line[$safe] = $val;
                $rowTotal += $val;
                $totals[$safe] = ($totals[$safe] ?? 0) + $val;
            }
            $line['row_total'] = $rowTotal;
            $totals['row_total'] += $rowTotal;
            $out[] = $line;
            if (count($out) >= $limit) {
                break;
            }
        }

        return [
            'columns' => array_map(static function (array $c): array {
                return [
                    'key' => $c['key'],
                    'label' => $c['label'],
                    'numeric' => (bool) $c['numeric'],
                ];
            }, $columns),
            'rows' => $out,
            'totals' => $totals,
            'meta' => ['format' => 'matrix', 'row_count' => count($out)],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function fetchAssociativeRows(DynEntity $entity, array $filters, int $max): array
    {
        $query = DynRecord::query()
            ->where('entity_id', $entity->id)
            ->where('is_deleted', false);

        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }
        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', '%'.$search.'%')
                    ->orWhere('source_external_id', 'like', '%'.$search.'%');
            });
        }
        if (! empty($filters['filter']) && is_array($filters['filter'])) {
            foreach ($filters['filter'] as $fieldName => $value) {
                if ($value === null || $value === '' || is_array($value)) {
                    continue;
                }
                $fieldName = (string) $fieldName;
                $query->whereExists(function ($sub) use ($entity, $fieldName, $value): void {
                    $sub->selectRaw('1')
                        ->from('dyn_record_indexes')
                        ->whereColumn('dyn_record_indexes.record_id', 'dyn_records.id')
                        ->where('dyn_record_indexes.entity_id', $entity->id)
                        ->where('dyn_record_indexes.field_name', $fieldName)
                        ->where('dyn_record_indexes.value_string', 'like', '%'.(string) $value.'%');
                });
            }
        }

        $records = $query->orderByDesc('updated_at')->limit(max(1, min(2000, $max)))->get();
        $out = [];
        foreach ($records as $record) {
            $presented = $this->records->presentListRow($entity, $record);
            $cols = is_array($presented['columns'] ?? null) ? $presented['columns'] : [];
            $out[] = array_merge($cols, [
                'id' => (string) ($presented['id'] ?? ''),
                'title' => (string) ($presented['title'] ?? ''),
                'status' => (string) ($presented['status'] ?? ''),
                'created_at' => $presented['created_at'] ?? null,
                'updated_at' => $presented['updated_at'] ?? null,
            ]);
        }

        return $out;
    }

    /**
     * @param  list<mixed>  $filters
     * @return array<string, mixed>
     */
    private function mapFilters(array $filters): array
    {
        $out = ['filter' => []];
        foreach ($filters as $f) {
            if (! is_array($f)) {
                continue;
            }
            $field = trim((string) ($f['field'] ?? ''));
            $value = $f['value'] ?? '';
            if ($field === '' || $value === null || $value === '') {
                continue;
            }
            if ($field === 'status') {
                $out['status'] = (string) $value;

                continue;
            }
            if ($field === 'search' || $field === 'title') {
                $out['search'] = (string) $value;

                continue;
            }
            $out['filter'][$field] = $value;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function groupValue(array $row, string $field, string $dateGrouping): string
    {
        if ($field === '') {
            return 'All';
        }
        $raw = $row[$field] ?? null;
        if (is_array($raw)) {
            $raw = $raw['title'] ?? $raw['name'] ?? $raw['id'] ?? json_encode($raw);
        }
        $text = trim((string) ($raw ?? ''));
        if ($text === '') {
            return '(blank)';
        }

        if (in_array($dateGrouping, ['day', 'month', 'year', 'week'], true)) {
            $ts = strtotime($text);
            if ($ts !== false) {
                return match ($dateGrouping) {
                    'year' => date('Y', $ts),
                    'month' => date('Y-m', $ts),
                    'week' => date('o-\WW', $ts),
                    default => date('Y-m-d', $ts),
                };
            }
        }

        return $text;
    }

    private function numeric(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $s = is_string($value) ? preg_replace('/[^0-9.\\-]/', '', $value) : '';
        if ($s === null || $s === '' || $s === '-' || $s === '.') {
            return 0.0;
        }

        return (float) $s;
    }
}
