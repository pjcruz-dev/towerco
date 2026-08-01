<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Jobs\GenerateEApprovalExportJob;
use App\Modules\EApproval\Models\EApprovalExportHistory;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalReportDefinition;
use App\Modules\EApproval\Support\EApprovalExportHistoryStatus;
use App\Modules\EApproval\Support\SimpleXlsxWriter;
use App\Modules\Identity\Models\TenantUser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class EApprovalReportService
{
    public function __construct(
        private readonly EApprovalSubmissionExportService $export,
        private readonly EApprovalFileStorageService $files,
        private readonly EApprovalAuditLogger $audit,
    ) {}

    /**
     * @return Collection<int, EApprovalReportDefinition>
     */
    public function listForUser(TenantUser $user): Collection
    {
        return EApprovalReportDefinition::query()
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array{
     *     name: string,
     *     description?: string|null,
     *     filters?: array<string, mixed>|null,
     *     columns?: list<string>|null,
     *     layout?: string,
     *     format?: string,
     *     grid_field_id?: string|null,
     *     schedule?: array<string, mixed>|null
     * }  $data
     */
    public function create(TenantUser $user, array $data): EApprovalReportDefinition
    {
        return EApprovalReportDefinition::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'filters_json' => $this->normalizeFilters($data['filters'] ?? null),
            'columns_json' => $this->normalizeColumns($data['columns'] ?? null),
            'layout' => $data['layout'] ?? 'submissions',
            'format' => $data['format'] ?? 'csv',
            'grid_field_id' => $data['grid_field_id'] ?? null,
            'schedule_json' => $this->normalizeSchedule($data['schedule'] ?? null),
        ]);
    }

    /**
     * @param  array{
     *     name?: string,
     *     description?: string|null,
     *     filters?: array<string, mixed>|null,
     *     columns?: list<string>|null,
     *     layout?: string,
     *     format?: string,
     *     grid_field_id?: string|null,
     *     schedule?: array<string, mixed>|null
     * }  $data
     */
    public function update(EApprovalReportDefinition $report, array $data): EApprovalReportDefinition
    {
        $report->fill([
            'name' => array_key_exists('name', $data) ? trim((string) $data['name']) : $report->name,
            'description' => array_key_exists('description', $data) ? $data['description'] : $report->description,
            'filters_json' => array_key_exists('filters', $data)
                ? $this->normalizeFilters($data['filters'])
                : $report->filters_json,
            'columns_json' => array_key_exists('columns', $data)
                ? $this->normalizeColumns($data['columns'])
                : $report->columns_json,
            'layout' => $data['layout'] ?? $report->layout,
            'format' => $data['format'] ?? $report->format,
            'grid_field_id' => array_key_exists('grid_field_id', $data)
                ? $data['grid_field_id']
                : $report->grid_field_id,
            'schedule_json' => array_key_exists('schedule', $data)
                ? $this->normalizeSchedule(array_merge(
                    is_array($report->schedule_json) ? $report->schedule_json : [],
                    is_array($data['schedule']) ? $data['schedule'] : [],
                ))
                : $report->schedule_json,
        ]);
        $report->save();

        return $report->refresh();
    }

    public function delete(EApprovalReportDefinition $report): void
    {
        $report->delete();
    }

    /**
     * @return Collection<int, EApprovalExportHistory>
     */
    public function historyForUser(TenantUser $user, int $limit = 50): Collection
    {
        return EApprovalExportHistory::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(max(1, min($limit, 200)))
            ->get();
    }

    /**
     * @return array{
     *     path: string,
     *     filename: string,
     *     content_type: string,
     *     history: EApprovalExportHistory,
     *     truncated: bool,
     *     matched_rows: int,
     *     exported_rows: int
     * }
     */
    public function run(
        TenantUser $user,
        EApprovalReportDefinition $report,
        string $triggeredBy = 'manual',
        ?int $rowLimit = null,
        bool $persistFile = false,
    ): array {
        $limit = $rowLimit ?? EApprovalSubmissionExportService::SYNC_MAX_ROWS;
        $result = $this->generateExportFile(
            filters: is_array($report->filters_json) ? $report->filters_json : [],
            columns: is_array($report->columns_json) ? $report->columns_json : null,
            layout: (string) $report->layout,
            format: (string) $report->format,
            gridFieldId: $report->grid_field_id,
            nameHint: (string) $report->name,
            rowLimit: $limit,
        );

        $history = $this->recordHistory(
            user: $user,
            report: $report,
            name: (string) $report->name,
            filters: is_array($report->filters_json) ? $report->filters_json : [],
            columns: is_array($report->columns_json) ? $report->columns_json : null,
            layout: (string) $report->layout,
            format: (string) $report->format,
            gridFieldId: $report->grid_field_id,
            matchedRows: $result['matched_rows'],
            exportedRows: $result['exported_rows'],
            truncated: $result['truncated'],
            triggeredBy: $triggeredBy,
            filename: $result['filename'],
            status: EApprovalExportHistoryStatus::COMPLETED,
        );

        if ($persistFile) {
            $this->attachStoredFile($history, $result['path'], $result['content_type'], $user);
        }

        $report->forceFill(['last_run_at' => now()])->save();

        $this->audit->log(
            'report_exported',
            (string) $report->id,
            sprintf(
                'Report "%s" exported as %s (%d/%d rows%s)',
                $report->name,
                strtoupper((string) $report->format),
                $result['exported_rows'],
                $result['matched_rows'],
                $result['truncated'] ? ', truncated' : '',
            ),
            $user,
        );

        return [
            'path' => $result['path'],
            'filename' => $result['filename'],
            'content_type' => $result['content_type'],
            'history' => $history->refresh(),
            'truncated' => $result['truncated'],
            'matched_rows' => $result['matched_rows'],
            'exported_rows' => $result['exported_rows'],
        ];
    }

    /**
     * Queue a saved-report export when the sync row cap would be exceeded (or async is forced).
     */
    public function queueReportRun(
        TenantUser $user,
        EApprovalReportDefinition $report,
        string $triggeredBy = 'manual',
    ): EApprovalExportHistory {
        $filters = is_array($report->filters_json) ? $report->filters_json : [];
        $matched = $this->countForFilters($filters);

        $history = $this->recordHistory(
            user: $user,
            report: $report,
            name: (string) $report->name,
            filters: $filters,
            columns: is_array($report->columns_json) ? $report->columns_json : null,
            layout: (string) $report->layout,
            format: (string) $report->format,
            gridFieldId: $report->grid_field_id,
            matchedRows: $matched,
            exportedRows: 0,
            truncated: false,
            triggeredBy: $triggeredBy,
            filename: $this->plannedFilename((string) $report->name, (string) $report->layout, (string) $report->format),
            status: EApprovalExportHistoryStatus::QUEUED,
            remarks: sprintf(
                'Queued for async export (up to %s rows).',
                number_format(EApprovalSubmissionExportService::ASYNC_MAX_ROWS),
            ),
        );

        $this->dispatchExportJob((string) $history->id);

        return $history;
    }

    /**
     * Queue an ad-hoc builder export for async generation + download.
     *
     * @param  array<string, mixed>  $filters
     * @param  list<string>|null  $columns
     */
    public function queueAdHocExport(
        TenantUser $user,
        array $filters,
        ?array $columns,
        string $layout,
        string $format,
        ?string $gridFieldId,
        int $matchedRows,
        string $filename,
    ): EApprovalExportHistory {
        $history = $this->recordHistory(
            user: $user,
            report: null,
            name: 'Ad-hoc export',
            filters: $filters,
            columns: $columns,
            layout: $layout,
            format: $format,
            gridFieldId: $gridFieldId,
            matchedRows: $matchedRows,
            exportedRows: 0,
            truncated: false,
            triggeredBy: 'manual',
            filename: $filename,
            status: EApprovalExportHistoryStatus::QUEUED,
            remarks: sprintf(
                'Queued for async export (up to %s rows).',
                number_format(EApprovalSubmissionExportService::ASYNC_MAX_ROWS),
            ),
        );

        $this->dispatchExportJob((string) $history->id);

        return $history;
    }

    public function shouldQueue(int $matchedRows, bool $forceAsync = false): bool
    {
        return $forceAsync || $matchedRows > EApprovalSubmissionExportService::SYNC_MAX_ROWS;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function countMatchingFilters(array $filters): int
    {
        return $this->countForFilters($filters);
    }

    public function findHistoryForUser(TenantUser $user, string $historyId): EApprovalExportHistory
    {
        $history = EApprovalExportHistory::query()->findOrFail($historyId);
        abort_unless((string) $history->user_id === (string) $user->id, 403);

        return $history;
    }

    /**
     * Worker entry: generate the file for a queued history row and store it.
     */
    public function processQueuedHistory(string $historyId): void
    {
        $history = EApprovalExportHistory::query()->find($historyId);
        if ($history === null) {
            return;
        }

        if ($history->status === EApprovalExportHistoryStatus::COMPLETED && $history->file_path) {
            return;
        }

        $history->forceFill([
            'status' => EApprovalExportHistoryStatus::PROCESSING,
            'error_message' => null,
        ])->save();

        $user = $history->owner;
        if ($user === null) {
            $history->forceFill([
                'status' => EApprovalExportHistoryStatus::FAILED,
                'error_message' => 'Export owner is missing.',
            ])->save();

            return;
        }

        try {
            $result = $this->generateExportFile(
                filters: is_array($history->filters_json) ? $history->filters_json : [],
                columns: is_array($history->columns_json) ? $history->columns_json : null,
                layout: (string) $history->layout,
                format: (string) $history->format,
                gridFieldId: $history->grid_field_id,
                nameHint: (string) ($history->name ?: 'export'),
                rowLimit: EApprovalSubmissionExportService::ASYNC_MAX_ROWS,
            );

            $this->attachStoredFile($history, $result['path'], $result['content_type'], $user);

            $history->forceFill([
                'matched_rows' => $result['matched_rows'],
                'exported_rows' => $result['exported_rows'],
                'truncated' => $result['truncated'],
                'filename' => $result['filename'],
                'status' => EApprovalExportHistoryStatus::COMPLETED,
                'error_message' => null,
                'remarks' => $result['truncated']
                    ? sprintf(
                        'Truncated at %d of %d matching rows (async cap %d).',
                        $result['exported_rows'],
                        $result['matched_rows'],
                        EApprovalSubmissionExportService::ASYNC_MAX_ROWS,
                    )
                    : null,
            ])->save();

            if ($history->report_definition_id) {
                EApprovalReportDefinition::query()
                    ->whereKey($history->report_definition_id)
                    ->update(['last_run_at' => now()]);
            }

            $this->audit->log(
                'report_exported',
                $history->report_definition_id,
                sprintf(
                    'Async export ready (%s, %d/%d rows%s)',
                    strtoupper((string) $history->format),
                    $result['exported_rows'],
                    $result['matched_rows'],
                    $result['truncated'] ? ', truncated' : '',
                ),
                $user,
            );
        } catch (\Throwable $e) {
            $history->forceFill([
                'status' => EApprovalExportHistoryStatus::FAILED,
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
                'remarks' => 'Async export failed.',
            ])->save();

            Log::error('e_approval.async_export_failed', [
                'history_id' => $historyId,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Delete expired stored export files and clear download metadata.
     */
    public function pruneExpiredExports(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $pruned = 0;

        $rows = EApprovalExportHistory::query()
            ->whereNotNull('file_path')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->limit(500)
            ->get();

        foreach ($rows as $row) {
            $disk = (string) ($row->disk ?: config('toweros.tenant_files.disk', 'tenant_files'));
            $path = (string) $row->file_path;
            if ($path !== '' && Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
            $row->forceFill([
                'file_path' => null,
                'disk' => null,
                'content_type' => null,
                'byte_size' => null,
                'remarks' => trim((string) ($row->remarks.' Export file expired and was removed.')),
            ])->save();
            $pruned++;
        }

        return $pruned;
    }

    /**
     * Record a manual ad-hoc export (from the builder) without a saved definition.
     *
     * @param  array<string, mixed>  $filters
     * @param  list<string>|null  $columns
     */
    public function recordAdHocExport(
        TenantUser $user,
        array $filters,
        ?array $columns,
        string $layout,
        string $format,
        ?string $gridFieldId,
        int $matchedRows,
        int $exportedRows,
        bool $truncated,
        string $filename,
    ): EApprovalExportHistory {
        $history = $this->recordHistory(
            user: $user,
            report: null,
            name: 'Ad-hoc export',
            filters: $filters,
            columns: $columns,
            layout: $layout,
            format: $format,
            gridFieldId: $gridFieldId,
            matchedRows: $matchedRows,
            exportedRows: $exportedRows,
            truncated: $truncated,
            triggeredBy: 'manual',
            filename: $filename,
            status: EApprovalExportHistoryStatus::COMPLETED,
        );

        $this->audit->log(
            'report_exported',
            null,
            sprintf(
                'Ad-hoc export (%s, %d/%d rows%s)',
                strtoupper($format),
                $exportedRows,
                $matchedRows,
                $truncated ? ', truncated' : '',
            ),
            $user,
        );

        return $history;
    }

    /**
     * @return list<EApprovalReportDefinition>
     */
    public function dueScheduledReports(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $due = [];

        foreach (EApprovalReportDefinition::query()->orderBy('name')->get() as $report) {
            if ($this->isDue($report, $now)) {
                $due[] = $report;
            }
        }

        return $due;
    }

    public function isDue(EApprovalReportDefinition $report, CarbonImmutable $now): bool
    {
        $schedule = is_array($report->schedule_json) ? $report->schedule_json : [];
        if (($schedule['enabled'] ?? false) !== true) {
            return false;
        }

        $frequency = (string) ($schedule['frequency'] ?? 'daily');
        $hour = (int) ($schedule['hour'] ?? 8);
        if ($now->hour !== $hour) {
            return false;
        }

        if ($frequency === 'weekly') {
            $day = (int) ($schedule['day_of_week'] ?? 1);
            if ($now->dayOfWeek !== $day) {
                return false;
            }
        }

        if ($report->last_run_at !== null) {
            $last = CarbonImmutable::parse($report->last_run_at);
            // Skip if already executed for this calendar day (covers daily + weekly windows).
            if ($last->isSameDay($now)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Run a scheduled report: generate file, record history, notify recipients (log + optional mail hook).
     *
     * @return array{history: EApprovalExportHistory, recipients: list<string>}
     */
    public function runScheduled(EApprovalReportDefinition $report): array
    {
        $owner = $report->owner;
        if ($owner === null) {
            throw new \RuntimeException('Scheduled report owner is missing.');
        }

        $matched = $this->countForFilters(is_array($report->filters_json) ? $report->filters_json : []);
        if ($this->shouldQueue($matched)) {
            $history = $this->queueReportRun($owner, $report, 'schedule');
            $schedule = is_array($report->schedule_json) ? $report->schedule_json : [];
            $recipients = array_values(array_filter(
                array_map('strval', is_array($schedule['recipients'] ?? null) ? $schedule['recipients'] : []),
                static fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
            ));

            return [
                'history' => $history,
                'recipients' => $recipients,
            ];
        }

        $result = $this->run(
            $owner,
            $report,
            'schedule',
            EApprovalSubmissionExportService::SYNC_MAX_ROWS,
            persistFile: true,
        );
        $schedule = is_array($report->schedule_json) ? $report->schedule_json : [];
        $recipients = array_values(array_filter(
            array_map('strval', is_array($schedule['recipients'] ?? null) ? $schedule['recipients'] : []),
            static fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
        ));

        if ($recipients !== []) {
            Log::info('e_approval.scheduled_report_ready', [
                'report_id' => $report->id,
                'report_name' => $report->name,
                'filename' => $result['filename'],
                'recipients' => $recipients,
                'matched_rows' => $result['matched_rows'],
                'exported_rows' => $result['exported_rows'],
                'history_id' => $result['history']->id,
            ]);
        }

        // Temp file was copied into tenant storage; remove the local temp path.
        if (is_file($result['path'])) {
            @unlink($result['path']);
        }

        return [
            'history' => $result['history'],
            'recipients' => $recipients,
        ];
    }

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     description: string|null,
     *     filters: array<string, mixed>,
     *     columns: list<string>|null,
     *     layout: string,
     *     format: string,
     *     grid_field_id: string|null,
     *     schedule: array<string, mixed>|null,
     *     last_run_at: string|null,
     *     created_at: string|null,
     *     updated_at: string|null
     * }
     */
    public function present(EApprovalReportDefinition $report): array
    {
        return [
            'id' => (string) $report->id,
            'name' => (string) $report->name,
            'description' => $report->description,
            'filters' => is_array($report->filters_json) ? $report->filters_json : [],
            'columns' => is_array($report->columns_json) ? array_values($report->columns_json) : null,
            'layout' => (string) $report->layout,
            'format' => (string) $report->format,
            'grid_field_id' => $report->grid_field_id,
            'schedule' => is_array($report->schedule_json) ? $report->schedule_json : null,
            'last_run_at' => $report->last_run_at?->toIso8601String(),
            'created_at' => $report->created_at?->toIso8601String(),
            'updated_at' => $report->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{
     *     id: string,
     *     report_definition_id: string|null,
     *     name: string|null,
     *     filters: array<string, mixed>,
     *     columns: list<string>|null,
     *     layout: string,
     *     format: string,
     *     grid_field_id: string|null,
     *     matched_rows: int,
     *     exported_rows: int,
     *     truncated: bool,
     *     status: string,
     *     triggered_by: string,
     *     filename: string|null,
     *     remarks: string|null,
     *     error_message: string|null,
     *     expires_at: string|null,
     *     download: array{url: string, stream: bool}|null,
     *     created_at: string|null
     * }
     */
    public function presentHistory(EApprovalExportHistory $history): array
    {
        $download = null;
        if ($history->status === EApprovalExportHistoryStatus::COMPLETED) {
            $download = $this->files->exportDownloadInfo($history);
        }

        return [
            'id' => (string) $history->id,
            'report_definition_id' => $history->report_definition_id,
            'name' => $history->name,
            'filters' => is_array($history->filters_json) ? $history->filters_json : [],
            'columns' => is_array($history->columns_json) ? array_values($history->columns_json) : null,
            'layout' => (string) $history->layout,
            'format' => (string) $history->format,
            'grid_field_id' => $history->grid_field_id,
            'matched_rows' => (int) $history->matched_rows,
            'exported_rows' => (int) $history->exported_rows,
            'truncated' => (bool) $history->truncated,
            'status' => (string) $history->status,
            'triggered_by' => (string) $history->triggered_by,
            'filename' => $history->filename,
            'remarks' => $history->remarks,
            'error_message' => $history->error_message,
            'expires_at' => $history->expires_at?->toIso8601String(),
            'download' => $download,
            'created_at' => $history->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>|null  $columns
     * @return array{
     *     path: string,
     *     filename: string,
     *     content_type: string,
     *     matched_rows: int,
     *     exported_rows: int,
     *     truncated: bool
     * }
     */
    public function generateExportFile(
        array $filters,
        ?array $columns,
        string $layout,
        string $format,
        ?string $gridFieldId,
        string $nameHint = 'export',
        ?int $rowLimit = null,
    ): array {
        $limit = $rowLimit ?? EApprovalSubmissionExportService::SYNC_MAX_ROWS;
        $formId = isset($filters['form_id']) ? (string) $filters['form_id'] : null;
        $form = $formId !== null && $formId !== ''
            ? EApprovalForm::query()->find($formId)
            : null;

        $includeFields = $form !== null;
        $scope = $form !== null
            ? ['form' => $form, 'include_fields' => true, 'can_view_all' => true]
            : null;

        $queryFilters = array_filter([
            'status' => $filters['status'] ?? null,
            'statuses' => $filters['statuses'] ?? null,
            'form_id' => $formId,
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
            'search' => $filters['search'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '' && $v !== []);

        $matched = $this->export->countMatching($queryFilters, $scope);
        $truncated = $matched > $limit;
        $exported = min($matched, $limit);

        $gridField = null;
        if ($layout === 'line_items') {
            if ($form === null) {
                throw new \InvalidArgumentException('Line-item exports require a single form.');
            }
            $gridField = $this->export->resolveGridField($form, $gridFieldId);
            if ($gridField === null) {
                throw new \InvalidArgumentException('The selected form has no line-item (grid) fields.');
            }
        }

        $filename = $this->plannedFilename($nameHint, $layout, $format);

        if ($format === 'xlsx') {
            $sheets = [[
                'name' => 'Submissions',
                'headers' => $this->export->headers($form, $includeFields, $columns),
                'rows' => $this->export->rows($queryFilters, $scope, $columns, $limit),
            ]];
            if ($layout === 'line_items' && $form !== null && $gridField !== null) {
                $sheets[] = [
                    'name' => 'Line Items',
                    'headers' => $this->export->lineItemHeaders($form, $gridField),
                    'rows' => $this->export->lineItemRows($queryFilters, $scope, $form, $gridField, $limit),
                ];
            }
            $path = (new SimpleXlsxWriter())->writeSheets($sheets);
            $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        } else {
            $path = tempnam(sys_get_temp_dir(), 'eapproval-csv-');
            if ($path === false) {
                throw new \RuntimeException('Unable to allocate a temporary CSV file.');
            }
            $handle = fopen($path, 'wb');
            if ($handle === false) {
                throw new \RuntimeException('Unable to open the temporary CSV file.');
            }
            fwrite($handle, "\xEF\xBB\xBF");
            if ($layout === 'line_items' && $form !== null && $gridField !== null) {
                fputcsv($handle, $this->export->lineItemHeaders($form, $gridField));
                foreach ($this->export->lineItemRows($queryFilters, $scope, $form, $gridField, $limit) as $row) {
                    fputcsv($handle, $row);
                }
            } else {
                fputcsv($handle, $this->export->headers($form, $includeFields, $columns));
                foreach ($this->export->rows($queryFilters, $scope, $columns, $limit) as $row) {
                    fputcsv($handle, $row);
                }
            }
            fclose($handle);
            $contentType = 'text/csv; charset=UTF-8';
        }

        return [
            'path' => $path,
            'filename' => $filename,
            'content_type' => $contentType,
            'matched_rows' => $matched,
            'exported_rows' => $exported,
            'truncated' => $truncated,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>|null  $columns
     */
    private function recordHistory(
        TenantUser $user,
        ?EApprovalReportDefinition $report,
        string $name,
        array $filters,
        ?array $columns,
        string $layout,
        string $format,
        ?string $gridFieldId,
        int $matchedRows,
        int $exportedRows,
        bool $truncated,
        string $triggeredBy,
        string $filename,
        string $status = EApprovalExportHistoryStatus::COMPLETED,
        ?string $remarks = null,
    ): EApprovalExportHistory {
        return EApprovalExportHistory::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'report_definition_id' => $report?->id,
            'name' => $name,
            'filters_json' => $this->normalizeFilters($filters),
            'columns_json' => $this->normalizeColumns($columns),
            'layout' => $layout,
            'format' => $format,
            'grid_field_id' => $gridFieldId,
            'matched_rows' => $matchedRows,
            'exported_rows' => $exportedRows,
            'truncated' => $truncated,
            'status' => $status,
            'triggered_by' => $triggeredBy,
            'filename' => $filename,
            'remarks' => $remarks ?? ($truncated
                ? sprintf('Truncated at %d of %d matching rows.', $exportedRows, $matchedRows)
                : null),
        ]);
    }

    /**
     * @param  array{
     *     path: string,
     *     filename: string,
     *     content_type: string,
     *     matched_rows: int,
     *     exported_rows: int,
     *     truncated: bool
     * }  $result
     */
    private function attachStoredFile(
        EApprovalExportHistory $history,
        string $localPath,
        string $contentType,
        TenantUser $user,
    ): void {
        $stored = $this->files->storeExport(
            $localPath,
            (string) $user->id,
            (string) $history->id,
            (string) ($history->filename ?: 'export.csv'),
        );

        $history->forceFill([
            'file_path' => $stored['path'],
            'disk' => $stored['disk'],
            'content_type' => $contentType,
            'byte_size' => $stored['byte_size'],
            'expires_at' => now()->addDays(7),
        ])->save();

        if (is_file($localPath)) {
            @unlink($localPath);
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function countForFilters(array $filters): int
    {
        $formId = isset($filters['form_id']) ? (string) $filters['form_id'] : null;
        $form = $formId !== null && $formId !== ''
            ? EApprovalForm::query()->find($formId)
            : null;
        $scope = $form !== null
            ? ['form' => $form, 'include_fields' => true, 'can_view_all' => true]
            : null;

        $queryFilters = array_filter([
            'status' => $filters['status'] ?? null,
            'statuses' => $filters['statuses'] ?? null,
            'form_id' => $formId,
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
            'search' => $filters['search'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '' && $v !== []);

        return $this->export->countMatching($queryFilters, $scope);
    }

    private function plannedFilename(string $nameHint, string $layout, string $format): string
    {
        $safeName = preg_replace('/[^a-z0-9_-]+/i', '-', $nameHint) ?: 'export';
        $stamp = now()->format('Y-m-d');

        return $safeName.($layout === 'line_items' ? '-line-items' : '').'-'.$stamp.'.'.$format;
    }

    private function dispatchExportJob(string $historyId): void
    {
        $tenantId = tenant()?->getTenantKey();
        if ($tenantId === null || $tenantId === '') {
            throw new \RuntimeException('Tenant context is required to queue an export.');
        }

        GenerateEApprovalExportJob::dispatch((string) $tenantId, $historyId);
    }

    /**
     * @param  array<string, mixed>|null  $filters
     * @return array<string, mixed>
     */
    private function normalizeFilters(?array $filters): array
    {
        if ($filters === null || $filters === []) {
            return [];
        }

        return array_filter([
            'form_id' => $filters['form_id'] ?? null,
            'status' => $filters['status'] ?? null,
            'statuses' => $filters['statuses'] ?? null,
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
            'search' => $filters['search'] ?? null,
            'scope' => $filters['scope'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * @param  list<string>|null  $columns
     * @return list<string>|null
     */
    private function normalizeColumns(?array $columns): ?array
    {
        if ($columns === null) {
            return null;
        }

        $clean = array_values(array_filter(
            array_map(static fn ($c): string => (string) $c, $columns),
            static fn (string $c): bool => $c !== '',
        ));

        return $clean === [] ? null : $clean;
    }

    /**
     * @param  array<string, mixed>|null  $schedule
     * @return array<string, mixed>|null
     */
    private function normalizeSchedule(?array $schedule): ?array
    {
        if ($schedule === null) {
            return null;
        }

        $enabled = (bool) ($schedule['enabled'] ?? false);
        $frequency = (string) ($schedule['frequency'] ?? 'daily');
        if (! in_array($frequency, ['daily', 'weekly'], true)) {
            $frequency = 'daily';
        }
        $hour = max(0, min(23, (int) ($schedule['hour'] ?? 8)));
        $dayOfWeek = max(0, min(6, (int) ($schedule['day_of_week'] ?? 1)));
        $recipients = array_values(array_filter(
            array_map('strval', is_array($schedule['recipients'] ?? null) ? $schedule['recipients'] : []),
            static fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
        ));

        return [
            'enabled' => $enabled,
            'frequency' => $frequency,
            'hour' => $hour,
            'day_of_week' => $dayOfWeek,
            'recipients' => $recipients,
        ];
    }
}
