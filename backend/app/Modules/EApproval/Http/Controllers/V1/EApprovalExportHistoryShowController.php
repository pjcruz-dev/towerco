<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalExportHistoryShowController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $history,
        EApprovalReportService $reports,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:audit:view'), 403);

        $model = $reports->findHistoryForUser($request->user(), $history);

        return $this->ok($reports->presentHistory($model));
    }
}
