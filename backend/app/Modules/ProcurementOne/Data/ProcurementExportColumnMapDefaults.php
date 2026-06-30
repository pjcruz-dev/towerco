<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Data;

use App\Modules\ProcurementOne\Support\ProcurementExportEntity;

final class ProcurementExportColumnMapDefaults
{
    /**
     * @return array<string, list<array{key: string, label: string, enabled: bool}>>
     */
    public static function all(): array
    {
        return [
            ProcurementExportEntity::VENDORS => [
                ['key' => 'vendor_code', 'label' => 'Vendor code', 'enabled' => true],
                ['key' => 'company_name', 'label' => 'Company name', 'enabled' => true],
                ['key' => 'tax_id', 'label' => 'Tax ID', 'enabled' => true],
                ['key' => 'category', 'label' => 'Category', 'enabled' => true],
                ['key' => 'accreditation_status', 'label' => 'Accreditation', 'enabled' => true],
                ['key' => 'accreditation_expires_at', 'label' => 'Accreditation expires', 'enabled' => true],
                ['key' => 'is_active', 'label' => 'Active', 'enabled' => true],
                ['key' => 'contact_email', 'label' => 'Contact email', 'enabled' => true],
                ['key' => 'contact_phone', 'label' => 'Contact phone', 'enabled' => false],
                ['key' => 'updated_at', 'label' => 'Updated at', 'enabled' => true],
            ],
            ProcurementExportEntity::PRS => [
                ['key' => 'document_no', 'label' => 'PR no', 'enabled' => true],
                ['key' => 'status', 'label' => 'Status', 'enabled' => true],
                ['key' => 'title', 'label' => 'Title', 'enabled' => true],
                ['key' => 'department', 'label' => 'Department', 'enabled' => true],
                ['key' => 'requestor_name', 'label' => 'Requestor', 'enabled' => true],
                ['key' => 'estimated_total', 'label' => 'Estimated total', 'enabled' => true],
                ['key' => 'currency', 'label' => 'Currency', 'enabled' => true],
                ['key' => 'committed_po_amount', 'label' => 'Committed PO', 'enabled' => true],
                ['key' => 'submitted_at', 'label' => 'Submitted at', 'enabled' => true],
                ['key' => 'approved_at', 'label' => 'Approved at', 'enabled' => true],
                ['key' => 'created_at', 'label' => 'Created at', 'enabled' => false],
            ],
            ProcurementExportEntity::PR_LINES => [
                ['key' => 'pr_document_no', 'label' => 'PR no', 'enabled' => true],
                ['key' => 'line_order', 'label' => 'Line', 'enabled' => true],
                ['key' => 'description', 'label' => 'Description', 'enabled' => true],
                ['key' => 'quantity', 'label' => 'Quantity', 'enabled' => true],
                ['key' => 'unit_price', 'label' => 'Unit price', 'enabled' => true],
                ['key' => 'amount', 'label' => 'Amount', 'enabled' => true],
                ['key' => 'expense_type', 'label' => 'Expense type', 'enabled' => true],
                ['key' => 'cost_center_id', 'label' => 'Cost center', 'enabled' => false],
            ],
            ProcurementExportEntity::POS => [
                ['key' => 'document_no', 'label' => 'PO no', 'enabled' => true],
                ['key' => 'status', 'label' => 'Status', 'enabled' => true],
                ['key' => 'vendor_code', 'label' => 'Vendor code', 'enabled' => true],
                ['key' => 'vendor_name', 'label' => 'Vendor name', 'enabled' => true],
                ['key' => 'supplier', 'label' => 'Supplier', 'enabled' => true],
                ['key' => 'requestor_name', 'label' => 'Requestor', 'enabled' => true],
                ['key' => 'grand_total', 'label' => 'Grand total', 'enabled' => true],
                ['key' => 'currency_code', 'label' => 'Currency', 'enabled' => true],
                ['key' => 'contract_document_no', 'label' => 'Contract no', 'enabled' => true],
                ['key' => 'submitted_at', 'label' => 'Submitted at', 'enabled' => true],
                ['key' => 'approved_at', 'label' => 'Approved at', 'enabled' => true],
                ['key' => 'created_at', 'label' => 'Created at', 'enabled' => false],
            ],
            ProcurementExportEntity::PO_LINES => [
                ['key' => 'po_document_no', 'label' => 'PO no', 'enabled' => true],
                ['key' => 'line_order', 'label' => 'Line', 'enabled' => true],
                ['key' => 'item', 'label' => 'Item', 'enabled' => true],
                ['key' => 'description', 'label' => 'Description', 'enabled' => true],
                ['key' => 'uom', 'label' => 'UOM', 'enabled' => true],
                ['key' => 'quantity', 'label' => 'Quantity', 'enabled' => true],
                ['key' => 'unit_price', 'label' => 'Unit price', 'enabled' => true],
                ['key' => 'discount', 'label' => 'Discount', 'enabled' => true],
                ['key' => 'amount', 'label' => 'Amount', 'enabled' => true],
                ['key' => 'pr_document_no', 'label' => 'PR no', 'enabled' => true],
            ],
        ];
    }
}
