<?php

declare(strict_types=1);

namespace Tests\Unit\EApproval;

use Tests\TestCase;

final class EApprovalFinanceProcurementTemplatesTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function financeTemplateIds(): array
    {
        return [
            'cash_advance',
            'liquidation',
            'reimbursement',
            'purchase_requisition',
            'purchase_order',
            'vendor_registration',
        ];
    }

    public function test_finance_procurement_templates_are_registered(): void
    {
        $templates = config('e_approval.form_templates', []);

        foreach ($this->financeTemplateIds() as $templateId) {
            $this->assertArrayHasKey($templateId, $templates, "Missing template: {$templateId}");
            $this->assertNotEmpty($templates[$templateId]['name'] ?? '');
            $this->assertNotEmpty($templates[$templateId]['fields'] ?? []);
            $this->assertNotEmpty($templates[$templateId]['steps'] ?? []);
        }
    }

    public function test_cash_advance_template_has_open_balance_field_names(): void
    {
        $templates = config('e_approval.form_templates', []);
        $fields = collect($templates['cash_advance']['fields'] ?? [])->pluck('name')->all();

        $this->assertContains('requested_amount', $fields);
    }

    public function test_liquidation_and_reimbursement_use_total_reimbursement_field(): void
    {
        $templates = config('e_approval.form_templates', []);

        foreach (['liquidation', 'reimbursement'] as $templateId) {
            $fields = collect($templates[$templateId]['fields'] ?? [])->pluck('name')->all();
            $this->assertContains('total_reimbursement', $fields, $templateId);
        }
    }

    public function test_purchase_order_vendor_field_uses_master_data_key(): void
    {
        $templates = config('e_approval.form_templates', []);
        $vendor = collect($templates['purchase_order']['fields'] ?? [])
            ->firstWhere('name', 'vendor');

        $this->assertIsArray($vendor);
        $this->assertSame('vendors', $vendor['options']['master_data_key'] ?? null);
    }
}
