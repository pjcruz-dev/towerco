<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Services\TenantPublicHolidayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantPublicHolidayIndexController extends AbstractApiController
{
    public function __invoke(Request $request, TenantPublicHolidayService $service): JsonResponse
    {
        abort_unless($request->user()?->can('project_one:view'), 403);

        $year = (int) $request->validate([
            'year' => ['sometimes', 'integer', 'min:2000', 'max:2100'],
        ])['year'] ?? (int) now()->format('Y');

        return $this->ok([
            'year' => $year,
            'holidays' => $service->listForYear($year),
        ]);
    }
}
