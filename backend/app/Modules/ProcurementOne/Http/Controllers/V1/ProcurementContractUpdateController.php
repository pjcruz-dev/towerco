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

final class ProcurementContractUpdateController extends AbstractApiController
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
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'vendor_id' => ['sometimes', 'uuid', 'exists:procurement_vendors,id'],
            'site_id' => ['nullable', 'uuid', 'exists:sites,id'],
            'primary_document_id' => ['nullable', 'uuid', 'exists:documents,id'],
            'spend_ceiling' => ['nullable', 'numeric', 'min:0'],
            'currency_code' => ['sometimes', 'string', 'max:8'],
            'effective_from' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'documents' => ['sometimes', 'array'],
            'documents.*.document_id' => ['nullable', 'uuid', 'exists:documents,id'],
            'documents.*.label' => ['required_with:documents', 'string', 'max:255'],
            'documents.*.document_kind' => ['nullable', 'string', 'max:64'],
            'documents.*.file_name' => ['nullable', 'string', 'max:255'],
        ]);

        $updated = $service->update($model, $validated, $actor);

        return $this->ok(['contract' => $registry->toDetailPayload($updated)]);
    }
}
