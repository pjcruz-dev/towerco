<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Services\PurchaseMonitoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PurchaseMonitoringReportController extends AbstractApiController
{
    public function __invoke(Request $request, PurchaseMonitoringService $service): JsonResponse
    {
        abort_unless($request->user()?->can('dynamic_entities:view'), 403);

        $data = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'supplier_id' => ['nullable', 'string', 'max:64'],
            'transaction_type' => ['nullable', 'string', 'max:64'],
        ]);

        return $this->ok($service->build($data));
    }
}
