<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DocExtract\Models\DocExtractDocument;
use App\Modules\DocExtract\Services\DocExtractBatchService;
use App\Modules\DocExtract\Services\DocExtractPlanFeaturesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DocExtractDocumentUpdateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $document,
        DocExtractBatchService $batches,
        DocExtractPlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('doc-extract:run'), 403);
        $planFeatures->assertModuleEnabled();

        $data = $request->validate([
            'field_values' => ['required', 'array'],
        ]);

        $model = DocExtractDocument::query()->findOrFail($document);
        $updated = $batches->updateDocumentFields($model, $data['field_values']);

        return $this->ok($batches->asDocumentRow($updated));
    }
}
