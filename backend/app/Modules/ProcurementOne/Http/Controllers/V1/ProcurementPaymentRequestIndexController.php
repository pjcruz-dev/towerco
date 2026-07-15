<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use App\Modules\ProcurementOne\Services\ProcurementPaymentRequestRegistryService;
use Illuminate\Http\JsonResponse;
use App\Modules\ProcurementOne\Support\FinanceOneAccess;
use Illuminate\Http\Request;

final class ProcurementPaymentRequestIndexController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ProcurementPaymentRequestRegistryService $registry,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        FinanceOneAccess::authorizeView($request->user());
        $planFeatures->assertPaymentTrackingEnabled();

        $data = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', 'string', 'max:32'],
            'vendor_code' => ['sometimes', 'nullable', 'string', 'max:64'],
            'ap_invoice_id' => ['sometimes', 'nullable', 'uuid'],
            'sort' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        $paginator = $registry->paginate(
            max(1, (int) ($data['page'] ?? 1)),
            min(100, max(1, (int) ($data['per_page'] ?? 25))),
            $data['search'] ?? null,
            $data['status'] ?? null,
            $data['vendor_code'] ?? null,
            $data['ap_invoice_id'] ?? null,
            $data['sort'] ?? null,
        );

        return $this->okWithMeta(
            $paginator->getCollection()->map(static fn ($row) => $registry->toListPayload($row))->values()->all(),
            [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }
}
