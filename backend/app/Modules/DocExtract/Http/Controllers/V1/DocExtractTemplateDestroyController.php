<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DocExtract\Services\DocExtractPlanFeaturesService;
use App\Modules\DocExtract\Services\DocExtractTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DocExtractTemplateDestroyController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $template,
        DocExtractTemplateService $templates,
        DocExtractPlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('doc-extract:templates:manage'), 403);
        $planFeatures->assertModuleEnabled();

        $model = $templates->findOrFail($template);
        $templates->delete($model, $request->user());

        return $this->ok(['deleted' => true]);
    }
}
