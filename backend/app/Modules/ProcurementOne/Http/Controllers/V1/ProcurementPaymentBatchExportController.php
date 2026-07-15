<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use App\Modules\ProcurementOne\Services\ProcurementPaymentBatchExportService;
use App\Modules\ProcurementOne\Services\ProcurementPaymentBatchService;
use App\Modules\ProcurementOne\Support\ProcurementPaymentBatchStatus;
use App\Modules\ProcurementOne\Support\FinanceOneAccess;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ProcurementPaymentBatchExportController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $paymentBatch,
        ProcurementPaymentBatchService $batches,
        ProcurementPaymentBatchExportService $export,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): StreamedResponse {
        FinanceOneAccess::authorizeReportsView($request->user());
        $planFeatures->assertPaymentTrackingEnabled();

        $batch = $batches->find($paymentBatch);
        abort_if($batch === null, 404);
        abort_unless(in_array((string) $batch->status, [
            ProcurementPaymentBatchStatus::SCHEDULED,
            ProcurementPaymentBatchStatus::EXPORTED,
        ], true), 422, __('Only scheduled or exported batches can be downloaded.'));

        $filename = 'payment-batch-'.($batch->document_no ?? $batch->id).'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($export, $batch): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $export->headers());

            foreach ($export->rowsForBatch($batch) as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
