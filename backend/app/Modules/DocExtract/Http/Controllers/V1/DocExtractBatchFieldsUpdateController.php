<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DocExtract\Services\DocExtractBatchService;
use App\Modules\DocExtract\Services\DocExtractPlanFeaturesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DocExtractBatchFieldsUpdateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $batch,
        DocExtractBatchService $batches,
        DocExtractPlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('doc-extract:run'), 403);
        $planFeatures->assertModuleEnabled();

        $data = $request->validate([
            'fields' => ['sometimes', 'array'],
            'fields.*.key' => ['nullable', 'string', 'max:80'],
            'fields.*.label' => ['required_with:fields', 'string', 'max:180'],
            'fields.*.type' => ['nullable', 'string', 'max:32'],
            'fields.*.hint' => ['nullable', 'string', 'max:500'],
            'fields.*.description' => ['nullable', 'string', 'max:500'],
            'fields.*.columns' => ['nullable', 'array', 'max:20'],
            'remove_key' => ['sometimes', 'string', 'max:80'],
        ]);

        $model = $batches->findOrFail($batch);

        /** @var \App\Modules\Identity\Models\TenantUser $actor */
        $actor = $request->user();

        if (isset($data['remove_key']) && is_string($data['remove_key']) && $data['remove_key'] !== '') {
            $fields = $batches->removeField($model, $data['remove_key'], $actor);
        } else {
            $fields = $batches->updateFieldSchema($model, $data['fields'] ?? [], $actor);
        }

        $fresh = $batches->findOrFail($batch);

        return $this->ok([
            ...$batches->asDetail($fresh),
            'effective_fields' => $fields,
        ]);
    }
}
