<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DocExtract\Services\DocExtractBatchService;
use App\Modules\DocExtract\Services\DocExtractPlanFeaturesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DocExtractPreviewController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        DocExtractBatchService $batches,
        DocExtractPlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('doc-extract:run'), 403);
        $planFeatures->assertModuleEnabled();

        $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file'],
        ]);

        $previews = $batches->previewFiles($request->file('files', []));

        return $this->ok(['files' => $previews]);
    }
}
