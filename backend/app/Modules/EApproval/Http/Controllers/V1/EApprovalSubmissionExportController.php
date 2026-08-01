<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Concerns\ValidatesTenantListQuery;
use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalFormField;
use App\Modules\EApproval\Services\EApprovalReportService;
use App\Modules\EApproval\Services\EApprovalSubmissionExportService;
use App\Modules\EApproval\Support\SimpleXlsxWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EApprovalSubmissionExportController extends AbstractApiController
{
    use ValidatesTenantListQuery;

    public function __invoke(
        Request $request,
        EApprovalSubmissionExportService $export,
        EApprovalReportService $reports,
    ): Response|JsonResponse {
        abort_unless($request->user()?->can('e_approval:audit:view'), 403);

        $query = $this->validatedTenantListQuery($request);
        $validated = $request->validate([
            'status' => ['sometimes', 'string', 'max:50'],
            'statuses' => ['sometimes', 'array'],
            'statuses.*' => ['string', 'max:50'],
            'form_id' => ['sometimes', 'uuid'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'format' => ['sometimes', 'string', 'in:csv,xlsx'],
            'columns' => ['sometimes', 'array'],
            'columns.*' => ['string', 'max:120'],
            'layout' => ['sometimes', 'string', 'in:submissions,line_items'],
            'grid_field' => ['sometimes', 'uuid'],
            'async' => ['sometimes', 'boolean'],
        ]);

        $format = $validated['format'] ?? 'csv';
        $layout = $validated['layout'] ?? 'submissions';
        $forceAsync = (bool) ($validated['async'] ?? false);

        // When a single form is selected, resolve it so its live custom fields are
        // available as columns (parity with the workspace export). Leaving it null
        // keeps the all-forms export to base columns only.
        $form = isset($validated['form_id'])
            ? EApprovalForm::query()->find($validated['form_id'])
            : null;

        $filters = array_filter([
            'status' => $validated['status'] ?? null,
            'statuses' => $validated['statuses'] ?? null,
            'form_id' => $validated['form_id'] ?? null,
            'from' => isset($validated['from']) ? (string) $validated['from'] : null,
            'to' => isset($validated['to']) ? (string) $validated['to'] : null,
            'search' => $query['search'] !== '' ? $query['search'] : null,
        ], static fn ($v) => $v !== null && $v !== '' && $v !== []);

        $includeFields = $form !== null;
        $scope = $form !== null
            ? ['form' => $form, 'include_fields' => true, 'can_view_all' => true]
            : null;

        $selectedColumns = ! empty($validated['columns']) ? array_values($validated['columns']) : null;

        $gridField = null;
        if ($layout === 'line_items') {
            abort_if($form === null, 422, 'Line-item exports require a single form.');
            $gridField = $export->resolveGridField($form, $validated['grid_field'] ?? null);
            abort_if($gridField === null, 422, 'The selected form has no line-item (grid) fields to export.');
        }

        $totalMatching = $export->countMatching($filters, $scope);

        $baseName = $form !== null
            ? 'e-approval-'.(preg_replace('/[^a-z0-9_-]+/i', '-', (string) $form->name) ?: 'form')
            : 'e-approval-submissions';
        if ($layout === 'line_items') {
            $baseName .= '-line-items';
        }
        $stamp = now()->format('Y-m-d');
        $filename = $baseName.'-'.$stamp.'.'.$format;

        if ($reports->shouldQueue($totalMatching, $forceAsync)) {
            $history = $reports->queueAdHocExport(
                $request->user(),
                $filters,
                $selectedColumns,
                $layout,
                $format,
                $gridField?->id !== null ? (string) $gridField->id : ($validated['grid_field'] ?? null),
                $totalMatching,
                $filename,
            );

            return $this->ok([
                'async' => true,
                'history' => $reports->presentHistory($history),
                'matched_rows' => $totalMatching,
                'max_rows' => EApprovalSubmissionExportService::ASYNC_MAX_ROWS,
                'message' => 'Export queued. Download from Recent exports when ready.',
            ], 202);
        }

        $truncated = $totalMatching > EApprovalSubmissionExportService::SYNC_MAX_ROWS;
        $exportedRows = min($totalMatching, EApprovalSubmissionExportService::SYNC_MAX_ROWS);

        $history = $reports->recordAdHocExport(
            $request->user(),
            $filters,
            $selectedColumns,
            $layout,
            $format,
            $gridField?->id !== null ? (string) $gridField->id : ($validated['grid_field'] ?? null),
            $totalMatching,
            $exportedRows,
            $truncated,
            $filename,
        );

        $exportHeaders = [
            'X-Export-Truncated' => $truncated ? '1' : '0',
            'X-Export-Total-Rows' => (string) $totalMatching,
            'X-Export-Max-Rows' => (string) EApprovalSubmissionExportService::SYNC_MAX_ROWS,
            'X-Export-History-Id' => (string) $history->id,
            'Access-Control-Expose-Headers' => 'X-Export-Truncated, X-Export-Total-Rows, X-Export-Max-Rows, X-Export-History-Id',
        ];

        if ($format === 'xlsx') {
            return $this->xlsxResponse($export, $filters, $scope, $selectedColumns, $form, $includeFields, $layout, $gridField, $filename, $exportHeaders);
        }

        return $this->csvResponse($export, $filters, $scope, $selectedColumns, $form, $includeFields, $layout, $gridField, $filename, $exportHeaders);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>|null  $scope
     * @param  list<string>|null  $selectedColumns
     * @param  array<string, string>  $exportHeaders
     */
    private function csvResponse(
        EApprovalSubmissionExportService $export,
        array $filters,
        ?array $scope,
        ?array $selectedColumns,
        ?EApprovalForm $form,
        bool $includeFields,
        string $layout,
        ?EApprovalFormField $gridField,
        string $filename,
        array $exportHeaders,
    ): Response {
        $isLineItems = $layout === 'line_items' && $form !== null && $gridField !== null;

        return response()->streamDownload(function () use ($export, $filters, $scope, $selectedColumns, $form, $includeFields, $isLineItems, $gridField): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");

            if ($isLineItems && $form !== null && $gridField !== null) {
                fputcsv($handle, $export->lineItemHeaders($form, $gridField));
                foreach ($export->lineItemRows($filters, $scope, $form, $gridField) as $row) {
                    fputcsv($handle, $row);
                }
            } else {
                fputcsv($handle, $export->headers($form, $includeFields, $selectedColumns));
                foreach ($export->rows($filters, $scope, $selectedColumns) as $row) {
                    fputcsv($handle, $row);
                }
            }

            fclose($handle);
        }, $filename, array_merge($exportHeaders, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>|null  $scope
     * @param  list<string>|null  $selectedColumns
     * @param  array<string, string>  $exportHeaders
     */
    private function xlsxResponse(
        EApprovalSubmissionExportService $export,
        array $filters,
        ?array $scope,
        ?array $selectedColumns,
        ?EApprovalForm $form,
        bool $includeFields,
        string $layout,
        ?EApprovalFormField $gridField,
        string $filename,
        array $exportHeaders,
    ): Response {
        $writer = new SimpleXlsxWriter();

        $sheets = [[
            'name' => 'Submissions',
            'headers' => $export->headers($form, $includeFields, $selectedColumns),
            'rows' => $export->rows($filters, $scope, $selectedColumns),
        ]];

        if ($layout === 'line_items' && $form !== null && $gridField !== null) {
            $sheets[] = [
                'name' => 'Line Items',
                'headers' => $export->lineItemHeaders($form, $gridField),
                'rows' => $export->lineItemRows($filters, $scope, $form, $gridField),
            ];
        }

        $path = $writer->writeSheets($sheets);

        return response()->download($path, $filename, array_merge($exportHeaders, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]))->deleteFileAfterSend(true);
    }
}
