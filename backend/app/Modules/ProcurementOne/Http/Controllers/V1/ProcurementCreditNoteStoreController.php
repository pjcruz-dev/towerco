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

final class ProcurementCreditNoteStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ProcurementCreditNoteService $service,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        FinanceOneAccess::authorizeDocumentsCreate($request->user());
        $planFeatures->assertApInvoicesEnabled();

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $data = $request->validate([
            'po_id' => ['required', 'uuid', 'exists:procurement_pos,id'],
            'ap_invoice_id' => ['nullable', 'uuid', 'exists:procurement_ap_invoices,id'],
            'vendor_credit_note_no' => ['nullable', 'string', 'max:120'],
            'credit_date' => ['nullable', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $note = $service->create($data, $actor);

        return $this->created($service->asPayload($note));
    }
}
