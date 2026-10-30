<?php

declare(strict_types=1);

/**
 * Built-in E-Forms templates — cash advance, liquidation, and reimbursement.
 *
 * Field names align with open-parent APIs when parent_submission_id is set:
 * - CA: `requested_amount` / child `total_reimbursement` (EApprovalCashAdvanceService)
 *
 * Chain: Department Head (Entra manager) → Finance → CFO → money step.
 * People after Department Head are set on each form's workflow.
 */
$steppedCompose = [
    'mode' => 'stepped',
    'step_source' => 'sections',
    'show_progress' => true,
    'validate_on_next' => true,
    'allow_back' => true,
    'include_review_step' => true,
];

$vatTreatmentChoices = [
    ['value' => 'vatable', 'label' => 'VATable'],
    ['value' => 'non_vatable', 'label' => 'Non-VATable'],
];

$expenseTypeChoices = [
    ['value' => 'land', 'label' => 'Transportation - Land', 'data' => ['vatable' => true]],
    ['value' => 'sea', 'label' => 'Transportation - Sea', 'data' => ['vatable' => true]],
    ['value' => 'air', 'label' => 'Transportation - Air', 'data' => ['vatable' => true]],
    ['value' => 'gasoline', 'label' => 'Gasoline', 'data' => ['vatable' => true]],
    ['value' => 'lodging', 'label' => 'Lodging', 'data' => ['vatable' => true, 'hotel' => true]],
    ['value' => 'per_diem', 'label' => 'Per Diem', 'data' => ['vatable' => false, 'per_diem' => true]],
    ['value' => 'car_rental', 'label' => 'Car Rental', 'data' => ['vatable' => true]],
    ['value' => 'others', 'label' => 'Others', 'data' => ['vatable' => true]],
];

$tripColumns = [
    ['label' => 'From date – To date', 'type' => 'date_range'],
    ['label' => 'From site / location', 'type' => 'select', 'master_data_key' => 'sites', 'multiple' => true],
    ['label' => 'To site / location', 'type' => 'select', 'master_data_key' => 'sites', 'multiple' => true],
];

$forecastTripColumns = $tripColumns;
$forecastTripColumns[0]['date_min'] = 'field';
$forecastTripColumns[0]['date_min_field'] = 'needed_by';
$forecastTripColumns[1]['subsidiary_field'] = 'subsidiary';
$forecastTripColumns[2]['subsidiary_field'] = 'subsidiary';

$forecastExpenseColumns = [
    ...$forecastTripColumns,
    ['label' => 'Expense type', 'type' => 'select', 'choices' => $expenseTypeChoices],
    ['label' => 'Amount', 'type' => 'currency'],
    ['label' => 'Nature of Activity', 'type' => 'text', 'required' => true],
    ['label' => 'VAT treatment', 'type' => 'select', 'choices' => $vatTreatmentChoices],
    ['label' => 'VAT', 'type' => 'currency', 'locked' => true],
    ['label' => 'Non-VATable', 'type' => 'currency', 'locked' => true],
    ['label' => 'VATable, net of VAT', 'type' => 'currency', 'locked' => true],
];

$reimbursementExpenseColumns = [
    ...$tripColumns,
    ['label' => 'OR No', 'type' => 'text'],
    ['label' => 'Supplier/Payee', 'type' => 'text'],
    ['label' => 'Description', 'type' => 'textarea'],
    ['label' => 'Expense type', 'type' => 'select', 'choices' => $expenseTypeChoices],
    ['label' => 'Amount', 'type' => 'currency'],
    ['label' => 'VAT treatment', 'type' => 'select', 'choices' => $vatTreatmentChoices],
    ['label' => 'VAT', 'type' => 'currency', 'locked' => true],
];

$actualExpenseColumns = array_values(array_filter(
    $reimbursementExpenseColumns,
    static fn (array $column): bool => ! in_array($column['label'], ['Quantity', 'Rate'], true),
));
foreach ($actualExpenseColumns as &$actualColumn) {
    if ($actualColumn['label'] === 'Amount') {
        unset($actualColumn['locked']);
    }
}
unset($actualColumn);
$amountColumnAt = array_search('Amount', array_column($actualExpenseColumns, 'label'), true);
array_splice($actualExpenseColumns, (int) $amountColumnAt + 1, 0, [[
    'label' => 'Forecast amount',
    'type' => 'currency',
    'locked' => true,
]]);
array_splice($actualExpenseColumns, (int) $amountColumnAt + 2, 0, [[
    'label' => 'Nature of Activity',
    'type' => 'text',
    'locked' => true,
]]);
$supplierColumnAt = array_search('Supplier/Payee', array_column($actualExpenseColumns, 'label'), true);
if ($supplierColumnAt !== false) {
    array_splice($actualExpenseColumns, (int) $supplierColumnAt + 1, 0, [[
        'label' => 'TIN',
        'type' => 'text',
    ]]);
}
$actualExpenseColumns[] = ['label' => 'Variance reason', 'type' => 'textarea'];

$moneyFooters = static function (string $totalName, string $totalLabel, int $stepOrder, bool $withSettlement, string $sumColumn = 'Amount'): array {
    $fields = [
        [
            'type' => 'currency',
            'name' => $totalName,
            'label' => $totalLabel,
            'step_order' => $stepOrder,
            'validation' => [
                'required' => true,
                'help_text' => 'Total of the Amount column. VAT is calculated from the rate in E-Forms settings.',
            ],
            'options' => [
                'read_only' => true,
                'computed_from' => [
                    'operation' => 'sum_grid_column',
                    'source_field' => 'expense_lines',
                    'column' => $sumColumn,
                ],
                'layout' => ['width' => 'quarter', 'row_id' => 'money_footer', 'slot' => 0],
            ],
        ],
        [
            'type' => 'currency',
            'name' => 'vat_total',
            'label' => 'VAT',
            'step_order' => $stepOrder + 1,
            'options' => [
                'read_only' => true,
                'layout' => ['width' => 'quarter', 'row_id' => 'money_footer', 'slot' => 1],
            ],
        ],
        [
            'type' => 'currency',
            'name' => 'non_vatable_total',
            'label' => 'Non-VATable',
            'step_order' => $stepOrder + 2,
            'options' => [
                'read_only' => true,
                'layout' => ['width' => 'quarter', 'row_id' => 'money_footer', 'slot' => 2],
            ],
        ],
        [
            'type' => 'currency',
            'name' => 'vatable_net',
            'label' => 'VATable, net of VAT',
            'step_order' => $stepOrder + 3,
            'options' => [
                'read_only' => true,
                'layout' => ['width' => 'quarter', 'row_id' => 'money_footer', 'slot' => 3],
            ],
        ],
    ];

    if ($withSettlement) {
        $fields[] = [
            'type' => 'currency',
            'name' => 'cash_advance_amount',
            'label' => 'Cash released',
            'step_order' => $stepOrder + 4,
            'validation' => [
                'help_text' => 'Filled from the linked cash advance.',
            ],
            'options' => [
                'read_only' => true,
                'layout' => ['width' => 'half', 'row_id' => 'settlement', 'slot' => 0],
            ],
        ];
        $fields[] = [
            'type' => 'currency',
            'name' => 'settlement_amount',
            'label' => 'Settlement amount',
            'step_order' => $stepOrder + 5,
            'validation' => [
                'help_text' => 'Actual expenses minus cash released. Positive means pay the employee. Negative means surrender.',
            ],
            'options' => [
                'read_only' => true,
                'layout' => ['width' => 'half', 'row_id' => 'settlement', 'slot' => 1],
            ],
        ];
        $fields[] = [
            'type' => 'text',
            'name' => 'settlement_summary',
            'label' => 'Settlement',
            'step_order' => $stepOrder + 6,
            'options' => ['read_only' => true],
        ];
    }

    return $fields;
};

$roleStep = static function (int $order, string $role, string $label, string $action): array {
    return [
        'type' => 'role',
        'approverId' => $role,
        'step_order' => $order,
        'label' => $label,
        'condition' => ['finance_action' => $action],
    ];
};

/**
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
        'description' => 'Request a cash advance. Each forecast line carries the trip and the expense. Department Head, Finance, and the CFO approve every request. Finance or Treasury then releases the full amount.',
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
            array_replace_recursive($subsidiaryField(2, ['width' => 'half', 'row_id' => 'ca_org', 'slot' => 0]), [
                'options' => ['lock_when_grid' => 'expense_lines'],
            ]),
            [
                'type' => 'date',
                'name' => 'needed_by',
                'label' => 'Funds needed by',
                'step_order' => 3,
                'validation' => ['required' => true, 'date_min' => 'lead', 'lead_days' => 3],
                'options' => [
                    'layout' => ['width' => 'half', 'row_id' => 'ca_org', 'slot' => 1],
                    'lock_when_grid' => 'expense_lines',
                ],
            ],
            [
                'type' => 'text',
                'name' => 'purpose',
                'label' => 'Purpose',
                'step_order' => 4,
                'validation' => ['required' => true, 'placeholder' => 'Why the advance is needed'],
            ],
            [
                'type' => 'section',
                'name' => 'section_expenses',
                'label' => 'Forecast expenses',
                'step_order' => 6,
                'options' => [
                    'visibility' => [
                        'mode' => 'show_when',
                        'operator' => 'all_filled',
                        'field' => 'needed_by',
                        'fields' => ['needed_by', 'subsidiary', 'purpose'],
                    ],
                ],
            ],
            [
                'type' => 'grid',
                'name' => 'expense_lines',
                'label' => 'Forecast lines',
                'step_order' => 9,
                'validation' => ['required' => true],
                'options' => ['columns' => $forecastExpenseColumns],
            ],
            ...$moneyFooters('requested_amount', 'Requested amount', 10, false),
            [
                'type' => 'file',
                'name' => 'supporting_documents',
                'label' => 'Forecast support',
                'step_order' => 15,
            ],
            [
                'type' => 'textarea',
                'name' => 'notes',
                'label' => 'Notes',
                'step_order' => 16,
            ],
        ],
        'steps' => [
            ['type' => 'manager', 'step_order' => 1, 'label' => 'Department Head'],
            $roleStep(2, 'finance', 'Finance', 'review'),
            $roleStep(3, 'cfo', 'CFO', 'cfo'),
            $roleStep(4, 'finance', 'Release cash', 'release'),
        ],
    ],

    'liquidation' => [
        'name' => 'Liquidation',
        'description' => 'Liquidate one released cash advance. The CFO sees any difference. Finance or Treasury records the surrender or the payment.',
        'category' => 'finance',
        'doc_type_code' => 'LQ',
        'metadata_json' => [
            'form_family' => 'liquidation',
            'parent_form_family' => 'cash_advance',
            'requires_parent_submission' => true,
            'related_template_ids' => ['cash_advance'],
            'compose' => $steppedCompose,
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
                    'help_text' => 'Filled automatically when you select a released cash advance.',
                    'placeholder' => 'Select a released cash advance',
                ],
                'options' => ['read_only' => true],
            ],
            $subsidiaryField(3, ['width' => 'half', 'row_id' => 'lq_meta', 'slot' => 0]),
            [
                'type' => 'date',
                'name' => 'liquidation_date',
                'label' => 'Date submitted for liquidation',
                'step_order' => 4,
                'validation' => ['required' => true],
                'options' => ['layout' => ['width' => 'half', 'row_id' => 'lq_meta', 'slot' => 1]],
            ],
            [
                'type' => 'text',
                'name' => 'purpose',
                'label' => 'Purpose',
                'step_order' => 5,
                'options' => ['read_only' => true],
            ],
            [
                'type' => 'section',
                'name' => 'section_expenses',
                'label' => 'Actual expenses',
                'step_order' => 7,
            ],
            [
                'type' => 'grid',
                'name' => 'expense_lines',
                'label' => 'Expense lines',
                'step_order' => 10,
                'validation' => ['required' => true],
                'options' => ['columns' => $actualExpenseColumns],
            ],
            ...$moneyFooters('total_reimbursement', 'Total expenses', 11, true),
            [
                'type' => 'file',
                'name' => 'receipts',
                'label' => 'Receipts',
                'step_order' => 19,
                'validation' => ['required' => true],
            ],
            [
                'type' => 'textarea',
                'name' => 'notes',
                'label' => 'Notes',
                'step_order' => 20,
            ],
        ],
        'steps' => [
            ['type' => 'manager', 'step_order' => 1, 'label' => 'Department Head'],
            $roleStep(2, 'finance', 'Finance', 'review'),
            $roleStep(3, 'cfo', 'CFO', 'cfo'),
            $roleStep(4, 'finance', 'Finance validation', 'validate'),
            $roleStep(5, 'finance', 'Settlement', 'settlement'),
        ],
    ],

    'reimbursement' => [
        'name' => 'Reimbursement',
        'description' => 'Reimburse out-of-pocket expenses. Same approval chain as cash advance, then Finance or Treasury pays the total.',
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
            $subsidiaryField(2),
            [
                'type' => 'text',
                'name' => 'purpose',
                'label' => 'Purpose',
                'step_order' => 4,
                'validation' => ['required' => true],
            ],
            [
                'type' => 'section',
                'name' => 'section_expenses',
                'label' => 'Actual expenses',
                'step_order' => 5,
            ],
            [
                'type' => 'grid',
                'name' => 'expense_lines',
                'label' => 'Expense lines',
                'step_order' => 8,
                'validation' => ['required' => true],
                'options' => ['columns' => $reimbursementExpenseColumns],
            ],
            ...$moneyFooters('total_reimbursement', 'Total expenses', 9, false),
            [
                'type' => 'file',
                'name' => 'receipts',
                'label' => 'Receipts',
                'step_order' => 14,
                'validation' => ['required' => true],
            ],
            [
                'type' => 'textarea',
                'name' => 'notes',
                'label' => 'Notes',
                'step_order' => 15,
            ],
        ],
        'steps' => [
            ['type' => 'manager', 'step_order' => 1, 'label' => 'Department Head'],
            $roleStep(2, 'finance', 'Finance', 'review'),
            $roleStep(3, 'cfo', 'CFO', 'cfo'),
            $roleStep(4, 'finance', 'Payment', 'payment'),
        ],
    ],
];
