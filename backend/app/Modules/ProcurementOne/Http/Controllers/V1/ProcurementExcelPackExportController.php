<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementExcelPackExportService;
use App\Modules\ProcurementOne\Services\ProcurementExportDateRangeService;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ProcurementExcelPackExportController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ProcurementExcelPackExportService $export,
        ProcurementExportDateRangeService $dateRange,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): Response {
        abort_unless($request->user()?->can('procurement_one:documents:manage'), 403);
        $planFeatures->assertReportingExportsEnabled();

        $filters = $request->validate([
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'period' => ['sometimes', 'string', 'in:current_month,previous_month'],
        ]);

        $binary = $export->buildBinary($filters, $dateRange);
        $filename = $export->filename($filters, $dateRange);

        return response($binary, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
