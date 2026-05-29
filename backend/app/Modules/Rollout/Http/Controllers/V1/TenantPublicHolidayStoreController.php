<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Services\TenantPublicHolidayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantPublicHolidayStoreController extends AbstractApiController
{
    public function __invoke(Request $request, TenantPublicHolidayService $service): JsonResponse
    {
        abort_unless($request->user()?->can('project_one:playbook:configure'), 403);

        $data = $request->validate([
            'holiday_date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255'],
            'region' => ['sometimes', 'nullable', 'string', 'max:64'],
            'calendar_year' => ['sometimes', 'nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $holiday = $service->create($data);

        return $this->created([
            'id' => $holiday->id,
            'holiday_date' => $holiday->holiday_date->toDateString(),
            'name' => $holiday->name,
            'region' => $holiday->region,
            'calendar_year' => $holiday->calendar_year,
        ]);
    }
}
