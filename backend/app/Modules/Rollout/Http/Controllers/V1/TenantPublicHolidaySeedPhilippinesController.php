<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Services\TenantPublicHolidayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantPublicHolidaySeedPhilippinesController extends AbstractApiController
{
    public function __invoke(Request $request, TenantPublicHolidayService $service): JsonResponse
    {
        abort_unless($request->user()?->can('project_one:playbook:configure'), 403);

        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'region' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        $count = $service->seedPhilippinesYear((int) $data['year'], $data['region'] ?? null);

        return $this->ok([
            'year' => (int) $data['year'],
            'seeded_count' => $count,
            'holidays' => $service->listForYear((int) $data['year']),
        ]);
    }
}
