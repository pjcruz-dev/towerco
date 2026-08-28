<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalReportDefinition;
use App\Modules\EApproval\Services\EApprovalReportService;
use App\Modules\EApproval\Support\EApprovalExportViewerScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalReportDestroyController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $report,
        EApprovalReportService $reports,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user !== null && EApprovalExportViewerScope::userCanExport($user), 403);

        $model = EApprovalReportDefinition::query()->findOrFail($report);
        abort_unless((string) $model->user_id === (string) $user->id, 403);

        $reports->delete($model);

        return $this->ok(['deleted' => true]);
    }
}
