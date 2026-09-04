<?php

declare(strict_types=1);

/**
 * Built-in E-Approval form templates — finance & procurement.
 *
 * Field names align with open-parent APIs when parent_submission_id is set:
 * - CA: `requested_amount` / child `total_reimbursement` (EApprovalCashAdvanceService)
 * - PR: `estimated_total` / child `total_amount` (EApprovalPurchaseRequisitionService)
 *
 * Cash advance, liquidation, and reimbursement share an amount ladder:
 * Direct manager (always) → Finance ≤ 5,000 / senior > 5,000 → final approver (always).
 */
$amountThreshold = '5000';

$steppedCompose = [
    'mode' => 'stepped',
    'step_source' => 'sections',
    'show_progress' => true,
    'validate_on_next' => true,
    'allow_back' => true,
    'include_review_step' => true,
];

$amountWorkflow = static function (string $amountField) use ($amountThreshold): array {
    return [
        ['type' => 'manager', 'step_order' => 1],
        [
            'type' => 'field',
            'approverId' => 'finance_approver',
            'step_order' => 2,
            'when' => [['field' => $amountField, 'operator' => 'lte', 'value' => $amountThreshold]],
        ],
        [
            'type' => 'field',
            'approverId' => 'senior_approver',
            'step_order' => 3,
            'when' => [['field' => $amountField, 'operator' => 'gt', 'value' => $amountThreshold]],
        ],
        ['type' => 'field', 'approverId' => 'final_approver', 'step_order' => 4],
    ];
};

$approverFields = static function (int $startOrder) use ($amountThreshold): array {
    return [
        [
            'type' => 'section',
            'name' => 'section_approvers',
            'label' => 'Approvers',
            'step_order' => $startOrder,
        ],
        [
            'type' => 'approver',
            'name' => 'finance_approver',
            'label' => 'Finance approver',
            'step_order' => $startOrder + 1,
            'validation' => [
                'required' => true,
                'help_text' => 'Used when the amount is '.$amountThreshold.' or less.',
            ],
        ],
        [
            'type' => 'approver',
            'name' => 'senior_approver',
            'label' => 'Senior / admin approver',
            'step_order' => $startOrder + 2,
            'validation' => [
                'required' => true,
                'help_text' => 'Used when the amount is over '.$amountThreshold.'.',
            ],
        ],
        [
            'type' => 'approver',
            'name' => 'final_approver',
            'label' => 'Final approver',
            'step_order' => $startOrder + 3,
            'validation' => [
                'required' => true,
                'help_text' => 'Always runs after the amount path (for example a controller or director).',
            ],
        ],
    ];
};

/**
 * Subsidiary selector — drives {{system.subsidiary_logo}} on print.
 * Choices sync from Print tab subsidiary codes (defaults ATC / ADIC).
 *
 * @param  array{width?: string, row_id?: string, slot?: int}|null  $layout
 * @return array<string, mixed>
 */
$subsidiaryField = static function (int $stepOrder, ?array $layout = null): array {
    $options = [
        'choices' => [
            ['value' => 'ATC', 'label' => 'ATC'],
            ['value' => 'ADIC', 'label' => 'ADIC'],
        ],
    ];
    if ($layout !== null) {
        $options['layout'] = $layout;
    }

    return [
        'type' => 'select',
        'name' => 'subsidiary',
        'label' => 'Subsidiary',
        'step_order' => $stepOrder,
        'validation' => [
            'required' => true,
            'help_text' => 'Chooses the letterhead logo for this subsidiary when printing.',
        ],
        'options' => $options,
    ];
};

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
        'description' => 'Cash advance chain, request for payment, purchase requisition → PO, and vendor registration.',
        'template_ids' => [
            'cash_advance',
            'liquidation',
            'reimbursement',
            'request_for_payment',
            'purchase_requisition',
            'purchase_order',
            'vendor_registration',
        ],
    ],

    'cash_advance' => [
        'name' => 'Cash advance',
        'description' => 'Request petty cash or travel advance. Direct manager, then Finance (≤ 5,000) or senior admin (> 5,000), then a final approver. Field requested_amount drives open-balance tracking.',
        'category' => 'finance',
        'doc_type_code' => 'CA',
        'metadata_json' => [
            'form_family' => 'cash_advance',
            'related_template_ids' => ['liquidation', 'reimbursement'],
            'compose' => $steppedCompose,
        ],
        'fields' => [
            [
                'type' => 'section',
                'name' => 'section_request',
                'label' => 'Cash advance request',
                'step_order' => 1,
            ],
            $subsidiaryField(2, ['width' => 'half', 'row_id' => 'ca_org', 'slot' => 0]),
            [
                'type' => 'date',
                'name' => 'needed_by',
                'label' => 'Funds needed by',
                'step_order' => 3,
                'validation' => ['required' => true],
                'options' => ['layout' => ['width' => 'half', 'row_id' => 'ca_org', 'slot' => 1]],
            ],
            [
                'type' => 'select',
                'name' => 'department',
                'label' => 'Department',
                'step_order' => 4,
                'validation' => ['required' => true],
                'options' => [
                    'choices' => [
                        ['value' => 'operations', 'label' => 'Operations'],
                        ['value' => 'finance', 'label' => 'Finance'],
                        ['value' => 'engineering', 'label' => 'Engineering'],
                        ['value' => 'hr', 'label' => 'Human resources'],
                    ],
                    'layout' => ['width' => 'half', 'row_id' => 'ca_dates', 'slot' => 0],
                ],
            ],
            [
                'type' => 'currency',
                'name' => 'requested_amount',
                'label' => 'Requested amount',
                'step_order' => 5,
                'validation' => ['required' => true],
                'options' => ['layout' => ['width' => 'half', 'row_id' => 'ca_amount', 'slot' => 0]],
            ],
            [
                'type' => 'select',
                'name' => 'currency',
                'label' => 'Currency',
                'step_order' => 6,
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
                'step_order' => 7,
                'validation' => ['required' => true, 'placeholder' => 'Describe why the advance is needed'],
            ],
            [
                'type' => 'text',
                'name' => 'location',
                'label' => 'Location / site',
                'step_order' => 8,
                'options' => ['layout' => ['width' => 'half', 'row_id' => 'ca_place', 'slot' => 0]],
            ],
            [
                'type' => 'date_range',
                'name' => 'activity_dates',
                'label' => 'Activity / travel period',
                'step_order' => 9,
                'options' => ['layout' => ['width' => 'half', 'row_id' => 'ca_place', 'slot' => 1]],
            ],
            [
                'type' => 'file',
                'name' => 'supporting_documents',
                'label' => 'Supporting documents',
                'step_order' => 10,
            ],
            ...$approverFields(11),
        ],
        'steps' => $amountWorkflow('requested_amount'),
    ],

    'liquidation' => [
        'name' => 'Liquidation',
        'description' => 'Liquidate an approved cash advance with expense lines and receipts. Same approval ladder as cash advance, using the liquidation total.',
        'category' => 'finance',
        'doc_type_code' => 'LQ',
        'metadata_json' => [
            'form_family' => 'liquidation',
            'parent_form_family' => 'cash_advance',
            'requires_parent_submission' => true,
            'related_template_ids' => ['cash_advance'],
            'compose' => $steppedCompose,
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
                'validation' => [
                    'required' => true,
                    'help_text' => 'Filled automatically when you select an approved cash advance.',
                    'placeholder' => 'Select an approved cash advance',
                ],
                'options' => [
                    'read_only' => true,
                ],
            ],
            $subsidiaryField(3, ['width' => 'half', 'row_id' => 'lq_meta', 'slot' => 0]),
            [
                'type' => 'date',
                'name' => 'liquidation_date',
                'label' => 'Liquidation date',
                'step_order' => 4,
                'validation' => ['required' => true],
                'options' => ['layout' => ['width' => 'half', 'row_id' => 'lq_meta', 'slot' => 1]],
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
                        ['label' => 'Category', 'type' => 'text'],
                        ['label' => 'Description', 'type' => 'text'],
                        ['label' => 'Amount', 'type' => 'currency'],
                    ],
                ],
            ],
            [
                'type' => 'currency',
                'name' => 'total_reimbursement',
                'label' => 'Total liquidation amount',
                'step_order' => 6,
                'validation' => ['required' => true, 'help_text' => 'Auto-calculated from expense lines.'],
                'options' => [
                    'read_only' => true,
                    'computed_from' => [
                        'operation' => 'sum_grid_column',
                        'source_field' => 'expense_lines',
                        'column' => 'Amount',
                    ],
                ],
            ],
            [
                'type' => 'file',
                'name' => 'receipts',
                'label' => 'Receipts',
                'step_order' => 7,
                'validation' => ['required' => true],
            ],
            [
                'type' => 'textarea',
                'name' => 'notes',
                'label' => 'Notes',
                'step_order' => 8,
            ],
            ...$approverFields(9),
        ],
        'steps' => $amountWorkflow('total_reimbursement'),
    ],

    'reimbursement' => [
        'name' => 'Reimbursement',
        'description' => 'Reimburse out-of-pocket expenses already paid by the requestor. Same approval ladder as cash advance, using the reimbursement total. No cash-advance parent.',
        'category' => 'finance',
        'doc_type_code' => 'RE',
        'metadata_json' => [
            'form_family' => 'reimbursement',
            'compose' => $steppedCompose,
        ],
        'fields' => [
            [
                'type' => 'section',
                'name' => 'section_request',
                'label' => 'Reimbursement request',
                'step_order' => 1,
            ],
            $subsidiaryField(2, ['width' => 'half', 'row_id' => 're_org', 'slot' => 0]),
            [
                'type' => 'date',
                'name' => 'expense_period_end',
                'label' => 'Expense period end',
                'step_order' => 3,
                'validation' => ['required' => true],
                'options' => ['layout' => ['width' => 'half', 'row_id' => 're_org', 'slot' => 1]],
            ],
            [
                'type' => 'select',
                'name' => 'department',
                'label' => 'Department',
                'step_order' => 4,
                'validation' => ['required' => true],
                'options' => [
                    'choices' => [
                        ['value' => 'operations', 'label' => 'Operations'],
                        ['value' => 'finance', 'label' => 'Finance'],
                        ['value' => 'engineering', 'label' => 'Engineering'],
                    ],
                    'layout' => ['width' => 'half', 'row_id' => 're_dates', 'slot' => 0],
                ],
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
                        ['label' => 'Category', 'type' => 'text'],
                        ['label' => 'Description', 'type' => 'text'],
                        ['label' => 'Amount', 'type' => 'currency'],
                    ],
                ],
            ],
            [
                'type' => 'currency',
                'name' => 'total_reimbursement',
                'label' => 'Total reimbursement amount',
                'step_order' => 6,
                'validation' => ['required' => true, 'help_text' => 'Auto-calculated from expense lines.'],
                'options' => [
                    'read_only' => true,
                    'computed_from' => [
                        'operation' => 'sum_grid_column',
                        'source_field' => 'expense_lines',
                        'column' => 'Amount',
                    ],
                ],
            ],
            [
                'type' => 'textarea',
                'name' => 'purpose',
                'label' => 'Purpose / summary',
                'step_order' => 7,
                'validation' => ['required' => true],
            ],
            [
                'type' => 'file',
                'name' => 'receipts',
                'label' => 'Receipts',
                'step_order' => 8,
                'validation' => ['required' => true],
            ],
            ...$approverFields(9),
        ],
        'steps' => $amountWorkflow('total_reimbursement'),
    ],

    'request_for_payment' => [
        'name' => 'Request for payment',
        'description' => 'Vendor or non-PO payment against a service invoice — payee, amount, bank, and cost charge.',
        'category' => 'finance',
        'doc_type_code' => 'RFP',
        'metadata_json' => [
            'form_family' => 'request_for_payment',
            'compose' => [
                'mode' => 'stepped',
                'step_source' => 'sections',
                'show_progress' => true,
                'validate_on_next' => true,
                'allow_back' => true,
                'include_review_step' => true,
            ],
            'revision' => [
                'routing' => 'resume_returning_step',
                'material_fields' => ['payment_amount', 'payee'],
                'approver_can_force_full_restart' => false,
            ],
        ],
        'fields' => [
            [
                'type' => 'section',
                'name' => 'section_payee',
                'label' => 'Payee & payment details',
                'step_order' => 1,
            ],
            $subsidiaryField(2),
            [
                'type' => 'text',
                'name' => 'payee',
                'label' => 'Payee',
                'step_order' => 3,
                'validation' => ['required' => true],
            ],
            [
                'type' => 'text',
                'name' => 'vat_registration_no',
                'label' => 'TIN / VAT registration no.',
                'step_order' => 4,
                'validation' => ['required' => true],
                'options' => ['layout' => ['width' => 'half', 'row_id' => 'rfp_tax', 'slot' => 0]],
            ],
            [
                'type' => 'currency',
                'name' => 'payment_amount',
                'label' => 'Payment amount',
                'step_order' => 5,
                'validation' => ['required' => true],
                'options' => ['layout' => ['width' => 'half', 'row_id' => 'rfp_tax', 'slot' => 1]],
            ],
            [
                'type' => 'text',
                'name' => 'contact_person',
                'label' => 'Contact person',
                'step_order' => 6,
                'validation' => ['required' => true],
                'options' => ['layout' => ['width' => 'half', 'row_id' => 'rfp_contact', 'slot' => 0]],
            ],
            [
                'type' => 'phone',
                'name' => 'tel_no',
                'label' => 'Tel no.',
                'step_order' => 7,
                'validation' => ['required' => true],
                'options' => ['layout' => ['width' => 'half', 'row_id' => 'rfp_contact', 'slot' => 1]],
            ],
            [
                'type' => 'select',
                'name' => 'currency',
                'label' => 'Currency',
                'step_order' => 8,
                'validation' => ['required' => true],
                'options' => [
                    'choices' => [
                        ['value' => 'PHP', 'label' => 'PHP'],
                        ['value' => 'USD', 'label' => 'USD'],
                    ],
                    'layout' => ['width' => 'half', 'row_id' => 'rfp_currency', 'slot' => 0],
                ],
            ],
            [
                'type' => 'text',
                'name' => 'non_po',
                'label' => 'PO no. / Non-PO reference',
                'step_order' => 9,
                'validation' => [
                    'required' => true,
                    'help_text' => 'Enter the PO number, or Non-PO with a short reason.',
                ],
                'options' => ['layout' => ['width' => 'half', 'row_id' => 'rfp_currency', 'slot' => 1]],
            ],
            [
                'type' => 'textarea',
                'name' => 'payment_purpose',
                'label' => 'Payment purpose',
                'step_order' => 10,
                'validation' => ['required' => true, 'placeholder' => 'Describe what this payment covers'],
            ],
            [
                'type' => 'text',
                'name' => 'passenger',
                'label' => 'Passenger (if travel)',
                'step_order' => 11,
                'options' => ['layout' => ['width' => 'half', 'row_id' => 'rfp_travel', 'slot' => 0]],
            ],
            [
                'type' => 'text',
                'name' => 'location',
                'label' => 'Location / site',
                'step_order' => 12,
                'options' => ['layout' => ['width' => 'half', 'row_id' => 'rfp_travel', 'slot' => 1]],
            ],
            [
                'type' => 'date_range',
                'name' => 'service_period',
                'label' => 'Service / travel period',
                'step_order' => 13,
                'validation' => ['required' => true],
                'options' => ['layout' => ['width' => 'half', 'row_id' => 'rfp_dates', 'slot' => 0]],
            ],
            [
                'type' => 'instruction',
                'name' => 'payment_terms_note',
                'label' => 'Payment terms',
                'step_order' => 14,
                'options' => [
                    'body' => 'Note: 100% full payment — upon submission of Service Invoice.',
                ],
            ],
            [
                'type' => 'file',
                'name' => 'service_invoice',
                'label' => 'Service invoice',
                'step_order' => 15,
                'validation' => [
                    'required' => true,
                    'help_text' => 'Attach the service invoice required for payment release.',
                    'maxFiles' => 5,
                    'allowedFileTypes' => ['pdf', 'image'],
                ],
            ],
            [
                'type' => 'file',
                'name' => 'supporting_documents',
                'label' => 'Supporting documents',
                'step_order' => 16,
                'validation' => [
                    'required' => false,
                    'maxFiles' => 10,
                    'allowedFileTypes' => ['pdf', 'image'],
                ],
            ],
            [
                'type' => 'section',
                'name' => 'section_bank_cost',
                'label' => 'Bank & cost charge',
                'step_order' => 17,
            ],
            [
                'type' => 'text',
                'name' => 'bank_name',
                'label' => 'Name of bank',
                'step_order' => 18,
                'validation' => ['required' => true],
            ],
            [
                'type' => 'text',
                'name' => 'bank_account_name',
                'label' => 'Bank account name',
                'step_order' => 19,
                'validation' => ['required' => true],
                'options' => ['layout' => ['width' => 'half', 'row_id' => 'rfp_bank', 'slot' => 0]],
            ],
            [
                'type' => 'text',
                'name' => 'bank_account_no',
                'label' => 'Bank account no.',
                'step_order' => 20,
                'validation' => ['required' => true],
                'options' => ['layout' => ['width' => 'half', 'row_id' => 'rfp_bank', 'slot' => 1]],
            ],
            [
                'type' => 'checklist_matrix',
                'name' => 'cost_application',
                'label' => 'Cost application',
                'step_order' => 21,
                'validation' => ['required' => true],
                'options' => [
                    'row_select_label' => 'Cost Application',
                    'rows' => [
                        ['value' => 'saq_site_survey', 'label' => 'SAQ-Site Survey'],
                        ['value' => 'saq_permitting', 'label' => 'SAQ-Permitting'],
                        ['value' => 'saq_soil_testing', 'label' => 'SAQ-Soil Testing'],
                        ['value' => 'cme_materials', 'label' => 'CME-Materials'],
                        ['value' => 'cme_labor', 'label' => 'CME-Labor'],
                        ['value' => 'logistics', 'label' => 'Logistics'],
                        ['value' => 'various_department', 'label' => 'Various Department'],
                        ['value' => 'finance_and_accounting', 'label' => 'Finance and Accounting'],
                        ['value' => 'others', 'label' => 'Others'],
                    ],
                    'columns' => [
                        ['value' => 'project_site_no', 'label' => 'Project Site No', 'type' => 'text'],
                        ['value' => 'ref_no', 'label' => 'Ref No', 'type' => 'text'],
                        ['value' => 'or_no', 'label' => 'OR No.', 'type' => 'text'],
                    ],
                ],
            ],
            [
                'type' => 'approver',
                'name' => 'finance_approver',
                'label' => 'Finance approver',
                'step_order' => 21,
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
            'print_template_kind' => 'purchase_requisition',
            'related_template_ids' => ['purchase_order'],
            'use_approval_policy' => true,
            'workflow_source' => 'form',
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
                        ['label' => 'Site ID', 'type' => 'select', 'master_data_key' => 'sites'],
                        ['label' => 'Description', 'type' => 'text'],
                        ['label' => 'Item Code', 'type' => 'select', 'master_data_key' => 'item_codes'],
                        [
                            'label' => 'Department',
                            'type' => 'select',
                            'choices' => [
                                ['value' => 'operations', 'label' => 'Operations'],
                                ['value' => 'it', 'label' => 'IT'],
                                ['value' => 'network', 'label' => 'Network'],
                                ['value' => 'facilities', 'label' => 'Facilities'],
                            ],
                        ],
                        ['label' => 'UOM', 'type' => 'text'],
                        [
                            'label' => 'Quote basis',
                            'type' => 'select',
                            'choices' => [
                                ['value' => 'one_time', 'label' => 'One-time'],
                                ['value' => 'monthly', 'label' => 'Monthly'],
                                ['value' => 'yearly', 'label' => 'Yearly'],
                                ['value' => 'monthly_yearly', 'label' => 'Monthly + Yearly'],
                            ],
                        ],
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
                'validation' => ['required' => true, 'help_text' => 'Auto-calculated from line items (Qty × unit price).'],
                'options' => [
                    'read_only' => true,
                    'computed_from' => [
                        'operation' => 'sum_grid_lines',
                        'source_field' => 'line_items',
                        'quantity_column' => 'Qty',
                        'amount_column' => 'Unit price',
                    ],
                ],
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
        ],
        'steps' => [
            ['type' => 'manager', 'step_order' => 1],
        ],
    ],

    'purchase_order' => [
        'name' => 'Purchase order (PO)',
        'description' => 'Issue a purchase order against an approved requisition and selected vendor.',
        'category' => 'procurement',
        'doc_type_code' => 'PO',
        'metadata_json' => [
            'form_family' => 'purchase_order',
            'print_template_kind' => 'purchase_order',
            'parent_form_family' => 'purchase_requisition',
            'requires_parent_submission' => true,
            'related_template_ids' => ['purchase_requisition', 'vendor_registration'],
            'use_approval_policy' => true,
            'workflow_source' => 'form',
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
                'type' => 'textarea',
                'name' => 'payment_terms',
                'label' => 'Payment terms',
                'step_order' => 6,
                'validation' => [
                    'placeholder' => 'e.g. Net 30 days from invoice date',
                    'help_text' => 'Free text — describe commercial terms for this PO.',
                ],
            ],
            [
                'type' => 'grid',
                'name' => 'line_items',
                'label' => 'PO line items',
                'step_order' => 7,
                'validation' => ['required' => true],
                'options' => [
                    'columns' => [
                        ['label' => 'Site ID', 'type' => 'select', 'master_data_key' => 'sites'],
                        ['label' => 'Description', 'type' => 'text'],
                        ['label' => 'Item Code', 'type' => 'select', 'master_data_key' => 'item_codes'],
                        [
                            'label' => 'Department',
                            'type' => 'select',
                            'choices' => [
                                ['value' => 'operations', 'label' => 'Operations'],
                                ['value' => 'it', 'label' => 'IT'],
                                ['value' => 'network', 'label' => 'Network'],
                                ['value' => 'facilities', 'label' => 'Facilities'],
                            ],
                        ],
                        ['label' => 'Qty', 'type' => 'number'],
                        ['label' => 'Unit price', 'type' => 'currency'],
                    ],
                ],
            ],
            [
                'type' => 'currency',
                'name' => 'total_amount',
                'label' => 'PO total amount',
                'step_order' => 8,
                'validation' => ['required' => true, 'help_text' => 'Auto-calculated from line items (Qty × unit price).'],
                'options' => [
                    'read_only' => true,
                    'computed_from' => [
                        'operation' => 'sum_grid_lines',
                        'source_field' => 'line_items',
                        'quantity_column' => 'Qty',
                        'amount_column' => 'Unit price',
                    ],
                ],
            ],
            [
                'type' => 'file',
                'name' => 'vendor_quote',
                'label' => 'Vendor quote / order confirmation',
                'step_order' => 9,
            ],
        ],
        'steps' => [
            ['type' => 'manager', 'step_order' => 1],
        ],
    ],

    'ap_invoice' => [
        'name' => 'AP invoice (supplier invoice)',
        'description' => 'Match supplier invoice to purchase order and goods receipt for accounts payable.',
        'category' => 'procurement',
        'doc_type_code' => 'APINV',
        'metadata_json' => [
            'form_family' => 'ap_invoice',
            'print_template_kind' => 'ap_invoice',
            'parent_form_family' => 'purchase_order',
            'requires_parent_submission' => true,
            'related_template_ids' => ['purchase_order', 'purchase_requisition'],
            'use_approval_policy' => true,
        ],
        'fields' => [
            [
                'type' => 'section',
                'name' => 'section_invoice',
                'label' => 'Supplier invoice',
                'step_order' => 1,
            ],
            [
                'type' => 'text',
                'name' => 'purchase_order_document_no',
                'label' => 'PO document no.',
                'step_order' => 2,
                'validation' => ['required' => true],
            ],
            [
                'type' => 'text',
                'name' => 'vendor_invoice_no',
                'label' => 'Vendor invoice no.',
                'step_order' => 3,
                'validation' => ['required' => true],
            ],
            [
                'type' => 'text',
                'name' => 'supplier',
                'label' => 'Supplier',
                'step_order' => 4,
                'validation' => ['required' => true],
            ],
            [
                'type' => 'date',
                'name' => 'invoice_date',
                'label' => 'Invoice date',
                'step_order' => 5,
                'validation' => ['required' => true],
            ],
            [
                'type' => 'date',
                'name' => 'due_date',
                'label' => 'Due date',
                'step_order' => 6,
            ],
            [
                'type' => 'grid',
                'name' => 'line_items',
                'label' => 'Invoice lines',
                'step_order' => 7,
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
                'name' => 'grand_total',
                'label' => 'Invoice total',
                'step_order' => 8,
                'validation' => ['required' => true],
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
            ['type' => 'manager', 'step_order' => 1],
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
