<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use App\Modules\ProcurementOne\Services\ProcurementPaymentBatchService;
use Illuminate\Http\JsonResponse;
use App\Modules\ProcurementOne\Support\FinanceOneAccess;
use Illuminate\Http\Request;

final class ProcurementPaymentBatchIndexController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ProcurementPaymentBatchService $service,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        FinanceOneAccess::authorizeView($request->user());
        $planFeatures->assertPaymentTrackingEnabled();

        $data = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', 'nullable', 'string', 'max:32'],
            'sort' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        $paginator = $service->paginate(
            max(1, (int) ($data['page'] ?? 1)),
            min(100, max(1, (int) ($data['per_page'] ?? 25))),
            $data['status'] ?? null,
            $data['sort'] ?? null,
        );

        return $this->okWithMeta(
            $paginator->getCollection()->map(static fn ($row) => $service->toListPayload($row))->values()->all(),
            [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }
}
