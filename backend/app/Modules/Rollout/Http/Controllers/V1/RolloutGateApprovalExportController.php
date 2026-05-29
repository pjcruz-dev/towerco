<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Services\RolloutGateApprovalExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RolloutGateApprovalExportController extends AbstractApiController
{
    public function __invoke(Request $request, RolloutGateApprovalExportService $export): StreamedResponse
    {
        abort_unless($request->user()?->can('project_one:rollout:view'), 403);

        $data = $request->validate([
            'status' => ['sometimes', 'string', 'in:in_review,approved,rejected,cancelled,all'],
        ]);

        $rows = $export->rows($data['status'] ?? 'all');
        $filename = 'gate-approvals-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
