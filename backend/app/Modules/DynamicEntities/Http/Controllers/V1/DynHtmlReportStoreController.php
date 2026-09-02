<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Services\DynHtmlReportService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynHtmlReportStoreController extends AbstractApiController
{
    public function __invoke(Request $request, DynHtmlReportService $service): JsonResponse
    {
        abort_unless($request->user()?->can('html_reports:manage'), 403);

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'html_source' => ['nullable', 'string'],
            'css_source' => ['nullable', 'string'],
            'js_source' => ['nullable', 'string'],
            'builder_json' => ['nullable', 'array'],
        ]);

        return $this->ok($service->create($data, $actor), 201);
    }
}
