<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DocExtract\Services\DocExtractPlanFeaturesService;
use App\Modules\DocExtract\Services\DocExtractTemplateService;
use App\Modules\DocExtract\Support\DocExtractTemplateStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class DocExtractTemplateIndexController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        DocExtractTemplateService $templates,
        DocExtractPlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('doc-extract:view'), 403);
        $planFeatures->assertModuleEnabled();

        $data = $request->validate([
            'status' => ['nullable', 'string', Rule::in(DocExtractTemplateStatus::all())],
        ]);

        $status = isset($data['status']) && is_string($data['status']) ? $data['status'] : null;

        return $this->ok($templates->list($status));
    }
}
