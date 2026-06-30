<?php

declare(strict_types=1);

namespace Tests\Unit\EApproval;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalFormField;
use App\Modules\EApproval\Services\EApprovalFieldComputedService;
use Tests\TestCase;

final class EApprovalFieldComputedServiceTest extends TestCase
{
    public function test_reimbursement_total_sums_expense_line_amounts(): void
    {
        $form = new EApprovalForm(['id' => 'form-1']);
        $form->setRelation('fields', collect([
            $this->gridField('expense_lines', [
                ['label' => 'Date', 'type' => 'date'],
                ['label' => 'Description', 'type' => 'text'],
                ['label' => 'Amount', 'type' => 'currency'],
            ]),
            $this->currencyField('total_reimbursement'),
        ]));

        $service = new EApprovalFieldComputedService;
        $values = $service->apply($form, [
            'expense_lines' => json_encode([
                'rows' => [
                    ['0' => '2026-06-01', '1' => 'Taxi', '2' => '1200'],
                    ['0' => '2026-06-02', '1' => 'Meal', '2' => '2400'],
                ],
            ], JSON_THROW_ON_ERROR),
            'total_reimbursement' => '0',
        ]);

        $this->assertSame('3600.00', $values['total_reimbursement']);
    }

    public function test_purchase_requisition_total_sums_qty_times_unit_price(): void
    {
        $form = new EApprovalForm(['id' => 'form-2']);
        $form->setRelation('fields', collect([
            $this->gridField('line_items', [
                ['label' => 'Description', 'type' => 'text'],
                ['label' => 'Qty', 'type' => 'number'],
                ['label' => 'Unit price', 'type' => 'currency'],
            ]),
            $this->currencyField('estimated_total'),
        ]));

        $service = new EApprovalFieldComputedService;
        $values = $service->apply($form, [
            'line_items' => json_encode([
                'rows' => [
                    ['0' => 'Cable', '1' => '2', '2' => '1500'],
                    ['0' => 'Bracket', '1' => '3', '2' => '250'],
                ],
            ], JSON_THROW_ON_ERROR),
            'estimated_total' => '1',
        ]);

        $this->assertSame('3750.00', $values['estimated_total']);
    }

    public function test_purchase_order_vat_chain_computes_grand_total(): void
    {
        $form = new EApprovalForm(['id' => 'form-po']);
        $form->setRelation('fields', collect([
            $this->gridField('line_items', [
                ['label' => 'Item', 'type' => 'text'],
                ['label' => 'Description', 'type' => 'text'],
                ['label' => 'UOM', 'type' => 'text'],
                ['label' => 'Qty', 'type' => 'number'],
                ['label' => 'Unit price', 'type' => 'currency'],
                ['label' => 'Discount', 'type' => 'currency'],
                ['label' => 'Amount', 'type' => 'currency'],
            ]),
            $this->computedCurrencyField('vatable_amount', [
                'operation' => 'sum_grid_column',
                'source_field' => 'line_items',
                'column' => 'Amount',
            ]),
            new EApprovalFormField([
                'name' => 'vat_exempt_amount',
                'label' => 'VAT-exempt sales',
                'type' => 'currency',
                'options' => null,
            ]),
            new EApprovalFormField([
                'name' => 'zero_rated_amount',
                'label' => 'Zero-rated sales',
                'type' => 'currency',
                'options' => null,
            ]),
            new EApprovalFormField([
                'name' => 'vat_rate',
                'label' => 'VAT rate (%)',
                'type' => 'number',
                'options' => null,
            ]),
            $this->computedCurrencyField('vat_amount', [
                'operation' => 'percent_of',
                'source_field' => 'vatable_amount',
                'rate_field' => 'vat_rate',
            ]),
            $this->computedCurrencyField('total_vat_inclusive', [
                'operation' => 'add_fields',
                'fields' => ['vatable_amount', 'vat_exempt_amount', 'zero_rated_amount', 'vat_amount'],
            ]),
            new EApprovalFormField([
                'name' => 'less_discount',
                'label' => 'Less: Discount',
                'type' => 'currency',
                'options' => null,
            ]),
            $this->computedCurrencyField('grand_total', [
                'operation' => 'subtract_fields',
                'left_field' => 'total_vat_inclusive',
                'right_field' => 'less_discount',
            ]),
        ]));

        $service = new EApprovalFieldComputedService;
        $values = $service->apply($form, [
            'line_items' => json_encode([
                'rows' => [
                    ['0' => 'A', '1' => 'Widget', '2' => 'EA', '3' => '10', '4' => '100', '5' => '50', '6' => '0'],
                    ['0' => 'B', '1' => 'Cable', '2' => 'M', '3' => '5', '4' => '200', '5' => '0', '6' => '0'],
                ],
            ], JSON_THROW_ON_ERROR),
            'vat_exempt_amount' => '100.00',
            'zero_rated_amount' => '0.00',
            'vat_rate' => '12',
            'less_discount' => '200.00',
        ]);

        $this->assertSame('1950.00', $values['vatable_amount']);
        $this->assertSame('234.00', $values['vat_amount']);
        $this->assertSame('2284.00', $values['total_vat_inclusive']);
        $this->assertSame('2084.00', $values['grand_total']);
    }

    /**
     * @param  list<array{label: string, type: string}>  $columns
     */
    private function gridField(string $name, array $columns): EApprovalFormField
    {
        return new EApprovalFormField([
            'name' => $name,
            'label' => $name,
            'type' => 'grid',
            'options' => ['columns' => $columns],
        ]);
    }

    private function currencyField(string $name): EApprovalFormField
    {
        return new EApprovalFormField([
            'name' => $name,
            'label' => $name,
            'type' => 'currency',
            'options' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $computedFrom
     */
    private function computedCurrencyField(string $name, array $computedFrom): EApprovalFormField
    {
        return new EApprovalFormField([
            'name' => $name,
            'label' => $name,
            'type' => 'currency',
            'options' => ['computed_from' => $computedFrom],
        ]);
    }
}
