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
            'request_for_payment',
            'purchase_requisition',
            'purchase_order',
            'vendor_registration',
        ];
    }

    public function test_request_for_payment_template_has_single_page_compose(): void
    {
        $templates = config('e_approval.form_templates', []);
        $template = $templates['request_for_payment'] ?? [];
        $fields = collect($template['fields'] ?? [])->pluck('name')->all();

        $this->assertContains('payee', $fields);
        $this->assertContains('department', $fields);
        $this->assertContains('payment_amount', $fields);
        $this->assertContains('service_invoice', $fields);
        $this->assertContains('cost_application', $fields);
        $this->assertSame('single_page', $template['metadata_json']['compose']['mode'] ?? null);
        $this->assertFalse((bool) ($template['metadata_json']['compose']['include_review_step'] ?? true));
        $this->assertSame('Payee', collect($template['fields'] ?? [])->firstWhere('name', 'section_payee')['label'] ?? null);
        $this->assertSame('Bank details', collect($template['fields'] ?? [])->firstWhere('name', 'section_bank')['label'] ?? null);
        $this->assertSame('Cost application', collect($template['fields'] ?? [])->firstWhere('name', 'section_cost')['label'] ?? null);

        $amountLayout = collect($template['fields'] ?? [])->firstWhere('name', 'payment_amount')['options']['layout']['row_id'] ?? null;
        $currencyLayout = collect($template['fields'] ?? [])->firstWhere('name', 'currency')['options']['layout']['row_id'] ?? null;
        $this->assertSame('rfp_amount', $amountLayout);
        $this->assertSame('rfp_amount', $currencyLayout);

        $costRows = collect($template['fields'] ?? [])->firstWhere('name', 'cost_application')['options']['rows'] ?? [];
        $costLabels = collect($costRows)->pluck('label')->all();
        $this->assertContains('CME-Delivery & Handling', $costLabels);
        $this->assertContains('Others, pls specify', $costLabels);
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
        $this->assertContains('finance_approver', $fields);
        $this->assertContains('senior_approver', $fields);
        $this->assertContains('final_approver', $fields);
        $this->assertCount(4, $templates['cash_advance']['steps'] ?? []);
        $this->assertSame('manager', $templates['cash_advance']['steps'][0]['type'] ?? null);
        $this->assertSame('lte', $templates['cash_advance']['steps'][1]['when'][0]['operator'] ?? null);
        $this->assertSame('5000', $templates['cash_advance']['steps'][1]['when'][0]['value'] ?? null);
        $this->assertSame('requested_amount', $templates['cash_advance']['steps'][1]['when'][0]['field'] ?? null);
        $this->assertSame('gt', $templates['cash_advance']['steps'][2]['when'][0]['operator'] ?? null);
        $this->assertSame('final_approver', $templates['cash_advance']['steps'][3]['approverId'] ?? null);
        $this->assertTrue((bool) ($templates['cash_advance']['metadata_json']['compose']['include_review_step'] ?? false));
    }

    public function test_liquidation_and_reimbursement_use_total_reimbursement_field(): void
    {
        $templates = config('e_approval.form_templates', []);

        foreach (['liquidation', 'reimbursement'] as $templateId) {
            $fields = collect($templates[$templateId]['fields'] ?? [])->pluck('name')->all();
            $this->assertContains('total_reimbursement', $fields, $templateId);
            $this->assertContains('senior_approver', $fields, $templateId);
            $this->assertContains('final_approver', $fields, $templateId);
            $this->assertSame('total_reimbursement', $templates[$templateId]['steps'][1]['when'][0]['field'] ?? null, $templateId);
            $this->assertSame('final_approver', $templates[$templateId]['steps'][3]['approverId'] ?? null, $templateId);
        }

        $liquidationDoc = collect($templates['liquidation']['fields'] ?? [])->firstWhere('name', 'cash_advance_document_no');
        $this->assertTrue((bool) ($liquidationDoc['options']['read_only'] ?? false));
        $this->assertTrue((bool) ($templates['liquidation']['metadata_json']['requires_parent_submission'] ?? false));

        $liquidationTotalOrder = collect($templates['liquidation']['fields'] ?? [])->firstWhere('name', 'total_reimbursement')['step_order'] ?? 0;
        $liquidationGridOrder = collect($templates['liquidation']['fields'] ?? [])->firstWhere('name', 'expense_lines')['step_order'] ?? 0;
        $this->assertGreaterThan($liquidationGridOrder, $liquidationTotalOrder);

        $lqColumns = collect($templates['liquidation']['fields'] ?? [])->firstWhere('name', 'expense_lines')['options']['columns'] ?? [];
        $reColumns = collect($templates['reimbursement']['fields'] ?? [])->firstWhere('name', 'expense_lines')['options']['columns'] ?? [];
        $lqLabels = collect($lqColumns)->pluck('label')->all();
        $reLabels = collect($reColumns)->pluck('label')->all();

        $this->assertContains('Transportation - Land', $lqLabels);
        $this->assertContains('Lodging', $lqLabels);
        $this->assertNotContains('Toll Fee', $lqLabels);

        $this->assertContains('Landfare', $reLabels);
        $this->assertContains('Toll Fee', $reLabels);
        $this->assertNotContains('Lodging', $reLabels);
        $this->assertNotContains('Transportation - Sea', $reLabels);

        $reFields = collect($templates['reimbursement']['fields'] ?? [])->pluck('name')->all();
        $this->assertContains('travel_period', $reFields);
        $this->assertContains('place', $reFields);
        $this->assertNotContains('expense_period_end', $reFields);
        $this->assertNotContains('cash_advance_document_no', $reFields);

        $lqFields = collect($templates['liquidation']['fields'] ?? [])->pluck('name')->all();
        $this->assertContains('cash_advance_amount', $lqFields);
        $this->assertContains('cash_overage_shortage', $lqFields);
        $this->assertNotContains('employee_name', $lqFields);
        $this->assertSame('landscape', $templates['liquidation']['metadata_json']['print_default_orientation'] ?? null);
        $this->assertSame('landscape', $templates['reimbursement']['metadata_json']['print_default_orientation'] ?? null);
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
