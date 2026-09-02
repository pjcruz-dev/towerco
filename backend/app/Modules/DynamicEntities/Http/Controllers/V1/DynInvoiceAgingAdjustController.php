<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Services\InvoiceAgingWorkbenchService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynInvoiceAgingAdjustController extends AbstractApiController
{
    public function __invoke(Request $request, InvoiceAgingWorkbenchService $service): JsonResponse
    {
        abort_unless(
            $request->user()?->can('dynamic_entities:records:manage')
            || $request->user()?->can('dynamic_entities:view'),
            403,
        );

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);
        abort_unless($request->user()?->can('dynamic_entities:records:manage'), 403);

        $data = $request->validate([
            'record_id' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->ok($service->postAdjustment($data, $actor));
    }
}
