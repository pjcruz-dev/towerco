<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Core\Services\AbstractDomainService;
use App\Core\Support\ModuleListPrintableHtml;
use App\Modules\DocExtract\Services\DocExtractBatchService;
use App\Modules\Identity\Jobs\ProcessModuleListExportJob;
use App\Modules\Identity\Models\ModuleListExport;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Notifications\Services\TenantNotificationService;
use App\Modules\Notifications\Support\TenantNotificationModule;
use App\Modules\ProcurementOne\Support\ProcurementExcelWorkbookWriter;
use App\Modules\Ticketing\Services\TicketingTicketService;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Str;

class ModuleListExportService extends AbstractDomainService
{
    public function __construct(
        private readonly TenantNotificationService $notifications,
        Dispatcher $events,
    ) {
        parent::__construct($events);
    }

    public function shouldQueue(int $matchedRows, bool $forceAsync = false): bool
    {
        return $forceAsync || $matchedRows > ModuleListExport::SYNC_MAX_ROWS;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function queue(
        TenantUser $user,
        string $module,
        string $filename,
        string $format,
        array $filters,
        int $matchedRows,
    ): ModuleListExport {
        $tenantId = tenant()?->getTenantKey();
        if ($tenantId === null || $tenantId === '') {
            throw new \RuntimeException('Tenant context is required to queue an export.');
        }

        $export = ModuleListExport::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'module' => $module,
            'filename' => $filename,
            'format' => $format,
            'status' => ModuleListExport::STATUS_QUEUED,
            'disk' => 'local',
            'matched_rows' => min($matchedRows, ModuleListExport::ASYNC_MAX_ROWS),
            'exported_rows' => 0,
            'truncated' => $matchedRows > ModuleListExport::ASYNC_MAX_ROWS,
            'filters_json' => $filters,
            'expires_at' => now()->addDays(7),
        ]);

        ProcessModuleListExportJob::dispatch((string) $tenantId, (string) $export->id);

        return $export;
    }

    public function process(string $exportId, DocExtractBatchService $batches, TicketingTicketService $tickets): void
    {
        $export = ModuleListExport::query()->find($exportId);
        if ($export === null) {
            return;
        }

        if ($export->status === ModuleListExport::STATUS_COMPLETED && $export->file_path) {
            return;
        }

        $export->forceFill([
            'status' => ModuleListExport::STATUS_PROCESSING,
            'error_message' => null,
        ])->save();

        try {
            $filters = is_array($export->filters_json) ? $export->filters_json : [];
            $limit = ModuleListExport::ASYNC_MAX_ROWS;
            $format = (string) $export->format;

            if ((string) $export->module === 'doc-extract') {
                $rows = $batches->exportListRows(
                    isset($filters['status']) ? (string) $filters['status'] : null,
                    isset($filters['search']) ? (string) $filters['search'] : null,
                    isset($filters['sort']) ? (string) $filters['sort'] : null,
                    $limit,
                    isset($filters['ids']) && is_array($filters['ids']) ? $filters['ids'] : null,
                );
                $headers = is_array($filters['columns'] ?? null) && $filters['columns'] !== []
                    ? array_values(array_map('strval', $filters['columns']))
                    : array_keys($rows[0] ?? [
                        'id' => '',
                        'created_at' => '',
                        'primary_filename' => '',
                        'file_count' => '',
                        'template_name' => '',
                        'mode' => '',
                        'status' => '',
                        'document_count' => '',
                        'ready_count' => '',
                        'failed_count' => '',
                        'message' => '',
                    ]);
            } elseif ((string) $export->module === 'ticketing') {
                $user = $export->user;
                if ($user === null) {
                    throw new \RuntimeException('Export owner is missing.');
                }
                $rows = $tickets->exportRows($user, $filters, $limit);
                $headers = is_array($filters['columns'] ?? null) && $filters['columns'] !== []
                    ? array_values(array_map('strval', $filters['columns']))
                    : [
                        'ticket_number',
                        'title',
                        'status',
                        'priority',
                        'category',
                        'department',
                        'requester',
                        'assignee',
                        'source_module',
                        'created_at',
                        'updated_at',
                        'sla_status',
                    ];
            } else {
                throw new \RuntimeException('Unsupported export module: '.$export->module);
            }

            $dir = storage_path('app/module-list-exports/'.$export->id);
            if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
                throw new \RuntimeException('Unable to create export directory.');
            }
            $path = $dir.DIRECTORY_SEPARATOR.$export->filename;

            if ($format === 'xlsx') {
                $sheetRows = [$headers];
                foreach ($rows as $row) {
                    $sheetRows[] = array_map(
                        static fn (string $key) => (string) ($row[$key] ?? ''),
                        $headers,
                    );
                }
                $writer = new ProcurementExcelWorkbookWriter;
                $writer->addSheet('Export', $sheetRows);
                file_put_contents($path, $writer->toBinaryString());
            } elseif ($format === 'html') {
                file_put_contents($path, ModuleListPrintableHtml::render(
                    (string) $export->module.' export',
                    $headers,
                    $rows,
                    (int) $export->matched_rows,
                ));
            } else {
                $handle = fopen($path, 'wb');
                if ($handle === false) {
                    throw new \RuntimeException('Unable to open export file.');
                }
                fwrite($handle, "\xEF\xBB\xBF");
                fputcsv($handle, $headers);
                foreach ($rows as $row) {
                    fputcsv($handle, array_map(
                        static fn (string $key) => (string) ($row[$key] ?? ''),
                        $headers,
                    ));
                }
                fclose($handle);
            }

            $export->forceFill([
                'status' => ModuleListExport::STATUS_COMPLETED,
                'file_path' => 'module-list-exports/'.$export->id.'/'.$export->filename,
                'exported_rows' => count($rows),
                'truncated' => (int) $export->matched_rows > count($rows),
            ])->save();

            $this->notifyReady($export);
        } catch (\Throwable $e) {
            $export->forceFill([
                'status' => ModuleListExport::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ])->save();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function present(ModuleListExport $export): array
    {
        return [
            'id' => (string) $export->id,
            'module' => (string) $export->module,
            'filename' => (string) $export->filename,
            'format' => (string) $export->format,
            'status' => (string) $export->status,
            'matched_rows' => (int) $export->matched_rows,
            'exported_rows' => (int) $export->exported_rows,
            'truncated' => (bool) $export->truncated,
            'error_message' => $export->error_message,
            'expires_at' => optional($export->expires_at)?->toIso8601String(),
            'created_at' => optional($export->created_at)?->toIso8601String(),
            'download' => $export->status === ModuleListExport::STATUS_COMPLETED && $export->file_path
                ? [
                    'url' => '/module-list-exports/'.(string) $export->id.'/download',
                    'stream' => true,
                ]
                : null,
        ];
    }

    public function findForUser(TenantUser $user, string $id): ModuleListExport
    {
        $export = ModuleListExport::query()->findOrFail($id);
        abort_unless((string) $export->user_id === (string) $user->id, 403);

        return $export;
    }

    public function absolutePath(ModuleListExport $export): string
    {
        abort_unless($export->file_path !== null && $export->file_path !== '', 404);

        return storage_path('app/'.$export->file_path);
    }

    public function notifyReady(ModuleListExport $export): void
    {
        $module = (string) $export->module === 'ticketing'
            ? TenantNotificationModule::TICKETING
            : TenantNotificationModule::DOCUMENTS;

        $this->notifications->notify(
            userId: (string) $export->user_id,
            module: $module,
            type: 'module_list_export_ready',
            message: 'Your export is ready to download.',
            subjectType: 'module_list_export',
            subjectId: (string) $export->id,
            contextPrimary: (string) $export->filename,
            href: '/exports',
        );
    }
}
