<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DocExtract\Services\DocExtractBatchService;
use App\Modules\DocExtract\Services\DocExtractExportService;
use App\Modules\DocExtract\Services\DocExtractPlanFeaturesService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DocExtractBatchExportController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $batch,
        DocExtractBatchService $batches,
        DocExtractExportService $exports,
        DocExtractPlanFeaturesService $planFeatures,
    ): StreamedResponse {
        abort_unless($request->user()?->can('doc-extract:export'), 403);
        $planFeatures->assertModuleEnabled();

        $format = strtolower((string) $request->query('format', 'csv'));
        $model = $batches->findOrFail($batch);

        return $format === 'xlsx'
            ? $exports->exportXlsx($model)
            : $exports->exportCsv($model);
    }
}
