<?php

declare(strict_types=1);

/**
 * Built-in E-Approval form templates — finance & procurement.
 *
 * Field names align with open-parent APIs when parent_submission_id is set:
 * - CA: `requested_amount` / child `total_reimbursement` (EApprovalCashAdvanceService)
 * - PR: `estimated_total` / child `total_amount` (EApprovalPurchaseRequisitionService)
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Finance & procurement gallery bundle (D2)
    |--------------------------------------------------------------------------
    |
    | POST /e-approval/form-templates/finance-procurement-bundle creates these
    | templates and wires related_form_ids from related_template_ids metadata.
    */
    '_bundle' => [
        'id' => 'finance_procurement',
        'name' => 'Finance & procurement pack',
        'description' => 'Cash advance chain, purchase requisition → PO, and vendor registration.',
        'template_ids' => [
            'cash_advance',
            'liquidation',
            'reimbursement',
            'purchase_requisition',
            'purchase_order',
            'vendor_registration',
        ],
    ],

    'cash_advance' => [
        'name' => 'Cash advance',
        'description' => 'Request petty cash or travel advance. Use field requested_amount for open-balance tracking.',
        'category' => 'finance',
        'doc_type_code' => 'CA',
        'metadata_json' => [
            'form_family' => 'cash_advance',
            'related_template_ids' => ['liquidation', 'reimbursement'],
        ],
        'fields' => [
            [
                'type' => 'section',
                'name' => 'section_request',
                'label' => 'Cash advance request',
                'step_order' => 1,
            ],
            [
                'type' => 'date',
                'name' => 'needed_by',
                'label' => 'Funds needed by',
                'step_order' => 2,
                'validation' => ['required' => true],
                'options' => ['layout' => ['width' => 'half', 'row_id' => 'ca_dates', 'slot' => 0]],
            ],
            [
                'type' => 'select',
                'name' => 'department',
                'label' => 'Department',
                'step_order' => 3,
                'validation' => ['required' => true],
                'options' => [
                    'choices' => [
                        ['value' => 'operations', 'label' => 'Operations'],
                        ['value' => 'finance', 'label' => 'Finance'],
                        ['value' => 'engineering', 'label' => 'Engineering'],
                        ['value' => 'hr', 'label' => 'Human resources'],
                    ],
                    'layout' => ['width' => 'half', 'row_id' => 'ca_dates', 'slot' => 1],
                ],
            ],
            [
                'type' => 'currency',
                'name' => 'requested_amount',
                'label' => 'Requested amount',
                'step_order' => 4,
                'validation' => ['required' => true],
                'options' => ['layout' => ['width' => 'half', 'row_id' => 'ca_amount', 'slot' => 0]],
            ],
            [
                'type' => 'select',
                'name' => 'currency',
                'label' => 'Currency',
                'step_order' => 5,
                'validation' => ['required' => true],
                'options' => [
                    'choices' => [
                        ['value' => 'PHP', 'label' => 'PHP'],
                        ['value' => 'USD', 'label' => 'USD'],
                    ],
                    'layout' => ['width' => 'half', 'row_id' => 'ca_amount', 'slot' => 1],
                ],
            ],
            [
                'type' => 'textarea',
                'name' => 'purpose',
                'label' => 'Purpose / activity',
                'step_order' => 6,
                'validation' => ['required' => true, 'placeholder' => 'Describe why the advance is needed'],
            ],
            [
                'type' => 'file',
                'name' => 'supporting_documents',
                'label' => 'Supporting documents',
                'step_order' => 7,
            ],
            [
                'type' => 'approver',
                'name' => 'finance_approver',
                'label' => 'Finance approver',
                'step_order' => 8,
                'validation' => ['required' => true],
            ],
        ],
        'steps' => [
            ['type' => 'manager', 'step_order' => 1],
            ['type' => 'field', 'approverId' => 'finance_approver', 'step_order' => 2],
        ],
    ],

    'liquidation' => [
        'name' => 'Liquidation',
        'description' => 'Liquidate an approved cash advance with expense lines and receipts.',
        'category' => 'finance',
        'doc_type_code' => 'LQ',
        'metadata_json' => [
            'form_family' => 'liquidation',
            'parent_form_family' => 'cash_advance',
            'requires_parent_submission' => true,
            'related_template_ids' => ['cash_advance'],
        ],
        'fields' => [
            [
                'type' => 'section',
                'name' => 'section_reference',
                'label' => 'Cash advance reference',
                'step_order' => 1,
            ],
            [
                'type' => 'text',
                'name' => 'cash_advance_document_no',
                'label' => 'Cash advance document no.',
                'step_order' => 2,
                'validation' => ['required' => true, 'placeholder' => 'e.g. CA-2026-00012'],
            ],
            [
                'type' => 'currency',
                'name' => 'total_reimbursement',
                'label' => 'Total liquidation amount',
                'step_order' => 3,
                'validation' => ['required' => true],
                'options' => ['layout' => ['width' => 'half', 'row_id' => 'lq_total', 'slot' => 0]],
            ],
            [
                'type' => 'date',
                'name' => 'liquidation_date',
                'label' => 'Liquidation date',
                'step_order' => 4,
                'validation' => ['required' => true],
                'options' => ['layout' => ['width' => 'half', 'row_id' => 'lq_total', 'slot' => 1]],
            ],
            [
                'type' => 'grid',
                'name' => 'expense_lines',
                'label' => 'Expense lines',
                'step_order' => 5,
                'validation' => ['required' => true],
                'options' => [
                    'columns' => [
                        ['label' => 'Date', 'type' => 'date'],
                        ['label' => 'Description', 'type' => 'text'],
                        ['label' => 'Amount', 'type' => 'currency'],
                    ],
                ],
            ],
            [
                'type' => 'file',
                'name' => 'receipts',
                'label' => 'Receipts',
                'step_order' => 6,
                'validation' => ['required' => true],
            ],
            [
                'type' => 'textarea',
                'name' => 'notes',
                'label' => 'Notes',
                'step_order' => 7,
            ],
            [
                'type' => 'approver',
                'name' => 'finance_approver',
                'label' => 'Finance approver',
                'step_order' => 8,
                'validation' => ['required' => true],
            ],
        ],
        'steps' => [
            ['type' => 'manager', 'step_order' => 1],
            ['type' => 'field', 'approverId' => 'finance_approver', 'step_order' => 2],
        ],
    ],

    'reimbursement' => [
        'name' => 'Reimbursement',
        'description' => 'Reimburse out-of-pocket expenses already paid by the requestor.',
        'category' => 'finance',
        'doc_type_code' => 'RE',
        'metadata_json' => [
            'form_family' => 'reimbursement',
        ],
        'fields' => [
            [
                'type' => 'section',
                'name' => 'section_request',
                'label' => 'Reimbursement request',
                'step_order' => 1,
            ],
            [
                'type' => 'date',
                'name' => 'expense_period_end',
                'label' => 'Expense period end',
                'step_order' => 2,
                'validation' => ['required' => true],
                'options' => ['layout' => ['width' => 'half', 'row_id' => 're_dates', 'slot' => 0]],
            ],
            [
                'type' => 'select',
                'name' => 'department',
                'label' => 'Department',
                'step_order' => 3,
                'validation' => ['required' => true],
                'options' => [
                    'choices' => [
                        ['value' => 'operations', 'label' => 'Operations'],
                        ['value' => 'finance', 'label' => 'Finance'],
                        ['value' => 'engineering', 'label' => 'Engineering'],
                    ],
                    'layout' => ['width' => 'half', 'row_id' => 're_dates', 'slot' => 1],
                ],
            ],
            [
                'type' => 'currency',
                'name' => 'total_reimbursement',
                'label' => 'Total reimbursement amount',
                'step_order' => 4,
                'validation' => ['required' => true],
            ],
            [
                'type' => 'grid',
                'name' => 'expense_lines',
                'label' => 'Expense lines',
                'step_order' => 5,
                'validation' => ['required' => true],
                'options' => [
                    'columns' => [
                        ['label' => 'Date', 'type' => 'date'],
                        ['label' => 'Description', 'type' => 'text'],
                        ['label' => 'Amount', 'type' => 'currency'],
                    ],
                ],
            ],
            [
                'type' => 'textarea',
                'name' => 'purpose',
                'label' => 'Purpose / summary',
                'step_order' => 6,
                'validation' => ['required' => true],
            ],
            [
                'type' => 'file',
                'name' => 'receipts',
                'label' => 'Receipts',
                'step_order' => 7,
                'validation' => ['required' => true],
            ],
            [
                'type' => 'approver',
                'name' => 'finance_approver',
                'label' => 'Finance approver',
                'step_order' => 8,
                'validation' => ['required' => true],
            ],
        ],
        'steps' => [
            ['type' => 'manager', 'step_order' => 1],
            ['type' => 'field', 'approverId' => 'finance_approver', 'step_order' => 2],
        ],
    ],

    'purchase_requisition' => [
        'name' => 'Purchase requisition (PR)',
        'description' => 'Request approval to purchase goods or services before issuing a PO.',
        'category' => 'procurement',
        'doc_type_code' => 'PR',
        'metadata_json' => [
            'form_family' => 'purchase_requisition',
            'related_template_ids' => ['purchase_order'],
        ],
        'fields' => [
            [
                'type' => 'section',
                'name' => 'section_requisition',
                'label' => 'Requisition details',
                'step_order' => 1,
            ],
            [
                'type' => 'text',
                'name' => 'requisition_title',
                'label' => 'Title / summary',
                'step_order' => 2,
                'validation' => ['required' => true],
            ],
            [
                'type' => 'select',
                'name' => 'department',
                'label' => 'Department',
                'step_order' => 3,
                'validation' => ['required' => true],
                'options' => [
                    'choices' => [
                        ['value' => 'operations', 'label' => 'Operations'],
                        ['value' => 'it', 'label' => 'IT'],
                        ['value' => 'network', 'label' => 'Network'],
                        ['value' => 'facilities', 'label' => 'Facilities'],
                    ],
                    'layout' => ['width' => 'half', 'row_id' => 'pr_meta', 'slot' => 0],
                ],
            ],
            [
                'type' => 'select',
                'name' => 'urgency',
                'label' => 'Urgency',
                'step_order' => 4,
                'validation' => ['required' => true],
                'options' => [
                    'choices' => [
                        ['value' => 'normal', 'label' => 'Normal'],
                        ['value' => 'urgent', 'label' => 'Urgent'],
                    ],
                    'layout' => ['width' => 'half', 'row_id' => 'pr_meta', 'slot' => 1],
                ],
            ],
            [
                'type' => 'grid',
                'name' => 'line_items',
                'label' => 'Line items',
                'step_order' => 5,
                'validation' => ['required' => true],
                'options' => [
                    'columns' => [
                        ['label' => 'Description', 'type' => 'text'],
                        ['label' => 'Qty', 'type' => 'number'],
                        ['label' => 'Unit price', 'type' => 'currency'],
                    ],
                ],
            ],
            [
                'type' => 'currency',
                'name' => 'estimated_total',
                'label' => 'Estimated total',
                'step_order' => 6,
                'validation' => ['required' => true],
            ],
            [
                'type' => 'textarea',
                'name' => 'justification',
                'label' => 'Business justification',
                'step_order' => 7,
                'validation' => ['required' => true],
            ],
            [
                'type' => 'file',
                'name' => 'quotes',
                'label' => 'Quotes / specifications',
                'step_order' => 8,
            ],
            [
                'type' => 'approver',
                'name' => 'procurement_approver',
                'label' => 'Procurement approver',
                'step_order' => 9,
                'validation' => ['required' => true],
            ],
            [
                'type' => 'approver',
                'name' => 'finance_approver',
                'label' => 'Finance approver',
                'step_order' => 10,
                'validation' => ['required' => true],
            ],
        ],
        'steps' => [
            ['type' => 'manager', 'step_order' => 1],
            ['type' => 'field', 'approverId' => 'procurement_approver', 'step_order' => 2],
            ['type' => 'field', 'approverId' => 'finance_approver', 'step_order' => 3],
        ],
    ],

    'purchase_order' => [
        'name' => 'Purchase order (PO)',
        'description' => 'Issue a purchase order against an approved requisition and selected vendor.',
        'category' => 'procurement',
        'doc_type_code' => 'PO',
        'metadata_json' => [
            'form_family' => 'purchase_order',
            'parent_form_family' => 'purchase_requisition',
            'requires_parent_submission' => true,
            'related_template_ids' => ['purchase_requisition', 'vendor_registration'],
        ],
        'fields' => [
            [
                'type' => 'section',
                'name' => 'section_order',
                'label' => 'Purchase order',
                'step_order' => 1,
            ],
            [
                'type' => 'text',
                'name' => 'purchase_requisition_document_no',
                'label' => 'PR document no.',
                'step_order' => 2,
                'validation' => ['required' => true, 'placeholder' => 'e.g. PR-2026-00008'],
            ],
            [
                'type' => 'select',
                'name' => 'vendor',
                'label' => 'Vendor',
                'step_order' => 3,
                'validation' => ['required' => true],
                'options' => [
                    'master_data_key' => 'vendors',
                    'choices' => [
                        ['value' => 'vendor_pending', 'label' => 'Vendor pending registration'],
                    ],
                ],
            ],
            [
                'type' => 'date',
                'name' => 'required_delivery_date',
                'label' => 'Required delivery date',
                'step_order' => 4,
                'validation' => ['required' => true],
                'options' => ['layout' => ['width' => 'half', 'row_id' => 'po_dates', 'slot' => 0]],
            ],
            [
                'type' => 'text',
                'name' => 'delivery_location',
                'label' => 'Delivery location',
                'step_order' => 5,
                'validation' => ['required' => true],
                'options' => ['layout' => ['width' => 'half', 'row_id' => 'po_dates', 'slot' => 1]],
            ],
            [
                'type' => 'grid',
                'name' => 'line_items',
                'label' => 'PO line items',
                'step_order' => 6,
                'validation' => ['required' => true],
                'options' => [
                    'columns' => [
                        ['label' => 'Item', 'type' => 'text'],
                        ['label' => 'Qty', 'type' => 'number'],
                        ['label' => 'Unit price', 'type' => 'currency'],
                    ],
                ],
            ],
            [
                'type' => 'currency',
                'name' => 'total_amount',
                'label' => 'PO total amount',
                'step_order' => 7,
                'validation' => ['required' => true],
            ],
            [
                'type' => 'file',
                'name' => 'vendor_quote',
                'label' => 'Vendor quote / order confirmation',
                'step_order' => 8,
            ],
            [
                'type' => 'approver',
                'name' => 'finance_approver',
                'label' => 'Finance approver',
                'step_order' => 9,
                'validation' => ['required' => true],
            ],
        ],
        'steps' => [
            ['type' => 'field', 'approverId' => 'finance_approver', 'step_order' => 1],
        ],
    ],

    'vendor_registration' => [
        'name' => 'Vendor registration',
        'description' => 'Register or update a vendor for procurement. Suitable for internal intake or public link.',
        'category' => 'procurement',
        'doc_type_code' => 'VN',
        'metadata_json' => [
            'form_family' => 'vendor_registration',
            'public_link_suitable' => true,
            'master_data_set_key' => 'vendors',
            'master_data_schema_version' => 1,
            'related_template_ids' => ['purchase_order'],
        ],
        'fields' => [
            [
                'type' => 'section',
                'name' => 'section_company',
                'label' => 'Company information',
                'step_order' => 1,
            ],
            [
                'type' => 'text',
                'name' => 'company_name',
                'label' => 'Company / vendor name',
                'step_order' => 2,
                'validation' => ['required' => true],
            ],
            [
                'type' => 'text',
                'name' => 'tax_id',
                'label' => 'Tax ID / business registration no.',
                'step_order' => 3,
                'validation' => ['required' => true],
                'options' => ['layout' => ['width' => 'half', 'row_id' => 'vn_ids', 'slot' => 0]],
            ],
            [
                'type' => 'text',
                'name' => 'vendor_category',
                'label' => 'Vendor category',
                'step_order' => 4,
                'validation' => ['required' => true],
                'options' => [
                    'layout' => ['width' => 'half', 'row_id' => 'vn_ids', 'slot' => 1],
                    'placeholder' => 'e.g. Equipment, Services, Logistics',
                ],
            ],
            [
                'type' => 'text',
                'name' => 'contact_name',
                'label' => 'Primary contact name',
                'step_order' => 5,
                'validation' => ['required' => true],
                'options' => ['layout' => ['width' => 'half', 'row_id' => 'vn_contact', 'slot' => 0]],
            ],
            [
                'type' => 'email',
                'name' => 'contact_email',
                'label' => 'Contact email',
                'step_order' => 6,
                'validation' => ['required' => true],
                'options' => ['layout' => ['width' => 'half', 'row_id' => 'vn_contact', 'slot' => 1]],
            ],
            [
                'type' => 'phone',
                'name' => 'contact_phone',
                'label' => 'Contact phone',
                'step_order' => 7,
                'validation' => ['required' => true],
            ],
            [
                'type' => 'textarea',
                'name' => 'registered_address',
                'label' => 'Registered address',
                'step_order' => 8,
                'validation' => ['required' => true],
            ],
            [
                'type' => 'textarea',
                'name' => 'services_offered',
                'label' => 'Products / services offered',
                'step_order' => 9,
                'validation' => ['required' => true],
            ],
            [
                'type' => 'section',
                'name' => 'section_banking',
                'label' => 'Banking (for payments)',
                'step_order' => 10,
            ],
            [
                'type' => 'text',
                'name' => 'bank_name',
                'label' => 'Bank name',
                'step_order' => 11,
                'validation' => ['required' => true],
                'options' => ['layout' => ['width' => 'half', 'row_id' => 'vn_bank', 'slot' => 0]],
            ],
            [
                'type' => 'text',
                'name' => 'bank_account_no',
                'label' => 'Account number',
                'step_order' => 12,
                'validation' => ['required' => true],
                'options' => ['layout' => ['width' => 'half', 'row_id' => 'vn_bank', 'slot' => 1]],
            ],
            [
                'type' => 'file',
                'name' => 'compliance_documents',
                'label' => 'BIR / SEC / compliance documents',
                'step_order' => 13,
            ],
            [
                'type' => 'approver',
                'name' => 'procurement_approver',
                'label' => 'Procurement reviewer',
                'step_order' => 14,
                'validation' => ['required' => true],
            ],
        ],
        'steps' => [
            ['type' => 'field', 'approverId' => 'procurement_approver', 'step_order' => 1],
        ],
    ],
];
