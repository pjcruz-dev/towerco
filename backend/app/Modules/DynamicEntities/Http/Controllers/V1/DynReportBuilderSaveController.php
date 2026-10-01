<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynHtmlReport;
use App\Modules\DynamicEntities\Services\DynReportBuilderService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynReportBuilderSaveController extends AbstractApiController
{
    public function __invoke(Request $request, DynReportBuilderService $service): JsonResponse
    {
        abort_unless($request->user()?->can('html_reports:manage'), 403);

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $data = $request->validate([
            'id' => ['nullable', 'uuid'],
            'title' => ['required', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:2000'],
            'slug' => ['nullable', 'string', 'max:160'],
            'entity_slug' => ['required', 'string', 'max:160'],
            'format' => ['nullable', 'string', 'in:summary,detail,matrix'],
            'metric' => ['nullable', 'string', 'in:count,sum'],
            'metric_field' => ['nullable', 'string', 'max:120'],
            'group_by' => ['nullable', 'string', 'max:120'],
            'group_by_2' => ['nullable', 'string', 'max:120'],
            'matrix_column' => ['nullable', 'string', 'max:120'],
            'date_grouping' => ['nullable', 'string', 'in:exact,day,week,month,year'],
            'filters' => ['nullable', 'array', 'max:40'],
            'filters.*.field' => ['required_with:filters', 'string', 'max:120'],
            'filters.*.op' => ['nullable', 'string', 'max:40'],
            'filters.*.value' => ['nullable'],
            'chart' => ['nullable', 'string', 'max:40'],
            'sort_by' => ['nullable', 'string', 'in:metric,group'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'row_limit' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'currency_prefix' => ['nullable', 'string', 'max:8'],
            'saved_slug' => ['nullable', 'string', 'max:160'],
        ]);

        $existing = null;
        if (! empty($data['id'])) {
            $existing = DynHtmlReport::query()->findOrFail((string) $data['id']);
        }

        $report = $service->saveAsHtmlReport($data, $actor, $existing);

        return $this->ok($report, $existing ? 200 : 201);
    }
}
