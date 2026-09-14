<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DocExtract\Services\DocExtractAuditLogger;
use App\Modules\DocExtract\Services\DocExtractBatchService;
use App\Modules\DocExtract\Services\DocExtractPlanFeaturesService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DocExtractBatchRequeueController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $batch,
        DocExtractBatchService $batches,
        DocExtractPlanFeaturesService $planFeatures,
        DocExtractAuditLogger $audit,
    ): JsonResponse {
        abort_unless($request->user()?->can('doc-extract:run'), 403);
        $planFeatures->assertModuleEnabled();

        $model = $batches->findOrFail($batch);
        $model->loadMissing('template');

        $result = $batches->requeueStuckDocuments(
            batchId: (string) $model->id,
            tenantId: (string) (tenant('id') ?? ''),
        );

        $actor = $request->user();
        if ($actor instanceof TenantUser) {
            $audit->record(
                action: 'batch.requeued',
                summary: __('Requeued :count stuck DocExtract document(s).', ['count' => $result['requeued']]),
                entityType: 'batch',
                entityId: (string) $model->id,
                entityLabel: $model->template?->name ?? 'Auto-detect',
                actor: $actor,
                changes: [
                    'requeued' => ['from' => null, 'to' => $result['requeued']],
                ],
            );
        }

        $fresh = $batches->findOrFail($batch);

        return $this->ok([
            ...$batches->asListRow($fresh),
            'requeued' => $result['requeued'],
        ]);
    }
}
