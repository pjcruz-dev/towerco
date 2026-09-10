<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Concerns\ValidatesTenantListQuery;
use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalFormField;
use App\Modules\EApproval\Services\EApprovalFormWorkspaceService;
use App\Modules\EApproval\Services\EApprovalReportService;
use App\Modules\EApproval\Services\EApprovalSubmissionExportService;
use App\Modules\EApproval\Support\SimpleXlsxWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EApprovalFormWorkspaceExportController extends AbstractApiController
{
    use ValidatesTenantListQuery;

    public function __invoke(
        Request $request,
        string $slug,
        EApprovalFormWorkspaceService $workspaces,
        EApprovalSubmissionExportService $export,
        EApprovalReportService $reports,
    ): Response|JsonResponse {
        abort_unless($request->user()?->can('e_approval:view'), 403);
        abort_unless($request->user()?->can('e_approval:submissions:view'), 403);

        $user = $request->user();
        $context = $workspaces->resolveWorkspaceContext($slug, $user);
        $form = $context['form'];
        $workspace = $context['workspace'];

        abort_unless($workspaces->viewerCanExport($user, $workspace), 403);

        $query = $this->validatedTenantListQuery($request);
        $validated = $request->validate([
            'status' => ['sometimes', 'string', 'max:50'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'include_fields' => ['sometimes', 'boolean'],
            'subsidiary' => ['sometimes', 'nullable', 'string', 'max:64'],
            'department' => ['sometimes', 'nullable', 'string', 'max:120'],
            'format' => ['sometimes', 'string', 'in:csv,xlsx'],
            'columns' => ['sometimes', 'array'],
            'columns.*' => ['string', 'max:120'],
            'layout' => ['sometimes', 'string', 'in:submissions,line_items'],
            'grid_field' => ['sometimes', 'uuid'],
            'async' => ['sometimes', 'boolean'],
            'ids' => ['sometimes', 'array', 'max:500'],
            'ids.*' => ['uuid'],
        ]);

        $format = $validated['format'] ?? 'csv';
        $layout = $validated['layout'] ?? 'submissions';
        $forceAsync = (bool) ($validated['async'] ?? false);
        $forceOwn = filter_var($request->query('mine', false), FILTER_VALIDATE_BOOLEAN);
        $includeFields = ! $request->has('include_fields')
            || filter_var($request->query('include_fields'), FILTER_VALIDATE_BOOLEAN);
        $canViewAll = $workspaces->viewerCanSeeAllInWorkspace($user, $workspace) && ! $forceOwn;

        $ids = ! empty($validated['ids'])
            ? array_values(array_unique(array_map('strval', $validated['ids'])))
            : null;

        $filters = array_filter([
            'status' => $validated['status'] ?? null,
            'form_id' => $form?->id !== null ? (string) $form->id : null,
            'form_ids' => $context['form_ids'],
            'from' => isset($validated['from']) ? (string) $validated['from'] : null,
            'to' => isset($validated['to']) ? (string) $validated['to'] : null,
            'search' => $query['search'] !== '' ? $query['search'] : null,
            'subsidiary' => isset($validated['subsidiary']) ? trim((string) $validated['subsidiary']) : null,
            'department' => isset($validated['department']) ? trim((string) $validated['department']) : null,
            'ids' => $ids,
            'viewer_scope' => $forceOwn ? 'mine' : 'all',
            'workspace_can_view_all' => $canViewAll,
            'include_fields' => $includeFields,
        ], static fn ($v) => $v !== null && $v !== '' && $v !== []);

        $scope = [
            'viewer' => $user,
            'can_view_all' => $canViewAll,
            'form' => $form,
            'include_fields' => $includeFields,
        ];

        $selectedColumns = ! empty($validated['columns']) ? array_values($validated['columns']) : null;

        $gridField = null;
        $isLineItems = $layout === 'line_items';
        if ($isLineItems) {
            abort_if($form === null, 422, 'Line-item exports require a workspace form.');
            $gridField = $export->resolveGridField($form, $validated['grid_field'] ?? null);
            abort_if($gridField === null, 422, 'The workspace form has no line-item (grid) fields to export.');
        }

        $queryFilters = array_filter([
            'status' => $filters['status'] ?? null,
            'form_id' => $filters['form_id'] ?? null,
            'form_ids' => $filters['form_ids'] ?? null,
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
            'search' => $filters['search'] ?? null,
            'subsidiary' => $filters['subsidiary'] ?? null,
            'department' => $filters['department'] ?? null,
            'ids' => $filters['ids'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '' && $v !== []);

        $totalMatching = $export->countMatching($queryFilters, $scope);

        $safeSlug = preg_replace('/[^a-z0-9_-]+/i', '-', $slug) ?: 'workspace';
        $baseName = 'workspace-'.$safeSlug.($isLineItems ? '-line-items' : '');
        $stamp = now()->format('Y-m-d');
        $filename = $baseName.'-'.$stamp.'.'.$format;

        if ($reports->shouldQueue($totalMatching, $forceAsync)) {
            $history = $reports->queueAdHocExport(
                $user,
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
            $user,
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
            return $this->xlsxResponse($export, $queryFilters, $scope, $selectedColumns, $form, $includeFields, $layout, $gridField, $filename, $exportHeaders);
        }

        return $this->csvResponse($export, $queryFilters, $scope, $selectedColumns, $form, $includeFields, $layout, $gridField, $filename, $exportHeaders);
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

        $isLineItems = $layout === 'line_items' && $form !== null && $gridField !== null;

        if ($isLineItems && $form !== null && $gridField !== null) {
            $path = $writer->write(
                'Line items',
                $export->lineItemHeaders($form, $gridField),
                $export->lineItemRows($filters, $scope, $form, $gridField),
            );
        } else {
            $path = $writer->write(
                'Workspace',
                $export->headers($form, $includeFields, $selectedColumns),
                $export->rows($filters, $scope, $selectedColumns),
            );
        }

        return response()->download($path, $filename, array_merge($exportHeaders, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]))->deleteFileAfterSend(true);
    }
}
