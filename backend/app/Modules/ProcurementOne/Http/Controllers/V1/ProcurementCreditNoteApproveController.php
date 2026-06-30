<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Services\ProcurementCreditNoteService;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use Illuminate\Http\JsonResponse;
use App\Modules\ProcurementOne\Support\FinanceOneAccess;
use Illuminate\Http\Request;

final class ProcurementCreditNoteApproveController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $creditNote,
        ProcurementCreditNoteService $service,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        FinanceOneAccess::authorizeDocumentsManage($request->user());
        $planFeatures->assertApInvoicesEnabled();

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $model = $service->find($creditNote);
        abort_if($model === null, 404);

        $approved = $service->approve($model, $actor);

        return $this->ok($service->asPayload($approved));
    }
}
