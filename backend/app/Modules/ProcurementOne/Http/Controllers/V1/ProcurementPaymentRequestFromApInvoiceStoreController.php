<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Services\ProcurementApInvoiceRegistryService;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use App\Modules\ProcurementOne\Services\ProcurementPaymentRequestRegistryService;
use App\Modules\ProcurementOne\Services\ProcurementPaymentRequestService;
use Illuminate\Http\JsonResponse;
use App\Modules\ProcurementOne\Support\FinanceOneAccess;
use Illuminate\Http\Request;

final class ProcurementPaymentRequestFromApInvoiceStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $apInvoice,
        ProcurementApInvoiceRegistryService $invoices,
        ProcurementPaymentRequestService $service,
        ProcurementPaymentRequestRegistryService $registry,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        FinanceOneAccess::authorizePaymentsManage($request->user());
        $planFeatures->assertPaymentTrackingEnabled();

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $invoice = $invoices->find($apInvoice);
        abort_if($invoice === null, 404);

        $validated = $request->validate([
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $created = $service->createFromInvoice($invoice, $validated, $actor);

        return $this->created(['payment_request' => $registry->toDetailPayload($created)]);
    }
}
