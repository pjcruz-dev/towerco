<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Core\Support\ModuleListExportQuery;
use App\Modules\DocExtract\Services\DocExtractBatchService;
use App\Modules\DocExtract\Services\DocExtractPlanFeaturesService;
use App\Modules\Identity\Models\ModuleListExport;
use App\Modules\Identity\Services\ModuleListExportService;
use App\Modules\ProcurementOne\Support\ProcurementExcelWorkbookWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class DocExtractBatchListExportController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        DocExtractBatchService $batches,
        DocExtractPlanFeaturesService $planFeatures,
        ModuleListExportService $asyncExports,
    ): Response|JsonResponse {
        abort_unless($request->user()?->can('doc-extract:view'), 403);
        $planFeatures->assertModuleEnabled();

        ModuleListExportQuery::coerceArrayParams($request);

        $validated = $request->validate([
            'format' => ['sometimes', 'string', 'in:csv,xlsx,html'],
            'status' => ['sometimes', 'nullable', 'string', 'max:40'],
            'search' => ['sometimes', 'nullable', 'string', 'max:200'],
            'sort' => ['sometimes', 'nullable', 'string', 'max:60'],
            'columns' => ['sometimes', 'array'],
            'columns.*' => ['string', 'max:60'],
            'ids' => ['sometimes', 'array', 'max:500'],
            'ids.*' => ['uuid'],
            'async' => ['sometimes', 'boolean'],
        ]);

        $format = $validated['format'] ?? 'csv';
        // Printable HTML is always async (open from My exports, then print).
        $forceAsync = (bool) ($validated['async'] ?? false) || $format === 'html';
        $ids = ! empty($validated['ids'])
            ? array_values(array_unique(array_map('strval', $validated['ids'])))
            : null;

        $matched = $batches->countListRows(
            $validated['status'] ?? null,
            $validated['search'] ?? null,
            $ids,
        );

        $headers = [
            'id',
            'created_at',
            'primary_filename',
            'file_count',
            'template_name',
            'mode',
            'status',
            'document_count',
            'ready_count',
            'failed_count',
            'message',
        ];
        $requested = $validated['columns'] ?? null;
        if (is_array($requested) && $requested !== []) {
            $allow = array_values(array_intersect($headers, array_map('strval', $requested)));
            if ($allow !== []) {
                $headers = $allow;
            }
        }

        $stamp = now()->format('Y-m-d');
        $filename = 'doc-extract-batches-'.$stamp.'.'.$format;
        $filters = array_filter([
            'status' => $validated['status'] ?? null,
            'search' => $validated['search'] ?? null,
            'sort' => $validated['sort'] ?? null,
            'ids' => $ids,
            'columns' => $headers,
        ], static fn ($v) => $v !== null && $v !== '' && $v !== []);

        if ($asyncExports->shouldQueue($matched, $forceAsync)) {
            $export = $asyncExports->queue(
                $request->user(),
                'doc-extract',
                $filename,
                $format,
                $filters,
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

        $rows = $batches->exportListRows(
            $validated['status'] ?? null,
            $validated['search'] ?? null,
            $validated['sort'] ?? null,
            ModuleListExport::SYNC_MAX_ROWS,
            $ids,
        );

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
                    static fn (string $key) => $row[$key] ?? '',
                    $headers,
                );
            }
            $writer = new ProcurementExcelWorkbookWriter;
            $writer->addSheet('Batches', $sheetRows);
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
