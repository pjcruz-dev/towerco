<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use App\Modules\ProcurementOne\Services\ProcurementOneSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProcurementOneSettingsUpdateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ProcurementOneSettingsService $settings,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('procurement_one:settings:manage'), 403);
        $planFeatures->assertModuleEnabled();

        $data = $request->validate([
            'module_message' => ['sometimes', 'string', 'max:2000'],
            'vendor_accreditation_policy' => ['sometimes', 'array'],
            'vendor_accreditation_policy.enabled' => ['sometimes', 'boolean'],
            'vendor_accreditation_policy.mode' => ['sometimes', 'string', 'in:warn,block'],
            'pr_budget_policy' => ['sometimes', 'array'],
            'pr_budget_policy.enabled' => ['sometimes', 'boolean'],
            'pr_budget_policy.mode' => ['sometimes', 'string', 'in:warn,block'],
            'vendor_email_templates' => ['sometimes', 'array'],
            'vendor_email_templates.auto_on_approve' => ['sometimes', 'boolean'],
            'vendor_email_templates.auto_on_sent' => ['sometimes', 'boolean'],
            'gr_receipt_policy' => ['sometimes', 'array'],
            'gr_receipt_policy.tolerance_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'gr_receipt_policy.mode' => ['sometimes', 'string', 'in:warn,block'],
            'inventory_policy' => ['sometimes', 'array'],
            'inventory_policy.inventory_mode' => ['sometimes', 'string', 'in:none,simple'],
            'inventory_policy.default_receipt_location_id' => ['nullable', 'uuid'],
            'inventory_policy.auto_create_assets_on_deploy' => ['sometimes', 'boolean'],
            'ap_invoice_match_policy' => ['sometimes', 'array'],
            'ap_invoice_match_policy.match_mode' => ['sometimes', 'string', 'in:two_way,three_way'],
            'ap_invoice_match_policy.tolerance_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'ap_invoice_match_policy.mode' => ['sometimes', 'string', 'in:warn,block'],
            'ap_invoice_match_policy.require_grn_posted' => ['sometimes', 'boolean'],
            'contract_spend_policy' => ['sometimes', 'array'],
            'contract_spend_policy.enabled' => ['sometimes', 'boolean'],
            'contract_spend_policy.mode' => ['sometimes', 'string', 'in:warn,block'],
            'export_column_maps' => ['sometimes', 'array'],
            'export_schedule' => ['sometimes', 'array'],
            'export_schedule.enabled' => ['sometimes', 'boolean'],
            'export_schedule.frequency' => ['sometimes', 'string', 'in:monthly'],
            'export_schedule.day_of_month' => ['sometimes', 'integer', 'min:1', 'max:28'],
            'export_schedule.hour' => ['sometimes', 'integer', 'min:0', 'max:23'],
            'export_schedule.recipients' => ['sometimes', 'array'],
            'export_schedule.recipients.*' => ['email'],
            'export_schedule.period' => ['sometimes', 'string', 'in:previous_month,current_month'],
            'procurement_policy' => ['sometimes', 'array'],
            'procurement_policy.budget' => ['sometimes', 'array'],
            'procurement_policy.budget.enabled' => ['sometimes', 'boolean'],
            'procurement_policy.budget.mode' => ['sometimes', 'string', 'in:warn,block'],
            'document_types' => ['sometimes', 'array'],
            'document_types.*.id' => ['required_with:document_types', 'string', 'max:64'],
            'document_types.*.label' => ['required_with:document_types', 'string', 'max:120'],
            'document_types.*.code' => ['required_with:document_types', 'string', 'max:16'],
            'status_catalogs' => ['sometimes', 'array'],
            'numbering_series' => ['sometimes', 'array'],
        ]);

        $settings->update($data);

        return $this->ok($settings->snapshot());
    }
}
