<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalFormField;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalFieldOptionsParser;
use App\Modules\Identity\Models\TenantUser;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class EApprovalSubmissionExportService
{
    /** Sync HTTP export cap (request timeout safety). */
    public const SYNC_MAX_ROWS = 5000;

    /** Soft ceiling for queued/async exports. */
    public const ASYNC_MAX_ROWS = 100_000;

    /** @deprecated Use SYNC_MAX_ROWS */
    public const MAX_ROWS = self::SYNC_MAX_ROWS;

    /** @var list<string> */
    private const SKIP_FIELD_TYPES = [
        'section',
        'page_break',
        'divider',
        'instruction',
        'info',
        'heading',
        'html',
        'file',
        'camera',
        'attachment',
        'signature',
    ];

    /** @var array<string, string> */
    private const BASE_COLUMN_LABELS = [
        'id' => 'ID',
        'document_no' => 'Document No',
        'form_id' => 'Form ID',
        'form_name' => 'Form',
        'requestor_id' => 'Requestor ID',
        'requestor_name' => 'Requestor',
        'requestor_email' => 'Requestor Email',
        'status' => 'Status',
        'current_step' => 'Current Step',
        'parent_submission_id' => 'Parent Submission ID',
        'created_at' => 'Created At',
    ];

    /** Summary workflow columns (always available). */
    private const APPROVAL_SUMMARY_LABELS = [
        'approvers' => 'Approvers',
        'approver_emails' => 'Approver Emails',
        'approval_dates' => 'Approver Dates',
        'approval_statuses' => 'Approval Statuses',
    ];

    /** Soft ceiling for per-step columns when form workflow is unknown. */
    private const MAX_EXPORT_STEPS = 8;

    public function __construct(
        private readonly EApprovalFormValueDisplayService $valueDisplay,
    ) {}

    /**
     * Ordered list of selectable columns for a scope.
     *
     * @return list<array{key: string, label: string, group: string}>
     */
    public function columns(?EApprovalForm $form = null, bool $includeFields = true): array
    {
        $columns = [];

        foreach ($this->baseHeaders() as $key) {
            $columns[] = [
                'key' => $key,
                'label' => self::BASE_COLUMN_LABELS[$key] ?? $key,
                'group' => 'base',
            ];
        }

        foreach (self::APPROVAL_SUMMARY_LABELS as $key => $label) {
            $columns[] = [
                'key' => $key,
                'label' => $label,
                'group' => 'approval',
            ];
        }

        foreach ($this->workflowStepOrders($form) as $stepOrder) {
            $columns[] = [
                'key' => 'step:'.$stepOrder.':approver',
                'label' => 'Step '.$stepOrder.' Approver',
                'group' => 'approval',
            ];
            $columns[] = [
                'key' => 'step:'.$stepOrder.':status',
                'label' => 'Step '.$stepOrder.' Status',
                'group' => 'approval',
            ];
            $columns[] = [
                'key' => 'step:'.$stepOrder.':acted_at',
                'label' => 'Step '.$stepOrder.' Date',
                'group' => 'approval',
            ];
            $columns[] = [
                'key' => 'step:'.$stepOrder.':remarks',
                'label' => 'Step '.$stepOrder.' Remarks',
                'group' => 'approval',
            ];
        }

        if ($form !== null && $includeFields) {
            foreach ($this->exportableFields($form) as $field) {
                $label = trim((string) ($field->label ?? ''));
                $columns[] = [
                    'key' => 'field:'.$field->id,
                    'label' => $label !== '' ? $label : (string) $field->name,
                    'group' => 'field',
                ];
            }
        }

        return $columns;
    }

    /**
     * @param  list<string>|null  $selectedKeys
     * @return list<string>
     */
    public function headers(?EApprovalForm $form = null, bool $includeFields = true, ?array $selectedKeys = null): array
    {
        $columns = $this->resolveColumns($form, $includeFields, $selectedKeys);

        return array_map(static fn (array $column): string => $column['label'], $columns);
    }

    /**
     * @param  array{status?: string, statuses?: list<string>, form_id?: string, form_ids?: list<string>, from?: string, to?: string, search?: string}  $filters
     * @param  array{viewer?: TenantUser, can_view_all?: bool, form?: EApprovalForm, include_fields?: bool}|null  $scope
     * @param  list<string>|null  $selectedKeys
     * @return Generator<int, list<string>>
     */
    public function rows(array $filters, ?array $scope = null, ?array $selectedKeys = null, ?int $rowLimit = null): Generator
    {
        $limit = $rowLimit ?? self::SYNC_MAX_ROWS;
        $form = $scope['form'] ?? null;
        $includeFields = ($scope['include_fields'] ?? true) === true;
        $exportFields = $form !== null && $includeFields ? $this->exportableFields($form) : collect();

        $columns = $this->resolveColumns($form, $includeFields, $selectedKeys);
        $orderedKeys = array_map(static fn (array $column): string => $column['key'], $columns);
        $needsApprovals = $this->selectionNeedsApprovals($orderedKeys);

        $query = $this->buildQuery($filters, $scope, true, $limit);
        $with = [];
        if ($exportFields->isNotEmpty()) {
            $with[] = 'values.field';
        }
        if ($needsApprovals) {
            $with[] = 'approvals.approver:id,name,email';
            $with[] = 'approvals.step:id,step_order';
        }
        if ($with !== []) {
            $query->with($with);
        }

        foreach ($query->lazy($limit) as $submission) {
            $keyed = $this->submissionRowMap($submission, $exportFields, $needsApprovals, $form);
            yield array_map(static fn (string $key): string => $keyed[$key] ?? '', $orderedKeys);
        }
    }

    /**
     * Total matching rows (uncapped) so callers can flag truncated exports.
     *
     * @param  array{status?: string, statuses?: list<string>, form_id?: string, form_ids?: list<string>, from?: string, to?: string, search?: string}  $filters
     * @param  array{viewer?: TenantUser, can_view_all?: bool, form?: EApprovalForm, include_fields?: bool}|null  $scope
     */
    public function countMatching(array $filters, ?array $scope = null): int
    {
        return $this->buildQuery($filters, $scope, false)->count();
    }

    /**
     * Repeatable line-item (grid) fields available for line-item exports.
     *
     * @return Collection<int, EApprovalFormField>
     */
    public function gridFields(EApprovalForm $form): Collection
    {
        return EApprovalFormField::query()
            ->where('form_id', $form->id)
            ->where('type', 'grid')
            ->orderBy('step_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Resolve the grid field to explode into line items (by id, else the first grid field).
     */
    public function resolveGridField(EApprovalForm $form, ?string $fieldId = null): ?EApprovalFormField
    {
        $grids = $this->gridFields($form);

        if ($fieldId !== null && $fieldId !== '') {
            $match = $grids->first(static fn (EApprovalFormField $field): bool => (string) $field->id === $fieldId);
            if ($match !== null) {
                return $match;
            }
        }

        return $grids->first();
    }

    /**
     * Column layout for a line-item export: parent columns repeated + one column per grid column.
     *
     * @return list<array{key: string, label: string, group: string}>
     */
    public function lineItemColumns(EApprovalForm $form, EApprovalFormField $gridField): array
    {
        $columns = [];

        foreach (['document_no', 'form_name', 'requestor_name', 'status', 'created_at'] as $key) {
            $columns[] = [
                'key' => $key,
                'label' => self::BASE_COLUMN_LABELS[$key] ?? $key,
                'group' => 'parent',
            ];
        }

        $columns[] = ['key' => 'line_no', 'label' => 'Line No', 'group' => 'line'];

        foreach ($this->gridColumnLabels($gridField) as $index => $label) {
            $columns[] = [
                'key' => 'cell:'.$index,
                'label' => $label !== '' ? $label : 'Column '.($index + 1),
                'group' => 'line',
            ];
        }

        if ($this->lineTotalIndices($gridField) !== null) {
            $columns[] = ['key' => 'line_total', 'label' => 'Line Total', 'group' => 'line'];
        }

        return $columns;
    }

    /**
     * @return list<string>
     */
    public function lineItemHeaders(EApprovalForm $form, EApprovalFormField $gridField): array
    {
        return array_map(
            static fn (array $column): string => $column['label'],
            $this->lineItemColumns($form, $gridField),
        );
    }

    /**
     * One row per grid row across all matching submissions (parent columns repeated).
     *
     * @param  array{status?: string, statuses?: list<string>, form_id?: string, form_ids?: list<string>, from?: string, to?: string, search?: string}  $filters
     * @param  array{viewer?: TenantUser, can_view_all?: bool, form?: EApprovalForm, include_fields?: bool}|null  $scope
     * @return Generator<int, list<string>>
     */
    public function lineItemRows(array $filters, ?array $scope, EApprovalForm $form, EApprovalFormField $gridField, ?int $rowLimit = null): Generator
    {
        $limit = $rowLimit ?? self::SYNC_MAX_ROWS;
        $columns = $this->lineItemColumns($form, $gridField);
        $orderedKeys = array_map(static fn (array $column): string => $column['key'], $columns);
        $columnCount = count($this->gridColumnLabels($gridField));
        $totalIndices = $this->lineTotalIndices($gridField);

        $query = $this->buildQuery($filters, $scope, true, $limit)->with(['values' => static function ($q) use ($gridField): void {
            $q->where('field_id', $gridField->id);
        }]);

        foreach ($query->lazy($limit) as $submission) {
            $base = $this->submissionRowMap($submission, collect(), false, $form);
            $gridValue = $submission->relationLoaded('values')
                ? $submission->values->first(static fn ($value) => (string) $value->field_id === (string) $gridField->id)
                : null;

            $rows = $this->parseGridRows($gridValue?->value, $columnCount);
            if ($rows === []) {
                continue;
            }

            foreach ($rows as $rowIndex => $cells) {
                $keyed = $base;
                $keyed['line_no'] = (string) ($rowIndex + 1);

                for ($i = 0; $i < $columnCount; $i++) {
                    $keyed['cell:'.$i] = $cells[(string) $i] ?? '';
                }

                if ($totalIndices !== null) {
                    $keyed['line_total'] = $this->computeLineTotal($cells, $totalIndices);
                }

                yield array_map(static fn (string $key): string => $keyed[$key] ?? '', $orderedKeys);
            }
        }
    }

    /**
     * @param  list<string>|null  $selectedKeys
     * @return list<array{key: string, label: string, group: string}>
     */
    private function resolveColumns(?EApprovalForm $form, bool $includeFields, ?array $selectedKeys): array
    {
        $columns = $this->columns($form, $includeFields);

        if ($selectedKeys === null || $selectedKeys === []) {
            return $columns;
        }

        $wanted = array_flip($selectedKeys);
        $filtered = array_values(array_filter(
            $columns,
            static fn (array $column): bool => isset($wanted[$column['key']]),
        ));

        // Never emit an empty file: fall back to all columns when the selection matched nothing.
        return $filtered !== [] ? $filtered : $columns;
    }

    /**
     * @return list<string>
     */
    private function baseHeaders(): array
    {
        return array_keys(self::BASE_COLUMN_LABELS);
    }

    /**
     * @param  array{status?: string, statuses?: list<string>, form_id?: string, form_ids?: list<string>, from?: string, to?: string, search?: string}  $filters
     * @param  array{viewer?: TenantUser, can_view_all?: bool, form?: EApprovalForm, include_fields?: bool}|null  $scope
     */
    private function buildQuery(array $filters, ?array $scope, bool $applyLimit = true, ?int $rowLimit = null): Builder
    {
        $query = EApprovalSubmission::query()
            ->with(['form:id,name', 'requestor:id,name,email'])
            ->orderByDesc('created_at');

        if ($applyLimit) {
            $query->limit($rowLimit ?? self::SYNC_MAX_ROWS);
        }

        $statuses = $this->normalizeStatuses($filters);
        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }

        if (! empty($filters['form_ids']) && is_array($filters['form_ids'])) {
            $query->whereIn('form_id', $filters['form_ids']);
        } elseif (! empty($filters['form_id'])) {
            $query->where('form_id', $filters['form_id']);
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        if (! empty($filters['search'])) {
            $like = '%'.addcslashes((string) $filters['search'], '%_\\').'%';
            $query->where(static function (Builder $q) use ($like): void {
                $q->where('document_no', 'like', $like)
                    ->orWhereHas('form', static fn ($f) => $f->where('name', 'like', $like))
                    ->orWhereHas('requestor', static fn ($u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like));
            });
        }

        if ($scope !== null && isset($scope['viewer']) && ($scope['can_view_all'] ?? true) !== true) {
            $this->applyViewerScope($query, $scope['viewer']);
        }

        return $query;
    }

    /**
     * @param  array{status?: string, statuses?: list<string>}  $filters
     * @return list<string>
     */
    private function normalizeStatuses(array $filters): array
    {
        $statuses = [];

        if (! empty($filters['statuses']) && is_array($filters['statuses'])) {
            $statuses = $filters['statuses'];
        } elseif (! empty($filters['status'])) {
            $statuses = [$filters['status']];
        }

        $statuses = array_values(array_filter(
            array_map(static fn ($status): string => (string) $status, $statuses),
            static fn (string $status): bool => $status !== '' && $status !== 'all',
        ));

        return $statuses;
    }

    private function applyViewerScope(Builder $query, TenantUser $viewer): void
    {
        $query->where(static function (Builder $scoped) use ($viewer): void {
            $scoped->where('requestor_id', $viewer->id)
                ->orWhereIn('id', EApprovalRequestApproval::query()
                    ->where('approver_id', $viewer->id)
                    ->select('submission_id'));
        });
    }

    /**
     * @param  Collection<int, EApprovalFormField>  $exportFields
     * @return array<string, string>
     */
    private function submissionRowMap(
        EApprovalSubmission $submission,
        Collection $exportFields,
        bool $includeApprovals = true,
        ?EApprovalForm $form = null,
    ): array {
        $row = [
            'id' => (string) $submission->id,
            'document_no' => (string) $submission->document_no,
            'form_id' => (string) $submission->form_id,
            'form_name' => (string) ($submission->form?->name ?? ''),
            'requestor_id' => (string) $submission->requestor_id,
            'requestor_name' => (string) ($submission->requestor?->name ?? ''),
            'requestor_email' => (string) ($submission->requestor?->email ?? ''),
            'status' => (string) $submission->status,
            'current_step' => (string) $submission->current_step,
            'parent_submission_id' => (string) ($submission->parent_submission_id ?? ''),
            'created_at' => $submission->created_at?->toIso8601String() ?? '',
        ];

        if ($includeApprovals) {
            $row = array_merge($row, $this->approvalColumnsForSubmission($submission, $form));
        }

        if ($exportFields->isEmpty()) {
            return $row;
        }

        $values = $submission->relationLoaded('values') ? $submission->values : collect();
        $valuesByFieldId = $values->keyBy(static fn ($value) => (string) $value->field_id);
        $usersById = $this->valueDisplay->approverUsersById($values);

        foreach ($exportFields as $field) {
            $value = $valuesByFieldId->get((string) $field->id);
            if ($value === null) {
                $row['field:'.$field->id] = '';

                continue;
            }

            $display = $this->valueDisplay->resolveDisplayValue(
                $field->type,
                $value->value,
                $usersById,
                is_array($field->options) ? $field->options : null,
            );
            $row['field:'.$field->id] = (string) ($display ?? '');
        }

        return $row;
    }

    /**
     * @return array<string, string>
     */
    private function approvalColumnsForSubmission(EApprovalSubmission $submission, ?EApprovalForm $form): array
    {
        $approvals = $submission->relationLoaded('approvals')
            ? $submission->approvals
            : $submission->approvals()->with(['approver:id,name,email', 'step:id,step_order'])->get();

        $sorted = $approvals
            ->sortBy(static fn (EApprovalRequestApproval $a): int => (int) ($a->step?->step_order ?? 0))
            ->values();

        $names = [];
        $emails = [];
        $dates = [];
        $statuses = [];
        $byStep = [];

        foreach ($sorted as $approval) {
            $stepOrder = (int) ($approval->step?->step_order ?? 0);
            $name = trim((string) ($approval->approver?->name ?? ''));
            $email = trim((string) ($approval->approver?->email ?? ''));
            $actedAt = $approval->acted_at?->toIso8601String() ?? '';
            $status = (string) $approval->status;
            $remarks = trim((string) ($approval->remarks ?? ''));

            if ($name !== '' || $email !== '' || $actedAt !== '' || $status !== 'pending') {
                $names[] = $name !== '' ? $name : ($email !== '' ? $email : 'Unknown');
                if ($email !== '') {
                    $emails[] = $email;
                }
                if ($actedAt !== '') {
                    $dates[] = $actedAt;
                }
                $statuses[] = ($stepOrder > 0 ? 'Step '.$stepOrder.': ' : '').$status
                    .($name !== '' ? ' ('.$name.')' : '');
            }

            if ($stepOrder > 0 && ! isset($byStep[$stepOrder])) {
                $byStep[$stepOrder] = [
                    'approver' => $name !== '' ? $name : $email,
                    'status' => $status,
                    'acted_at' => $actedAt,
                    'remarks' => $remarks,
                ];
            }
        }

        $row = [
            'approvers' => implode('; ', $names),
            'approver_emails' => implode('; ', array_values(array_unique($emails))),
            'approval_dates' => implode('; ', $dates),
            'approval_statuses' => implode('; ', $statuses),
        ];

        foreach ($this->workflowStepOrders($form) as $stepOrder) {
            $step = $byStep[$stepOrder] ?? null;
            $row['step:'.$stepOrder.':approver'] = (string) ($step['approver'] ?? '');
            $row['step:'.$stepOrder.':status'] = (string) ($step['status'] ?? '');
            $row['step:'.$stepOrder.':acted_at'] = (string) ($step['acted_at'] ?? '');
            $row['step:'.$stepOrder.':remarks'] = (string) ($step['remarks'] ?? '');
        }

        return $row;
    }

    /**
     * @param  list<string>  $orderedKeys
     */
    private function selectionNeedsApprovals(array $orderedKeys): bool
    {
        foreach ($orderedKeys as $key) {
            if (isset(self::APPROVAL_SUMMARY_LABELS[$key]) || str_starts_with($key, 'step:')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<int>
     */
    private function workflowStepOrders(?EApprovalForm $form): array
    {
        if ($form !== null) {
            $form->loadMissing(['workflowTemplate.steps']);
            $steps = $form->workflowTemplate?->steps;
            if ($steps !== null && $steps->isNotEmpty()) {
                $orders = $steps
                    ->pluck('step_order')
                    ->map(static fn ($order): int => (int) $order)
                    ->filter(static fn (int $order): bool => $order > 0)
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                if ($orders !== []) {
                    return $orders;
                }
            }
        }

        return range(1, min(5, self::MAX_EXPORT_STEPS));
    }

    /**
     * @return Collection<int, EApprovalFormField>
     */
    private function exportableFields(EApprovalForm $form): Collection
    {
        return EApprovalFormField::query()
            ->where('form_id', $form->id)
            ->whereNotIn('type', self::SKIP_FIELD_TYPES)
            ->orderBy('step_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return list<string>
     */
    private function gridColumnLabels(EApprovalFormField $gridField): array
    {
        return EApprovalFieldOptionsParser::gridColumns(
            is_array($gridField->options) ? $gridField->options : null,
        );
    }

    /**
     * Decode a grid value into normalized index-keyed rows.
     *
     * @return list<array<string, string>>
     */
    private function parseGridRows(?string $raw, int $columnCount): array
    {
        if ($raw === null || trim($raw) === '') {
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
            if (! is_array($row)) {
                continue;
            }

            $next = [];
            for ($i = 0; $i < $columnCount; $i++) {
                $key = (string) $i;
                $value = $row[$key] ?? $row[$i] ?? '';
                $next[$key] = is_scalar($value) ? trim((string) $value) : '';
            }
            $normalized[] = $next;
        }

        return $normalized;
    }

    /**
     * Resolve which grid columns yield a per-line total.
     *
     * @return array{amount?: int, qty?: int, unit?: int, discount?: int}|null
     */
    private function lineTotalIndices(EApprovalFormField $gridField): ?array
    {
        $labels = array_map(
            static fn (string $label): string => strtolower(trim($label)),
            $this->gridColumnLabels($gridField),
        );

        $find = static function (array $needles) use ($labels): ?int {
            foreach ($labels as $index => $label) {
                foreach ($needles as $needle) {
                    if ($label === $needle || str_contains($label, $needle)) {
                        return $index;
                    }
                }
            }

            return null;
        };

        $amount = $find(['amount', 'line total']);
        if ($amount !== null) {
            return ['amount' => $amount];
        }

        $qty = $find(['qty', 'quantity']);
        $unit = $find(['unit price', 'price', 'rate']);
        if ($qty !== null && $unit !== null) {
            $discount = $find(['discount']);

            return $discount !== null
                ? ['qty' => $qty, 'unit' => $unit, 'discount' => $discount]
                : ['qty' => $qty, 'unit' => $unit];
        }

        return null;
    }

    /**
     * @param  array<string, string>  $cells
     * @param  array{amount?: int, qty?: int, unit?: int, discount?: int}  $indices
     */
    private function computeLineTotal(array $cells, array $indices): string
    {
        if (isset($indices['amount'])) {
            $amount = $this->parseAmount($cells[(string) $indices['amount']] ?? '');

            return number_format($amount, 2, '.', '');
        }

        $qty = $this->parseAmount($cells[(string) ($indices['qty'] ?? -1)] ?? '');
        $unit = $this->parseAmount($cells[(string) ($indices['unit'] ?? -1)] ?? '');
        $discount = isset($indices['discount'])
            ? $this->parseAmount($cells[(string) $indices['discount']] ?? '')
            : 0.0;

        return number_format(max(0, $qty * $unit - $discount), 2, '.', '');
    }

    private function parseAmount(string $raw): float
    {
        $trimmed = trim(str_replace(',', '', $raw));
        if ($trimmed === '' || ! is_numeric($trimmed)) {
            return 0.0;
        }

        return (float) $trimmed;
    }
}
