<?php

declare(strict_types=1);

return [
    'public_links' => [
        'upload_token_minutes' => (int) env('E_APPROVAL_PUBLIC_UPLOAD_TOKEN_MINUTES', 60),
        'rate_limit_per_minute' => (int) env('E_APPROVAL_PUBLIC_RATE_LIMIT', 30),
    ],
    /*
    | Plan tier entitlements live in config/billing.php (TenantPlanEntitlementsService).
    | EApprovalPlanFeaturesService reads the e_approval module slice from that catalog.
    */

    'form_templates' => array_merge([
        'leave_request' => [
            'name' => 'Leave request',
            'description' => 'Standard leave application with manager approval.',
            'category' => 'hr',
            'fields' => [
                [
                    'type' => 'section',
                    'name' => 'section_request',
                    'label' => 'Request details',
                    'step_order' => 1,
                ],
                [
                    'type' => 'text',
                    'name' => 'employee_name',
                    'label' => 'Employee name',
                    'step_order' => 2,
                    'validation' => ['required' => true],
                ],
                [
                    'type' => 'select',
                    'name' => 'leave_type',
                    'label' => 'Leave type',
                    'step_order' => 3,
                    'validation' => ['required' => true],
                    'options' => [
                        'choices' => [
                            ['value' => 'annual', 'label' => 'Annual leave'],
                            ['value' => 'sick', 'label' => 'Sick leave'],
                            ['value' => 'unpaid', 'label' => 'Unpaid leave'],
                        ],
                    ],
                ],
                [
                    'type' => 'date',
                    'name' => 'start_date',
                    'label' => 'Start date',
                    'step_order' => 4,
                    'validation' => ['required' => true],
                    'options' => ['layout' => ['width' => 'half', 'row_id' => 'leave_dates', 'slot' => 0]],
                ],
                [
                    'type' => 'date',
                    'name' => 'end_date',
                    'label' => 'End date',
                    'step_order' => 5,
                    'validation' => ['required' => true],
                    'options' => ['layout' => ['width' => 'half', 'row_id' => 'leave_dates', 'slot' => 1]],
                ],
                [
                    'type' => 'textarea',
                    'name' => 'reason',
                    'label' => 'Reason',
                    'step_order' => 6,
                    'validation' => ['required' => true, 'placeholder' => 'Brief reason for leave'],
                ],
            ],
            'steps' => [
                ['type' => 'manager', 'step_order' => 1],
            ],
        ],
        'purchase_request' => [
            'name' => 'Purchase request (CAPEX)',
            'description' => 'Capital expenditure request with amount and finance routing.',
            'category' => 'finance',
            'fields' => [
                [
                    'type' => 'text',
                    'name' => 'project_title',
                    'label' => 'Project / item title',
                    'step_order' => 1,
                    'validation' => ['required' => true],
                ],
                [
                    'type' => 'currency',
                    'name' => 'amount',
                    'label' => 'Estimated amount',
                    'step_order' => 2,
                    'validation' => ['required' => true],
                    'options' => ['layout' => ['width' => 'half', 'row_id' => 'capex_row', 'slot' => 0]],
                ],
                [
                    'type' => 'select',
                    'name' => 'cost_center',
                    'label' => 'Cost center',
                    'step_order' => 3,
                    'validation' => ['required' => true],
                    'options' => [
                        'choices' => [
                            ['value' => 'it', 'label' => 'IT'],
                            ['value' => 'network', 'label' => 'Network'],
                            ['value' => 'facilities', 'label' => 'Facilities'],
                        ],
                        'layout' => ['width' => 'half', 'row_id' => 'capex_row', 'slot' => 1],
                    ],
                ],
                [
                    'type' => 'textarea',
                    'name' => 'justification',
                    'label' => 'Business justification',
                    'step_order' => 4,
                    'validation' => ['required' => true],
                ],
                [
                    'type' => 'file',
                    'name' => 'quote_attachment',
                    'label' => 'Quote / attachment',
                    'step_order' => 5,
                ],
            ],
            'steps' => [
                ['type' => 'user', 'step_order' => 1, 'approverId' => ''],
            ],
        ],
        'employee_onboarding' => [
            'name' => 'Employee onboarding',
            'description' => 'Collect new hire details for HR and IT provisioning.',
            'category' => 'hr',
            'fields' => [
                [
                    'type' => 'section',
                    'name' => 'section_personal',
                    'label' => 'Personal information',
                    'step_order' => 1,
                ],
                [
                    'type' => 'text',
                    'name' => 'full_name',
                    'label' => 'Full name',
                    'step_order' => 2,
                    'validation' => ['required' => true],
                    'options' => ['layout' => ['width' => 'half', 'row_id' => 'name_row', 'slot' => 0]],
                ],
                [
                    'type' => 'email',
                    'name' => 'work_email',
                    'label' => 'Work email',
                    'step_order' => 3,
                    'validation' => ['required' => true],
                    'options' => ['layout' => ['width' => 'half', 'row_id' => 'name_row', 'slot' => 1]],
                ],
                [
                    'type' => 'phone',
                    'name' => 'mobile_phone',
                    'label' => 'Mobile phone',
                    'step_order' => 4,
                    'validation' => ['required' => true],
                ],
                [
                    'type' => 'date',
                    'name' => 'start_date',
                    'label' => 'Start date',
                    'step_order' => 5,
                    'validation' => ['required' => true],
                ],
                [
                    'type' => 'select',
                    'name' => 'department',
                    'label' => 'Department',
                    'step_order' => 6,
                    'validation' => ['required' => true],
                    'options' => [
                        'choices' => [
                            ['value' => 'engineering', 'label' => 'Engineering'],
                            ['value' => 'operations', 'label' => 'Operations'],
                            ['value' => 'finance', 'label' => 'Finance'],
                        ],
                    ],
                ],
            ],
            'steps' => [
                ['type' => 'manager', 'step_order' => 1],
            ],
        ],
    ], require __DIR__.'/e_approval_finance_procurement_templates.php'),
];
