<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Concerns\ValidatesTenantListQuery;
use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalSubmissionExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EApprovalSubmissionExportController extends AbstractApiController
{
    use ValidatesTenantListQuery;

    public function __invoke(Request $request, EApprovalSubmissionExportService $export): StreamedResponse
    {
        abort_unless($request->user()?->can('e_approval:audit:view'), 403);

        $query = $this->validatedTenantListQuery($request);
        $validated = $request->validate([
            'status' => ['sometimes', 'string', 'max:50'],
            'form_id' => ['sometimes', 'uuid'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
        ]);

        $filters = array_filter([
            'status' => $validated['status'] ?? null,
            'form_id' => $validated['form_id'] ?? null,
            'from' => isset($validated['from']) ? (string) $validated['from'] : null,
            'to' => isset($validated['to']) ? (string) $validated['to'] : null,
            'search' => $query['search'] !== '' ? $query['search'] : null,
        ], static fn ($v) => $v !== null && $v !== '');

        $filename = 'e-approval-submissions-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($export, $filters): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $export->headers());

            foreach ($export->rows($filters) as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
