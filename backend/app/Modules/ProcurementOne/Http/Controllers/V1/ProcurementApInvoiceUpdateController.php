<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Services\ProcurementApInvoiceRegistryService;
use App\Modules\ProcurementOne\Services\ProcurementApInvoiceService;
use App\Modules\ProcurementOne\Services\ProcurementFormComposeService;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use Illuminate\Http\JsonResponse;
use App\Modules\ProcurementOne\Support\FinanceOneAccess;
use Illuminate\Http\Request;

final class ProcurementApInvoiceUpdateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $apInvoice,
        ProcurementFormComposeService $compose,
        ProcurementApInvoiceService $service,
        ProcurementApInvoiceRegistryService $registry,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        FinanceOneAccess::authorizeDocumentsCreate($request->user());
        $planFeatures->assertApInvoicesEnabled();

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $model = $registry->find($apInvoice);
        abort_if($model === null, 404);

        if ($request->has('values')) {
            $data = $request->validate([
                'values' => ['required', 'array'],
            ]);

            $updated = $compose->updateApInvoice($model, $data['values'], $actor, requireRequired: false);
            $fresh = $registry->find((string) $updated->id);

            return $this->ok($registry->toDetailPayload($fresh ?? $updated));
        }

        $data = $request->validate([
            'grn_id' => ['sometimes', 'nullable', 'uuid'],
            'vendor_invoice_no' => ['sometimes', 'nullable', 'string', 'max:120'],
            'invoice_date' => ['sometimes', 'nullable', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'payment_terms' => ['sometimes', 'nullable', 'string', 'max:120'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'vat_exempt_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'zero_rated_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'vat_rate' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'less_discount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'lines' => ['sometimes', 'array', 'min:1'],
            'lines.*.po_line_id' => ['required_with:lines', 'uuid', 'exists:procurement_po_lines,id'],
            'lines.*.grn_line_id' => ['nullable', 'uuid'],
            'lines.*.quantity_invoiced' => ['required_with:lines', 'numeric', 'min:0'],
            'lines.*.unit_price' => ['required_with:lines', 'numeric', 'min:0'],
            'lines.*.discount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.cost_center_id' => ['nullable', 'uuid', 'exists:procurement_cost_centers,id'],
            'lines.*.expense_type' => ['nullable', 'string', 'in:capex,opex'],
            'lines.*.budget_line_id' => ['nullable', 'uuid', 'exists:procurement_budget_lines,id'],
        ]);

        $updated = $service->updateDraft($model, $data, $actor);
        $fresh = $registry->find((string) $updated->id);

        return $this->ok($registry->toDetailPayload($fresh ?? $updated));
    }
}
