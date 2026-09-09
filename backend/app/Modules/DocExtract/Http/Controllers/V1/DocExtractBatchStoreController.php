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

final class DocExtractBatchStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        DocExtractBatchService $batches,
        DocExtractPlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('doc-extract:run'), 403);
        $planFeatures->assertModuleEnabled();

        $data = $request->validate([
            'template_id' => ['nullable', 'uuid'],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file'],
        ]);

        /** @var TenantUser $actor */
        $actor = $request->user();

        $templateId = isset($data['template_id']) && is_string($data['template_id']) && $data['template_id'] !== ''
            ? $data['template_id']
            : null;

        $batch = $batches->create(
            $templateId,
            $request->file('files', []),
            $actor,
        );

        return $this->ok($batches->asDetail($batch), 201);
    }
}
