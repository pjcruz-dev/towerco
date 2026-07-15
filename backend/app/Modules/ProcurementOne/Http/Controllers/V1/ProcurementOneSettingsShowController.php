<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use App\Modules\ProcurementOne\Services\ProcurementOneSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProcurementOneSettingsShowController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ProcurementOneSettingsService $settings,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('procurement_one:settings:manage'), 403);
        $planFeatures->assertModuleEnabled();

        return $this->ok($settings->snapshot());
    }
}
