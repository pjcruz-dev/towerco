<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Http\Controllers\V1;

use App\Core\Http\Concerns\ValidatesTenantListQuery;
use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\ModuleListExport;
use App\Modules\Identity\Services\ModuleListExportService;
use App\Modules\ProcurementOne\Support\ProcurementExcelWorkbookWriter;
use App\Modules\Ticketing\Services\TicketingPlanFeaturesService;
use App\Modules\Ticketing\Services\TicketingTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TicketingTicketExportController extends AbstractApiController
{
    use ValidatesTenantListQuery;

    public function __invoke(
        Request $request,
        TicketingTicketService $service,
        TicketingPlanFeaturesService $planFeatures,
        ModuleListExportService $asyncExports,
    ): Response|JsonResponse {
        abort_unless($request->user()?->can('ticketing:view'), 403);
        $planFeatures->assertModuleEnabled();

        $validated = $request->validate([
            'format' => ['sometimes', 'string', 'in:csv,xlsx,html'],
            'columns' => ['sometimes', 'array'],
            'columns.*' => ['string', 'max:60'],
            'ids' => ['sometimes', 'array', 'max:500'],
            'ids.*' => ['uuid'],
            'async' => ['sometimes', 'boolean'],
        ]);
        $format = $validated['format'] ?? 'csv';
        // Printable HTML is always async (open from My exports, then print).
        $forceAsync = (bool) ($validated['async'] ?? false) || $format === 'html';

        $listQuery = $this->validatedTenantListQuery($request);
        $ids = ! empty($validated['ids'])
            ? array_values(array_unique(array_map('strval', $validated['ids'])))
            : [];
        $filters = [
            ...$listQuery,
            'status' => $request->query('status'),
            'priority' => $request->query('priority'),
            'category' => $request->query('category'),
            'department' => $request->query('department'),
            'assignee_id' => $request->query('assignee_id'),
            'source_module' => $request->query('source_module'),
            'mine' => $request->boolean('mine'),
            'assigned_me' => $request->boolean('assigned_me'),
            'sla_status' => $request->query('sla_status'),
            'ids' => $ids !== [] ? $ids : null,
        ];

        $matched = $service->filteredQuery($request->user(), $filters)->count();

        $headers = [
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
        $requested = $validated['columns'] ?? null;
        if (is_array($requested) && $requested !== []) {
            $allow = array_values(array_intersect($headers, array_map('strval', $requested)));
            if ($allow !== []) {
                $headers = $allow;
            }
        }

        $stamp = now()->format('Y-m-d');
        $filename = 'ticketing-tickets-'.$stamp.'.'.$format;
        $queueFilters = array_filter([
            ...$filters,
            'columns' => $headers,
        ], static fn ($v) => $v !== null && $v !== '' && $v !== []);

        if ($asyncExports->shouldQueue($matched, $forceAsync)) {
            $export = $asyncExports->queue(
                $request->user(),
                'ticketing',
                $filename,
                $format,
                $queueFilters,
                $matched,
            );

            return $this->ok([
                'async' => true,
                'export' => $asyncExports->present($export),
                'matched_rows' => $matched,
                'max_rows' => ModuleListExport::ASYNC_MAX_ROWS,
                'message' => $format === 'html'
                    ? 'Printable HTML queued. Open Settings → My exports when ready, then open the file to print.'
                    : 'Export queued. You will be notified when the download is ready.',
            ], 202);
        }

        $rows = $service->exportRows($request->user(), $filters, ModuleListExport::SYNC_MAX_ROWS);
        $truncated = $matched > count($rows);
        $exportHeaders = [
            'X-Export-Truncated' => $truncated ? '1' : '0',
            'X-Export-Total-Rows' => (string) $matched,
            'X-Export-Max-Rows' => (string) ModuleListExport::SYNC_MAX_ROWS,
            'Access-Control-Expose-Headers' => 'X-Export-Truncated, X-Export-Total-Rows, X-Export-Max-Rows',
        ];

        if ($format === 'xlsx') {
            $sheetRows = [$headers];
            foreach ($rows as $row) {
                $sheetRows[] = array_map(
                    static fn (string $key) => (string) ($row[$key] ?? ''),
                    $headers,
                );
            }
            $writer = new ProcurementExcelWorkbookWriter;
            $writer->addSheet('Tickets', $sheetRows);
            $binary = $writer->toBinaryString();

            return response()->streamDownload(function () use ($binary): void {
                echo $binary;
            }, $filename, array_merge($exportHeaders, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]));
        }

        return response()->streamDownload(function () use ($rows, $headers): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
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
        }, $filename, array_merge($exportHeaders, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]));
    }
}
