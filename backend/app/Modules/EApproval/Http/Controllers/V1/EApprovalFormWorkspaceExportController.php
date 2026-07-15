<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Concerns\ValidatesTenantListQuery;
use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalFormWorkspaceService;
use App\Modules\EApproval\Services\EApprovalSubmissionExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EApprovalFormWorkspaceExportController extends AbstractApiController
{
    use ValidatesTenantListQuery;

    public function __invoke(
        Request $request,
        string $slug,
        EApprovalFormWorkspaceService $workspaces,
        EApprovalSubmissionExportService $export,
    ): StreamedResponse {
        abort_unless($request->user()?->can('e_approval:view'), 403);
        abort_unless($request->user()?->can('e_approval:submissions:view'), 403);

        $context = $workspaces->resolveWorkspaceContext($slug, $request->user());
        $form = $context['form'];
        $workspace = $context['workspace'];

        abort_unless($workspaces->viewerCanExport($request->user(), $workspace), 403);

        $query = $this->validatedTenantListQuery($request);
        $validated = $request->validate([
            'status' => ['sometimes', 'string', 'max:50'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'include_fields' => ['sometimes', 'boolean'],
        ]);

        $forceOwn = filter_var($request->query('mine', false), FILTER_VALIDATE_BOOLEAN);
        $includeFields = ! $request->has('include_fields')
            || filter_var($request->query('include_fields'), FILTER_VALIDATE_BOOLEAN);
        $canViewAll = $workspaces->viewerCanSeeAllInWorkspace($request->user(), $workspace) && ! $forceOwn;

        $filters = array_filter([
            'status' => $validated['status'] ?? null,
            'form_ids' => $context['form_ids'],
            'from' => isset($validated['from']) ? (string) $validated['from'] : null,
            'to' => isset($validated['to']) ? (string) $validated['to'] : null,
            'search' => $query['search'] !== '' ? $query['search'] : null,
        ], static fn ($v) => $v !== null && $v !== '' && $v !== []);

        $scope = [
            'viewer' => $request->user(),
            'can_view_all' => $canViewAll,
            'form' => $form,
            'include_fields' => $includeFields,
        ];

        $safeSlug = preg_replace('/[^a-z0-9_-]+/i', '-', $slug) ?: 'workspace';
        $filename = 'workspace-'.$safeSlug.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($export, $filters, $scope, $form, $includeFields): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $export->headers($form, $includeFields));

            foreach ($export->rows($filters, $scope) as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
