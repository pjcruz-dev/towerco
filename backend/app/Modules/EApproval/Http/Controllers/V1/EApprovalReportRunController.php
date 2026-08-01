<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalReportDefinition;
use App\Modules\EApproval\Services\EApprovalReportService;
use App\Modules\EApproval\Services\EApprovalSubmissionExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EApprovalReportRunController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $report,
        EApprovalReportService $reports,
    ): Response|JsonResponse {
        abort_unless($request->user()?->can('e_approval:audit:view'), 403);

        $validated = $request->validate([
            'async' => ['sometimes', 'boolean'],
        ]);
        $forceAsync = (bool) ($validated['async'] ?? false);

        $model = EApprovalReportDefinition::query()->findOrFail($report);
        abort_unless((string) $model->user_id === (string) $request->user()->id, 403);

        $filters = is_array($model->filters_json) ? $model->filters_json : [];
        $matchedRows = $reports->countMatchingFilters($filters);

        if ($reports->shouldQueue($matchedRows, $forceAsync)) {
            $history = $reports->queueReportRun($request->user(), $model, 'manual');

            return $this->ok([
                'async' => true,
                'history' => $reports->presentHistory($history),
                'matched_rows' => $matchedRows,
                'max_rows' => EApprovalSubmissionExportService::ASYNC_MAX_ROWS,
                'message' => 'Export queued. Download from Recent exports when ready.',
            ], 202);
        }

        try {
            $result = $reports->run($request->user(), $model, 'manual');
        } catch (\InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return response()->download($result['path'], $result['filename'], [
            'Content-Type' => $result['content_type'],
            'X-Export-Truncated' => $result['truncated'] ? '1' : '0',
            'X-Export-Total-Rows' => (string) $result['matched_rows'],
            'X-Export-Max-Rows' => (string) EApprovalSubmissionExportService::SYNC_MAX_ROWS,
            'X-Export-History-Id' => (string) $result['history']->id,
            'Access-Control-Expose-Headers' => 'X-Export-Truncated, X-Export-Total-Rows, X-Export-Max-Rows, X-Export-History-Id',
        ])->deleteFileAfterSend(true);
    }
}
