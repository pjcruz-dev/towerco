<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Services\AtcExtendedReportsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AtcExtendedReportsController extends AbstractApiController
{
    public function __invoke(Request $request, string $report, AtcExtendedReportsService $service): JsonResponse
    {
        abort_unless($request->user()?->can('dynamic_entities:view'), 403);
        abort_unless(in_array($report, AtcExtendedReportsService::reportKeys(), true), 404, 'Unknown extended report.');

        $filters = $request->validate([
            'as_of' => ['nullable', 'date'],
            'warehouse_id' => ['nullable', 'string', 'max:64'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'period' => ['nullable', 'string', 'max:32'],
            'month' => ['nullable', 'string', 'max:7'],
        ]);

        return $this->ok($service->build($report, $filters));
    }
}
