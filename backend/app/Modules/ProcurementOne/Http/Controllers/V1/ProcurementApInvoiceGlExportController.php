<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementApInvoiceGlExportService;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use App\Modules\ProcurementOne\Support\FinanceOneAccess;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ProcurementApInvoiceGlExportController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ProcurementApInvoiceGlExportService $export,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): StreamedResponse {
        FinanceOneAccess::authorizeReportsView($request->user());
        $planFeatures->assertApInvoicesEnabled();

        $filters = $request->validate([
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
        ]);

        $filename = 'ap-invoices-gl-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($export, $filters): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $export->headers());

            foreach ($export->rows($filters) as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
