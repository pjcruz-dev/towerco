<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DocExtract\Services\DocExtractAuditLogger;
use App\Modules\DocExtract\Services\DocExtractBatchService;
use App\Modules\DocExtract\Services\DocExtractExportService;
use App\Modules\DocExtract\Services\DocExtractPlanFeaturesService;
use App\Modules\Identity\Models\TenantUser;
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
        DocExtractAuditLogger $audit,
    ): StreamedResponse {
        abort_unless($request->user()?->can('doc-extract:export'), 403);
        $planFeatures->assertModuleEnabled();

        $format = strtolower((string) $request->query('format', 'csv'));
        $model = $batches->findOrFail($batch);

        /** @var TenantUser|null $actor */
        $actor = $request->user();
        $audit->record(
            action: 'batch.exported',
            summary: __('DocExtract batch exported as :format.', ['format' => strtoupper($format === 'xlsx' ? 'XLSX' : 'CSV')]),
            entityType: 'batch',
            entityId: (string) $model->id,
            entityLabel: $model->template?->name ?? 'Auto-detect',
            actor: $actor,
            changes: [
                'format' => ['from' => null, 'to' => $format === 'xlsx' ? 'xlsx' : 'csv'],
            ],
        );

        return $format === 'xlsx'
            ? $exports->exportXlsx($model)
            : $exports->exportCsv($model);
    }
}
