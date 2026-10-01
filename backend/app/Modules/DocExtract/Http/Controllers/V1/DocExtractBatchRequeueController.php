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

        $result = $batches->remapBatchFieldValues(
            batchId: (string) $model->id,
            tenantId: (string) (tenant('id') ?? ''),
        );

        $actor = $request->user();
        if ($actor instanceof TenantUser) {
            $audit->record(
                action: 'batch.requeued',
                summary: ($result['queued'] ?? false)
                    ? __('Queued DocExtract value rescan for :count document(s).', [
                        'count' => $result['document_count'] ?? 0,
                    ])
                    : __('Remapped :remapped DocExtract value(s); requeued :requeued stuck document(s).', [
                        'remapped' => $result['remapped'] ?? 0,
                        'requeued' => ($result['requeued'] ?? 0) + ($result['rescanned'] ?? 0),
                    ]),
                entityType: 'batch',
                entityId: (string) $model->id,
                entityLabel: $model->template?->name ?? 'Auto-detect',
                actor: $actor,
                changes: [
                    'queued' => ['from' => null, 'to' => $result['queued'] ?? false],
                    'document_count' => ['from' => null, 'to' => $result['document_count'] ?? 0],
                    'remapped' => ['from' => null, 'to' => $result['remapped'] ?? 0],
                    'rescanned' => ['from' => null, 'to' => $result['rescanned'] ?? 0],
                    'requeued' => ['from' => null, 'to' => $result['requeued'] ?? 0],
                ],
            );
        }

        $fresh = $batches->findOrFail($batch);

        return $this->ok([
            ...$batches->asListRow($fresh),
            'queued' => (bool) ($result['queued'] ?? false),
            'document_count' => (int) ($result['document_count'] ?? 0),
            'requeued' => (int) (($result['requeued'] ?? 0) + ($result['rescanned'] ?? 0)),
            'remapped' => (int) ($result['remapped'] ?? 0),
            'rescanned' => (int) ($result['rescanned'] ?? 0),
        ]);
    }
}
