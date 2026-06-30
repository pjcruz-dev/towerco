<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Models\ProcurementContract;
use App\Modules\ProcurementOne\Services\ProcurementContractRegistryService;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use App\Modules\ProcurementOne\Support\ProcurementContractStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementVendorContractsIndexController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $vendor,
        ProcurementContractRegistryService $registry,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('procurement_one:view'), 403);
        $planFeatures->assertVendorContractsEnabled();

        $contracts = ProcurementContract::query()
            ->with(['vendor:id,vendor_code,company_name', 'site:id,site_code,name'])
            ->where('vendor_id', $vendor)
            ->where('status', ProcurementContractStatus::ACTIVE)
            ->orderByDesc('activated_at')
            ->limit(100)
            ->get()
            ->map(static fn ($row) => $registry->toListPayload($row))
            ->values()
            ->all();

        return $this->ok($contracts);
    }
}
