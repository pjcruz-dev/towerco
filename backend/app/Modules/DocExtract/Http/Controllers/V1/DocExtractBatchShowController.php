<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DocExtract\Services\DocExtractBatchService;
use App\Modules\DocExtract\Services\DocExtractPlanFeaturesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DocExtractBatchShowController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $batch,
        DocExtractBatchService $batches,
        DocExtractPlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('doc-extract:view'), 403);
        $planFeatures->assertModuleEnabled();

        $model = $batches->findOrFail($batch);

        return $this->ok($batches->asDetail($model));
    }
}
