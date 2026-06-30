<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Services\ProcurementFormComposeService;
use App\Modules\ProcurementOne\Services\ProcurementPoRegistryService;
use App\Modules\ProcurementOne\Services\ProcurementPoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementPoUpdateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $po,
        ProcurementFormComposeService $compose,
        ProcurementPoService $service,
        ProcurementPoRegistryService $registry,
    ): JsonResponse {
        abort_unless($request->user()?->can('procurement_one:documents:create'), 403);

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $model = $registry->find($po);
        abort_if($model === null, 404);

        if ($request->has('values')) {
            $data = $request->validate([
                'values' => ['required', 'array'],
            ]);

            $updated = $compose->updatePurchaseOrder($model, $data['values'], $actor, requireRequired: false);
            $fresh = $registry->find((string) $updated->id);

            return $this->ok($registry->toDetailPayload($fresh ?? $updated));
        }

        $data = $request->validate([
            'status' => ['sometimes', 'string', 'max:32'],
            'vendor_code' => ['sometimes', 'nullable', 'string', 'max:64'],
            'vendor_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'supplier' => ['sometimes', 'nullable', 'string', 'max:500'],
            'ship_to' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'delivery_date' => ['sometimes', 'nullable', 'date'],
            'payment_terms' => ['sometimes', 'nullable', 'string', 'max:120'],
            'currency_code' => ['sometimes', 'nullable', 'string', 'max:8'],
            'exchange_rate' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'delivery_location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'vat_exempt_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'zero_rated_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'vat_rate' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'less_discount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'lines' => ['sometimes', 'array', 'min:1'],
            'lines.*.item' => ['nullable', 'string', 'max:120'],
            'lines.*.description' => ['required_with:lines', 'string', 'max:500'],
            'lines.*.uom' => ['nullable', 'string', 'max:32'],
            'lines.*.quantity' => ['required_with:lines', 'numeric', 'min:0'],
            'lines.*.unit_price' => ['required_with:lines', 'numeric', 'min:0'],
            'lines.*.discount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.pr_id' => ['nullable', 'uuid', 'exists:procurement_prs,id'],
            'lines.*.pr_line_id' => ['nullable', 'uuid'],
            'lines.*.cost_center_id' => ['nullable', 'uuid', 'exists:procurement_cost_centers,id'],
            'lines.*.expense_type' => ['nullable', 'string', 'in:capex,opex'],
            'lines.*.budget_line_id' => ['nullable', 'uuid', 'exists:procurement_budget_lines,id'],
            'allocations' => ['sometimes', 'array'],
            'allocations.*.pr_id' => ['required_with:allocations', 'uuid', 'exists:procurement_prs,id'],
            'allocations.*.amount' => ['required_with:allocations', 'numeric', 'min:0'],
            'contract_id' => ['nullable', 'uuid', 'exists:procurement_contracts,id'],
        ]);

        $updated = $service->update($model, $data, $actor);
        $fresh = $registry->find((string) $updated->id);

        return $this->ok($registry->toDetailPayload($fresh ?? $updated));
    }
}
