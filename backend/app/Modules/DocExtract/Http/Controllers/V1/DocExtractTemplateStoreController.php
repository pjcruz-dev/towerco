<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DocExtract\Services\DocExtractPlanFeaturesService;
use App\Modules\DocExtract\Services\DocExtractTemplateService;
use App\Modules\DocExtract\Support\DocExtractFieldTypes;
use App\Modules\DocExtract\Support\DocExtractTemplateStatus;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class DocExtractTemplateStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        DocExtractTemplateService $templates,
        DocExtractPlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('doc-extract:templates:manage'), 403);
        $planFeatures->assertModuleEnabled();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'string', Rule::in(DocExtractTemplateStatus::all())],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.key' => ['nullable', 'string', 'max:80'],
            'fields.*.label' => ['required', 'string', 'max:180'],
            'fields.*.type' => ['nullable', 'string', DocExtractFieldTypes::rule()],
            'fields.*.description' => ['nullable', 'string', 'max:500'],
            'fields.*.hint' => ['nullable', 'string', 'max:500'],
            'fields.*.columns' => ['nullable', 'array', 'max:20'],
            'fields.*.columns.*.key' => ['nullable', 'string', 'max:80'],
            'fields.*.columns.*.label' => ['required_with:fields.*.columns', 'string', 'max:180'],
            'fields.*.columns.*.type' => ['nullable', 'string', DocExtractFieldTypes::tableColumnRule()],
            'fields.*.columns.*.description' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var TenantUser $actor */
        $actor = $request->user();
        $template = $templates->create($data, $actor);

        return $this->ok($templates->asRow($template), 201);
    }
}
