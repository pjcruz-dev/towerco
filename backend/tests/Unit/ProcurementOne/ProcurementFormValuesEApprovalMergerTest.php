<?php

declare(strict_types=1);

namespace Tests\Unit\ProcurementOne;

use App\Modules\ProcurementOne\Services\ProcurementFormValuesEApprovalMerger;
use Tests\TestCase;

final class ProcurementFormValuesEApprovalMergerTest extends TestCase
{
    public function test_merges_compose_only_fields_onto_pr_backed_values(): void
    {
        $merger = new ProcurementFormValuesEApprovalMerger;

        $merged = $merger->mergePurchaseRequisition(
            [
                'requisition_title' => 'From PR model',
                'department' => 'operations',
                'estimated_total' => '4800',
            ],
            [
                'requisition_title' => 'Stale compose title',
                'procurement_approver' => '019ea634-3d4a-715f-8a7f-faccd3b3ea21',
                'finance_approver' => '',
                'quotes' => '',
            ],
        );

        $this->assertSame('From PR model', $merged['requisition_title']);
        $this->assertSame('operations', $merged['department']);
        $this->assertSame('4800', $merged['estimated_total']);
        $this->assertSame('019ea634-3d4a-715f-8a7f-faccd3b3ea21', $merged['procurement_approver']);
        $this->assertArrayNotHasKey('finance_approver', $merged);
        $this->assertArrayNotHasKey('quotes', $merged);
    }

    public function test_merges_compose_only_fields_onto_po_backed_values(): void
    {
        $merger = new ProcurementFormValuesEApprovalMerger;

        $merged = $merger->mergePurchaseOrder(
            [
                'supplier' => 'Acme Supplies',
                'grand_total' => '168000',
            ],
            [
                'supplier' => 'Stale compose supplier',
                'procurement_approver' => '019ea634-3d4a-715f-8a7f-faccd3b3ea21',
            ],
        );

        $this->assertSame('Acme Supplies', $merged['supplier']);
        $this->assertSame('168000', $merged['grand_total']);
        $this->assertSame('019ea634-3d4a-715f-8a7f-faccd3b3ea21', $merged['procurement_approver']);
    }
}
