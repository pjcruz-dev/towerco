<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DocExtract\Services\DocExtractPlanFeaturesService;
use App\Modules\DocExtract\Services\DocExtractTemplateService;
use App\Modules\DocExtract\Support\DocExtractFieldTypes;
use App\Modules\DocExtract\Support\DocExtractTemplateStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class DocExtractTemplateUpdateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $template,
        DocExtractTemplateService $templates,
        DocExtractPlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('doc-extract:templates:manage'), 403);
        $planFeatures->assertModuleEnabled();

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['sometimes', 'required', 'string', Rule::in(DocExtractTemplateStatus::all())],
            'fields' => ['sometimes', 'required', 'array', 'min:1'],
            'fields.*.key' => ['nullable', 'string', 'max:80'],
            'fields.*.label' => ['required_with:fields', 'string', 'max:180'],
            'fields.*.type' => ['nullable', 'string', DocExtractFieldTypes::rule()],
            'fields.*.description' => ['nullable', 'string', 'max:500'],
            'fields.*.hint' => ['nullable', 'string', 'max:500'],
            'fields.*.columns' => ['nullable', 'array', 'max:20'],
            'fields.*.columns.*.key' => ['nullable', 'string', 'max:80'],
            'fields.*.columns.*.label' => ['required_with:fields.*.columns', 'string', 'max:180'],
            'fields.*.columns.*.type' => ['nullable', 'string', DocExtractFieldTypes::tableColumnRule()],
            'fields.*.columns.*.description' => ['nullable', 'string', 'max:500'],
        ]);

        $model = $templates->findOrFail($template);
        $updated = $templates->update($model, $data, $request->user());

        return $this->ok($templates->asRow($updated));
    }
}
