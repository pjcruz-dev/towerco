<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Services\DynReportBuilderAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynReportBuilderAiBuildController extends AbstractApiController
{
    public function __invoke(Request $request, DynReportBuilderAiService $service): JsonResponse
    {
        abort_unless($request->user()?->can('html_reports:manage'), 403);

        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:4000'],
            'entity_slug' => ['nullable', 'string', 'max:160'],
        ]);

        return $this->ok($service->build($data));
    }
}
