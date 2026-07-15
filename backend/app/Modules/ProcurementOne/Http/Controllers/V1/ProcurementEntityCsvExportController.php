<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementCsvExportService;
use App\Modules\ProcurementOne\Services\ProcurementExportDateRangeService;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use App\Modules\ProcurementOne\Support\ProcurementExportEntity;
use App\Modules\ProcurementOne\Support\FinanceOneAccess;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ProcurementEntityCsvExportController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $entity,
        ProcurementCsvExportService $export,
        ProcurementExportDateRangeService $dateRange,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): StreamedResponse {
        FinanceOneAccess::authorizeReportsView($request->user());
        $planFeatures->assertReportingExportsEnabled();
        abort_unless(ProcurementExportEntity::isValid($entity), 404);

        $filters = $request->validate([
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'period' => ['sometimes', 'string', 'in:current_month,previous_month'],
        ]);

        $range = $dateRange->resolve($filters);
        $filename = $export->filename($entity, $range['from'], $range['to']);

        return response()->streamDownload(function () use ($export, $entity, $range): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $export->headers($entity));

            foreach ($export->rows($entity, $range['from'], $range['to']) as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
