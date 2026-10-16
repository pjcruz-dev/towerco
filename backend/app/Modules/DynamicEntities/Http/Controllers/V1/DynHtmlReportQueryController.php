<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Services\DynHtmlReportQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynHtmlReportQueryController extends AbstractApiController
{
    public function __invoke(Request $request, DynHtmlReportQueryService $queries): JsonResponse
    {
        abort_unless(
            $request->user()?->can('html_reports:manage')
            || $request->user()?->can('dynamic_entities:view'),
            403,
        );

        $data = $request->validate([
            'sql' => ['required', 'string', 'max:12000'],
        ]);

        return $this->ok([
            'rows' => $queries->select((string) $data['sql']),
        ]);
    }
}
