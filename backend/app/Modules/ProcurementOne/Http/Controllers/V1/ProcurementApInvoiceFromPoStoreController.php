<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Services\ProcurementApInvoiceRegistryService;
use App\Modules\ProcurementOne\Services\ProcurementApInvoiceService;
use App\Modules\ProcurementOne\Services\ProcurementFormComposeService;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use App\Modules\ProcurementOne\Services\ProcurementPoRegistryService;
use App\Modules\ProcurementOne\Support\ProcurementApMatchMode;
use Illuminate\Http\JsonResponse;
use App\Modules\ProcurementOne\Support\FinanceOneAccess;
use Illuminate\Http\Request;

final class ProcurementApInvoiceFromPoStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $po,
        ProcurementFormComposeService $compose,
        ProcurementApInvoiceService $service,
        ProcurementApInvoiceRegistryService $registry,
        ProcurementPoRegistryService $poRegistry,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        FinanceOneAccess::authorizeDocumentsCreate($request->user());
        $planFeatures->assertApInvoicesEnabled();

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $model = $poRegistry->find($po);
        abort_if($model === null, 404);

        if ($request->has('values')) {
            $data = $request->validate([
                'values' => ['required', 'array'],
            ]);

            $result = $compose->createApInvoiceFromPurchaseOrder($model, $data['values'], $actor, requireRequired: false);
            $fresh = $registry->find((string) $result['invoice']->id);

            return $this->created([
                'invoice' => $registry->toDetailPayload($fresh ?? $result['invoice']),
                'warning' => $result['warning'],
            ]);
        }

        $data = $request->validate([
            'grn_id' => ['nullable', 'uuid'],
            'vendor_invoice_no' => ['nullable', 'string', 'max:120'],
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'payment_terms' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'match_mode' => ['sometimes', 'string', 'in:'.implode(',', ProcurementApMatchMode::all())],
            'vat_exempt_amount' => ['nullable', 'numeric', 'min:0'],
            'zero_rated_amount' => ['nullable', 'numeric', 'min:0'],
            'vat_rate' => ['nullable', 'numeric', 'min:0'],
            'less_discount' => ['nullable', 'numeric', 'min:0'],
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

        $result = $service->createFromPurchaseOrder($model, $data, $actor);
        $fresh = $registry->find((string) $result['invoice']->id);

        return $this->created([
            'invoice' => $registry->toDetailPayload($fresh ?? $result['invoice']),
            'warning' => $result['warning'],
        ]);
    }
}
