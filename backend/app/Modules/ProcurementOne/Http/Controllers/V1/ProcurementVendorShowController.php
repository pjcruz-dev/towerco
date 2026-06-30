<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementVendorRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementVendorShowController extends AbstractApiController
{
    public function __invoke(Request $request, string $vendor, ProcurementVendorRegistryService $registry): JsonResponse
    {
        abort_unless($request->user()?->can('procurement_one:vendors:view'), 403);

        $model = $registry->find($vendor);
        abort_if($model === null, 404);

        return $this->ok($registry->toDetailPayload($model));
    }
}
