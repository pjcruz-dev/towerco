<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalFileStorageService;
use App\Modules\EApproval\Services\EApprovalReportService;
use App\Modules\EApproval\Support\EApprovalExportHistoryStatus;
use App\Modules\EApproval\Support\EApprovalExportViewerScope;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EApprovalExportHistoryDownloadController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $history,
        EApprovalReportService $reports,
        EApprovalFileStorageService $files,
    ): Response {
        $user = $request->user();
        abort_unless($user !== null && EApprovalExportViewerScope::userCanExport($user), 403);

        $model = $reports->findHistoryForUser($user, $history);
        abort_unless($model->status === EApprovalExportHistoryStatus::COMPLETED, 409, 'Export is not ready yet.');

        return $files->downloadExport($model);
    }
}
