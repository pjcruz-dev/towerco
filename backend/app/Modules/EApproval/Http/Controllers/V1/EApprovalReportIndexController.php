<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalReportService;
use App\Modules\EApproval\Support\EApprovalExportViewerScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalReportIndexController extends AbstractApiController
{
    public function __invoke(Request $request, EApprovalReportService $reports): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null && EApprovalExportViewerScope::userCanExport($user), 403);

        $items = $reports->listForUser($user)
            ->map(fn ($report) => $reports->present($report))
            ->values()
            ->all();

        return $this->ok($items);
    }
}
