<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Services\ProcurementApInvoiceRegistryService;
use App\Modules\ProcurementOne\Services\ProcurementApInvoiceSubmissionBridgeService;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use Illuminate\Http\JsonResponse;
use App\Modules\ProcurementOne\Support\FinanceOneAccess;
use Illuminate\Http\Request;

final class ProcurementApInvoiceSubmitController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $apInvoice,
        ProcurementApInvoiceSubmissionBridgeService $bridge,
        ProcurementApInvoiceRegistryService $registry,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        FinanceOneAccess::authorizeDocumentsCreate($request->user());
        $planFeatures->assertApInvoicesEnabled();

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $model = $registry->find($apInvoice);
        abort_if($model === null, 404);

        $result = $bridge->submit($model, $actor);
        $fresh = $registry->find((string) $result['invoice']->id);

        return $this->ok([
            'invoice' => $registry->toDetailPayload($fresh ?? $result['invoice']),
            'warning' => $result['warning'],
        ]);
    }
}
