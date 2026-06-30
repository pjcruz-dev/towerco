<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Services\ProcurementGrnRegistryService;
use App\Modules\ProcurementOne\Services\ProcurementGrnService;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementGrnPostController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $grn,
        ProcurementGrnService $service,
        ProcurementGrnRegistryService $registry,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('procurement_one:documents:create'), 403);
        $planFeatures->assertGoodsReceiptEnabled();

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $model = $registry->find($grn);
        abort_if($model === null, 404);

        $result = $service->post($model, $actor);
        $fresh = $registry->find((string) $result['grn']->id);

        return $this->ok([
            'grn' => $registry->toDetailPayload($fresh ?? $result['grn']),
            'warning' => $result['warning'],
        ]);
    }
}
