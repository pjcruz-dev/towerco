<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynHtmlReport;
use App\Modules\DynamicEntities\Services\DynHtmlReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynHtmlReportDestroyController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        DynHtmlReport $report,
        DynHtmlReportService $service,
    ): JsonResponse {
        abort_unless($request->user()?->can('html_reports:manage'), 403);

        $service->destroy($report);

        return $this->ok(['deleted' => true]);
    }
}
