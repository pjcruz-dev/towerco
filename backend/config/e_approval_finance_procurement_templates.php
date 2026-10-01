<?php

declare(strict_types=1);

/**
 * Built-in E-Forms templates — cash advance, liquidation, and reimbursement.
 *
 * Field names align with open-parent APIs when parent_submission_id is set:
 * - CA: `requested_amount` / child `total_reimbursement` (EApprovalCashAdvanceService)
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

/** ATC Expense lines of Liquidation (CA settlement). */
$liquidationExpenseLinesColumns = [
    ['label' => 'Date', 'type' => 'date'],
    ['label' => 'OR No', 'type' => 'text'],
    ['label' => 'Supplier/Payee', 'type' => 'text'],
    ['label' => 'Description', 'type' => 'textarea'],
    ['label' => 'Project Site No.', 'type' => 'text'],
    ['label' => 'Transportation - Land', 'type' => 'currency'],
    ['label' => 'Transportation - Sea', 'type' => 'currency'],
    ['label' => 'Transportation - Air', 'type' => 'currency'],
    ['label' => 'Gasoline', 'type' => 'currency'],
    ['label' => 'Lodging', 'type' => 'currency'],
    ['label' => 'Per Diem', 'type' => 'currency'],
    ['label' => 'VAT', 'type' => 'currency'],
    ['label' => 'Total', 'type' => 'currency'],
];

/** ATC Expense lines of Reimbursement (out-of-pocket travel). */
$reimbursementExpenseLinesColumns = [
    ['label' => 'Date', 'type' => 'date'],
    ['label' => 'OR No', 'type' => 'text'],
    ['label' => 'Supplier/Payee', 'type' => 'text'],
    ['label' => 'Description', 'type' => 'textarea'],
    ['label' => 'Project Site No.', 'type' => 'text'],
    ['label' => 'Landfare', 'type' => 'currency'],
    ['label' => 'Airfare', 'type' => 'currency'],
    ['label' => 'Gasoline', 'type' => 'currency'],
    ['label' => 'Toll Fee', 'type' => 'currency'],
    ['label' => 'Per Diem', 'type' => 'currency'],
    ['label' => 'VAT', 'type' => 'currency'],
    ['label' => 'Total', 'type' => 'currency'],
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
    'cash_advance' => [
        'name' => 'Cash advance',
        'description' => 'Request petty cash or travel advance. Direct manager, then Finance (≤ 5,000) or senior admin (> 5,000), then a final approver. Field requested_amount drives open-balance tracking.',
        'category' => 'finance',
        'doc_type_code' => 'CA',
        'metadata_json' => [
            'form_family' => 'cash_advance',
            'related_template_ids' => ['liquidation', 'reimbursement'],
            'compose' => $steppedCompose,
            'print_dynamic_form_body' => true,
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
            // Print uses live field/grid definitions via {{system.form_body}} — column edits apply automatically.
            'print_dynamic_form_body' => true,
            'print_default_orientation' => 'landscape',
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
                'type' => 'text',
                'name' => 'area',
                'label' => 'Area',
                'step_order' => 5,
                'options' => ['layout' => ['width' => 'half', 'row_id' => 'lq_area', 'slot' => 0]],
            ],
            [
                'type' => 'grid',
                'name' => 'expense_lines',
                'label' => 'Expense lines of Liquidation',
                'step_order' => 6,
                'validation' => ['required' => true],
                'options' => [
                    'columns' => $liquidationExpenseLinesColumns,
                ],
            ],
            [
                'type' => 'currency',
                'name' => 'total_reimbursement',
                'label' => 'Total liquidation amount',
                'step_order' => 7,
                'validation' => [
                    'required' => true,
                    'help_text' => 'System total from expense lines (used for approval thresholds; shown as TOTAL EXPENSES on the grid).',
                ],
                'options' => [
                    'read_only' => true,
                    'computed_from' => [
                        'operation' => 'sum_grid_column',
                        'source_field' => 'expense_lines',
                        'column' => 'Total',
                    ],
                ],
            ],
            [
                'type' => 'currency',
                'name' => 'cash_advance_amount',
                'label' => 'Cash advance',
                'step_order' => 8,
                'validation' => [
                    'help_text' => 'Filled from the linked cash advance when available.',
                ],
                'options' => [
                    'read_only' => true,
                    'layout' => ['width' => 'half', 'row_id' => 'lq_balance', 'slot' => 0],
                ],
            ],
            [
                'type' => 'currency',
                'name' => 'cash_overage_shortage',
                'label' => 'Cash overage (shortage)',
                'step_order' => 9,
                'validation' => [
                    'help_text' => 'Cash advance − total expenses (positive = overage, negative = shortage).',
                ],
                'options' => [
                    'read_only' => true,
                    'computed_from' => [
                        'operation' => 'subtract_fields',
                        'left_field' => 'cash_advance_amount',
                        'right_field' => 'total_reimbursement',
                    ],
                    'layout' => ['width' => 'half', 'row_id' => 'lq_balance', 'slot' => 1],
                ],
            ],
            [
                'type' => 'file',
                'name' => 'receipts',
                'label' => 'Receipts',
                'step_order' => 10,
                'validation' => ['required' => true],
            ],
            [
                'type' => 'textarea',
                'name' => 'notes',
                'label' => 'Notes',
                'step_order' => 11,
            ],
            ...$approverFields(12),
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
            'print_dynamic_form_body' => true,
            'print_default_orientation' => 'landscape',
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
                    'layout' => ['width' => 'half', 'row_id' => 're_org', 'slot' => 1],
                ],
            ],
            [
                'type' => 'date_range',
                'name' => 'travel_period',
                'label' => 'From / To',
                'step_order' => 4,
                'validation' => ['required' => true],
                'options' => ['layout' => ['width' => 'half', 'row_id' => 're_travel', 'slot' => 0]],
            ],
            [
                'type' => 'text',
                'name' => 'place',
                'label' => 'Place',
                'step_order' => 5,
                'validation' => ['required' => true],
                'options' => ['layout' => ['width' => 'half', 'row_id' => 're_travel', 'slot' => 1]],
            ],
            [
                'type' => 'grid',
                'name' => 'expense_lines',
                'label' => 'Expense lines of Reimbursement',
                'step_order' => 6,
                'validation' => ['required' => true],
                'options' => [
                    'columns' => $reimbursementExpenseLinesColumns,
                ],
            ],
            [
                'type' => 'currency',
                'name' => 'total_reimbursement',
                'label' => 'Total reimbursement amount',
                'step_order' => 7,
                'validation' => [
                    'required' => true,
                    'help_text' => 'System total from expense lines (used for approval thresholds; shown as TOTAL EXPENSES on the grid).',
                ],
                'options' => [
                    'read_only' => true,
                    'computed_from' => [
                        'operation' => 'sum_grid_column',
                        'source_field' => 'expense_lines',
                        'column' => 'Total',
                    ],
                ],
            ],
            [
                'type' => 'textarea',
                'name' => 'purpose',
                'label' => 'Purpose / summary',
                'step_order' => 8,
                'validation' => ['required' => true],
            ],
            [
                'type' => 'file',
                'name' => 'receipts',
                'label' => 'Receipts',
                'step_order' => 9,
                'validation' => ['required' => true],
            ],
            ...$approverFields(10),
        ],
        'steps' => $amountWorkflow('total_reimbursement'),
    ],
];
