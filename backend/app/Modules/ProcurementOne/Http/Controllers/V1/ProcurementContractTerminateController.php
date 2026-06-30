<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Services\ProcurementContractRegistryService;
use App\Modules\ProcurementOne\Services\ProcurementContractService;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use Illuminate\Http\JsonResponse;
use App\Modules\ProcurementOne\Support\FinanceOneAccess;
use Illuminate\Http\Request;

final class ProcurementContractTerminateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $contract,
        ProcurementContractService $service,
        ProcurementContractRegistryService $registry,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        FinanceOneAccess::authorizeContractsManage($request->user());
        $planFeatures->assertVendorContractsEnabled();

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $model = $service->find($contract);
        abort_if($model === null, 404);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $updated = $service->terminate($model, $actor, (string) $validated['reason']);

        return $this->ok(['contract' => $registry->toDetailPayload($updated)]);
    }
}
