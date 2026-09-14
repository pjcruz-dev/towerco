<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DocExtract\Services\DocExtractBatchService;
use App\Modules\DocExtract\Services\DocExtractPlanFeaturesService;
use App\Modules\DocExtract\Services\DocExtractTemplateService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DocExtractBatchSaveTemplateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $batch,
        DocExtractBatchService $batches,
        DocExtractTemplateService $templates,
        DocExtractPlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('doc-extract:templates:manage'), 403);
        $planFeatures->assertModuleEnabled();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var TenantUser $actor */
        $actor = $request->user();
        $model = $batches->findOrFail($batch);
        $template = $batches->saveDiscoveredTemplate(
            $model,
            (string) $data['name'],
            isset($data['description']) ? (string) $data['description'] : null,
            $actor,
        );

        return $this->ok($templates->asRow($template), 201);
    }
}
