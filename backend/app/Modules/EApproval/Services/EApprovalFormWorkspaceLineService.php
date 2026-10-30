<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalFormField;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalAwaitingApproverFilter;
use App\Modules\EApproval\Support\EApprovalGridCellText;
use App\Modules\EApproval\Support\EApprovalSubmissionSearchFields;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Collection;

/**
 * One row per grid line for a form workspace, using the same filters as the request list.
 */
final class EApprovalFormWorkspaceLineService
{
    private const PARENT_CAP = 200;

    public function __construct(private readonly EApprovalFormWorkspaceService $workspaces) {}

    /**
     * @param  array{
     *   status?: string|null,
     *   from?: string|null,
     *   to?: string|null,
     *   subsidiary?: string|null,
     *   department?: string|null,
     *   mine?: bool,
     *   search?: string,
     *   awaiting_me?: bool
     * }  $filters
     * @return array{data: list<array<string, mixed>>, meta: array<string, int|bool>}
     */
    public function paginate(string $slug, TenantUser $viewer, array $filters, int $page, int $perPage): array
    {
        $context = $this->workspaces->resolveWorkspaceContext($slug, $viewer);
        $form = $context['form'];
        $workspace = $context['workspace'];
        $formIds = $context['form_ids'];
        $forceOwn = (bool) ($filters['mine'] ?? false);
        $canViewAll = $this->workspaces->viewerCanSeeAllInWorkspace($viewer, $workspace) && ! $forceOwn;

        $query = $this->workspaces->scopedSubmissionsQuery($formIds, $viewer, $canViewAll, [
            'status' => $filters['status'] ?? null,
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
            'subsidiary' => $filters['subsidiary'] ?? null,
            'department' => $filters['department'] ?? null,
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            EApprovalSubmissionSearchFields::applySearch($query, $search, $formIds, null);
        }

        if (($filters['awaiting_me'] ?? false) === true) {
            EApprovalAwaitingApproverFilter::apply($query, (string) $viewer->id);
        }

        $parentTotal = (clone $query)->count();
        $grid = $this->gridField($form->id);
        $submissions = (clone $query)
            ->with(['requestor:id,name', 'values.field:id,name,type,options'])
            ->orderByDesc('created_at')
            ->limit(self::PARENT_CAP)
            ->get();

        $lines = $grid === null ? [] : $this->explode($submissions, $grid);
        $total = count($lines);
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        return [
            'data' => array_slice($lines, $offset, $perPage),
            'meta' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'truncated' => $parentTotal > self::PARENT_CAP,
            ],
        ];
    }

    private function gridField(string $formId): ?EApprovalFormField
    {
        return EApprovalFormField::query()
            ->where('form_id', $formId)
            ->where('type', 'grid')
            ->orderByRaw("CASE name WHEN 'expense_lines' THEN 0 WHEN 'activity_lines' THEN 1 WHEN 'forecast_lines' THEN 2 ELSE 3 END")
            ->orderBy('step_order')
            ->first();
    }

    /**
     * @param  Collection<int, EApprovalSubmission>  $submissions
     * @return list<array<string, mixed>>
     */
    private function explode($submissions, EApprovalFormField $grid): array
    {
        $columns = $this->columns($grid);
        $dateIndex = $this->dateColumnIndex($columns);
        $amountIndex = $this->amountColumnIndex($columns);
        $lines = [];

        foreach ($submissions as $submission) {
            $gridValue = $submission->values->first(
                static fn ($value): bool => (string) $value->field_id === (string) $grid->id,
            );
            $rows = $this->rows($gridValue?->value);
            if ($rows === []) {
                continue;
            }

            $subsidiary = '';
            foreach ($submission->values as $value) {
                if ((string) ($value->field?->name ?? '') === 'subsidiary') {
                    $subsidiary = trim((string) ($value->value ?? ''));
                    break;
                }
            }

            foreach ($rows as $index => $row) {
                $lineNo = $index + 1;
                $dateRaw = $dateIndex === null ? '' : ($row[(string) $dateIndex] ?? $row[$dateIndex] ?? '');
                $amountRaw = $amountIndex === null ? '' : ($row[(string) $amountIndex] ?? $row[$amountIndex] ?? '');
                $lines[] = [
                    'id' => (string) $submission->id.':'.$lineNo,
                    'submission_id' => (string) $submission->id,
                    'document_no' => (string) $submission->document_no,
                    'status' => (string) $submission->status,
                    'requestor_name' => (string) ($submission->requestor?->name ?? ''),
                    'subsidiary' => $subsidiary,
                    'date_range' => EApprovalGridCellText::present($dateRaw),
                    'amount' => $this->amountText($amountRaw),
                    'line_no' => $lineNo,
                ];
            }
        }

        return $lines;
    }

    /**
     * @return list<array{index: int, label: string, type: string}>
     */
    private function columns(EApprovalFormField $field): array
    {
        $options = is_array($field->options) ? $field->options : [];
        $raw = [];
        if (isset($options['columns']) && is_array($options['columns'])) {
            $raw = $options['columns'];
        } elseif (array_is_list($options)) {
            $raw = $options;
        }

        $columns = [];
        foreach ($raw as $index => $column) {
            if (is_string($column)) {
                $label = trim($column);
                if ($label === '') {
                    continue;
                }
                $columns[] = ['index' => (int) $index, 'label' => $label, 'type' => 'text'];

                continue;
            }
            if (! is_array($column)) {
                continue;
            }
            $label = trim((string) ($column['label'] ?? $column['name'] ?? ''));
            if ($label === '') {
                continue;
            }
            $columns[] = [
                'index' => (int) $index,
                'label' => $label,
                'type' => strtolower(trim((string) ($column['type'] ?? 'text'))),
            ];
        }

        return $columns;
    }

    /**
     * @param  list<array{index: int, label: string, type: string}>  $columns
     */
    private function dateColumnIndex(array $columns): ?int
    {
        foreach ($columns as $column) {
            if ($column['type'] === 'date_range' || $column['type'] === 'date') {
                return $column['index'];
            }
        }
        foreach ($columns as $column) {
            if (str_contains(strtolower($column['label']), 'date')) {
                return $column['index'];
            }
        }

        return null;
    }

    /**
     * @param  list<array{index: int, label: string, type: string}>  $columns
     */
    private function amountColumnIndex(array $columns): ?int
    {
        foreach ($columns as $column) {
            if (preg_match('/^amount$/i', $column['label']) === 1) {
                return $column['index'];
            }
        }
        foreach ($columns as $column) {
            if (preg_match('/forecast amount/i', $column['label']) === 1) {
                return $column['index'];
            }
        }
        foreach ($columns as $column) {
            if ($column['type'] === 'currency' || $column['type'] === 'number') {
                return $column['index'];
            }
        }

        return null;
    }

    /**
     * @return list<array<int|string, mixed>>
     */
    private function rows(mixed $raw): array
    {
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        $rows = is_array($decoded['rows'] ?? null) ? $decoded['rows'] : (array_is_list($decoded) ? $decoded : []);
        $normalized = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $normalized[] = $row;
            }
        }

        return $normalized;
    }

    private function amountText(mixed $raw): string
    {
        $text = EApprovalGridCellText::present($raw);
        $numeric = str_replace(',', '', $text);
        if ($numeric !== '' && is_numeric($numeric)) {
            return number_format((float) $numeric, 2, '.', ',');
        }

        return $text;
    }
}
