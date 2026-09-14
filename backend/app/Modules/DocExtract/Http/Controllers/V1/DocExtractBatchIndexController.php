<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DocExtract\Services\DocExtractBatchService;
use App\Modules\DocExtract\Services\DocExtractPlanFeaturesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DocExtractBatchIndexController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        DocExtractBatchService $batches,
        DocExtractPlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('doc-extract:view'), 403);
        $planFeatures->assertModuleEnabled();

        $result = $batches->paginate(
            (int) $request->query('page', 1),
            (int) $request->query('per_page', 20),
            $request->query('status'),
            $request->query('search'),
            $request->query('sort'),
        );

        return $this->okWithMeta($result['data'], $result['meta']);
    }
}
