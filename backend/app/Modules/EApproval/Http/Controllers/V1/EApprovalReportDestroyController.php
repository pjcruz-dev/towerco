<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalReportDefinition;
use App\Modules\EApproval\Services\EApprovalReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalReportDestroyController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $report,
        EApprovalReportService $reports,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:audit:view'), 403);

        $model = EApprovalReportDefinition::query()->findOrFail($report);
        abort_unless((string) $model->user_id === (string) $request->user()->id, 403);

        $reports->delete($model);

        return $this->ok(['deleted' => true]);
    }
}
