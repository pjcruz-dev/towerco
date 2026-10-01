<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynHtmlReport;
use App\Modules\DynamicEntities\Services\DynHtmlReportService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynHtmlReportUpdateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        DynHtmlReport $report,
        DynHtmlReportService $service,
    ): JsonResponse {
        abort_unless($request->user()?->can('html_reports:manage'), 403);

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'html_source' => ['sometimes', 'nullable', 'string'],
            'css_source' => ['sometimes', 'nullable', 'string'],
            'js_source' => ['sometimes', 'nullable', 'string'],
            'builder_json' => ['sometimes', 'nullable', 'array'],
        ]);

        return $this->ok($service->update($report, $data, $actor));
    }
}
