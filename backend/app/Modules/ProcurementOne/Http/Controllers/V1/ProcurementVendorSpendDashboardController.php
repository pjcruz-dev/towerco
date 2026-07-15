<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementExportDateRangeService;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use App\Modules\ProcurementOne\Services\ProcurementVendorSpendDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementVendorSpendDashboardController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ProcurementVendorSpendDashboardService $service,
        ProcurementExportDateRangeService $dateRange,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('procurement_one:view'), 403);
        $planFeatures->assertReportingExportsEnabled();

        $filters = $request->validate([
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'period' => ['sometimes', 'string', 'in:current_month,previous_month'],
        ]);

        return $this->ok($service->snapshot($filters, $dateRange));
    }
}
