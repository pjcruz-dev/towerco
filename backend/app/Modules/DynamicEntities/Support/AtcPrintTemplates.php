<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Support;

/**
 * Dynamic print templates for Dynamic Entity records (Metacoresoft-style documents).
 *
 * @phpstan-type PrintSettings array{
 *   list_label: string,
 *   document_title: string,
 *   layout: string,
 *   status_stamp: bool,
 *   signatures: bool,
 *   signature_style: string,
 *   company_name: ?string,
 *   company_address: ?string,
 *   subtitle_fields: list<string>,
 *   subtitle_prefix: ?string,
 *   title_align: string,
 *   highlight_fields: list<string>,
 *   currency_fields: list<string>,
 *   terms_paragraphs: list<string>,
 *   related_expand?: list<array{field: string, title: string, fields: list<string>}>,
 *   workflow_stages?: list<array{stage: string, plan: ?string, actual: ?string}>,
 *   milestone_fields?: list<string>,
 *   remarks_field?: ?string,
 *   show_site_assignment?: bool,
 *   department?: ?string,
 *   orientation?: ?string,
 *   template_html?: ?string,
 *   template_css?: ?string,
 *   id?: string,
 *   name?: string,
 *   updated_at?: ?string,
 *   templates?: list<array<string, mixed>>,
 *   default_template_id?: ?string
 * }
 */
final class AtcPrintTemplates
{
    /**
     * @return PrintSettings
     */
    public static function defaultsForSlug(string $slug): array
    {
        return match ($slug) {
            'site_permits' => [
                'list_label' => 'Permit Transmittal',
                'document_title' => 'PERMIT TRANSMITTAL',
                'layout' => 'transmittal',
                'status_stamp' => true,
                'signatures' => true,
                'signature_style' => 'prepared',
                'company_name' => null,
                'company_address' => null,
                'subtitle_fields' => ['permit_number', 'permit_type'],
                'subtitle_prefix' => null,
                'title_align' => 'left',
                'highlight_fields' => [],
                'currency_fields' => ['permit_cost'],
                'terms_paragraphs' => [],
                'related_expand' => [
                    [
                        'field' => 'tower_site_id',
                        'title' => 'Subject Site',
                        'fields' => [
                            'site_code',
                            'mno_anchor_site_id',
                            'site_name',
                            'town',
                            'province',
                            'coordinates',
                            'solution',
                        ],
                    ],
                ],
            ],
            'land_leases' => [
                'list_label' => 'Land Lease Agreement',
                'document_title' => 'LAND LEASE AGREEMENT SUMMARY',
                'layout' => 'agreement',
                'status_stamp' => true,
                'signatures' => true,
                'signature_style' => 'parties',
                'company_name' => null,
                'company_address' => null,
                'subtitle_fields' => ['lease_number'],
                'subtitle_prefix' => 'Lease No:',
                'title_align' => 'center',
                'highlight_fields' => ['monthly_rent'],
                'currency_fields' => ['monthly_rent', 'security_deposit'],
                'terms_paragraphs' => [
                    'The Lessee shall pay the Monthly Rent and other amounts due under this lease on the agreed Payment Terms.',
                    'The Lessor warrants lawful ownership or authority over the Leased Property and shall maintain quiet enjoyment for the Lessee during the lease term.',
                    'Either party shall comply with applicable laws, permits, and site access requirements related to the tower facility on the Leased Property.',
                    'This summary is issued for operational reference; the executed lease agreement governs in case of conflict.',
                ],
                'related_expand' => [
                    [
                        'field' => 'tower_site_id',
                        'title' => 'Leased Property (Tower Site)',
                        'fields' => [
                            'site_code',
                            'site_name',
                            'region',
                            'coordinates',
                            'town',
                            'province',
                        ],
                    ],
                    [
                        'field' => 'lessor_id',
                        'title' => 'Contract Parties',
                        'fields' => [
                            'lessor_name',
                            'contact_number',
                            'email_address',
                            'lessor_address',
                        ],
                    ],
                ],
            ],
            'saq_trackers' => [
                'list_label' => 'SAQ Status Report',
                'document_title' => 'SITE ACQUISITION STATUS REPORT',
                'layout' => 'status_report',
                'status_stamp' => true,
                'signatures' => true,
                'signature_style' => 'prepared',
                'company_name' => null,
                'company_address' => null,
                'subtitle_fields' => [],
                'subtitle_prefix' => null,
                'title_align' => 'center',
                'highlight_fields' => [],
                'currency_fields' => [],
                'terms_paragraphs' => [],
                'related_expand' => [
                    [
                        'field' => 'tower_site_id',
                        'title' => 'Tower Site',
                        'fields' => [
                            'site_name',
                            'site_code',
                            'region',
                            'province',
                            'town',
                            'mno_anchor_site_id',
                        ],
                    ],
                    [
                        'field' => 'saq_vendor_id',
                        'title' => 'SAQ Vendor',
                        'fields' => ['name', 'vendor_name', 'company_name'],
                    ],
                    [
                        'field' => 'lessor_id',
                        'title' => 'Lessor',
                        'fields' => ['lessor_name', 'name', 'contact_number'],
                    ],
                ],
                'workflow_stages' => [
                    ['stage' => 'Site hunting', 'plan' => 'site_hunting_plan_date', 'actual' => 'site_hunting_actual'],
                    ['stage' => 'SHR submitted', 'plan' => null, 'actual' => 'shr_submit_date'],
                    ['stage' => 'Pre-assess to MNO', 'plan' => null, 'actual' => 'pre_assess_submitted_to_mno'],
                    ['stage' => 'Pre-assess approved', 'plan' => null, 'actual' => 'pre_assess_approved_date'],
                    ['stage' => 'TSSR submitted', 'plan' => 'tssr_plan_date', 'actual' => 'atc_tssr_submitted'],
                    ['stage' => 'TSSR to MNO', 'plan' => null, 'actual' => 'tssr_submission_to_mno'],
                    ['stage' => 'TSSR fully approved', 'plan' => null, 'actual' => 'tssr_fully_approved_date'],
                    ['stage' => 'MOC', 'plan' => 'moc_plan_date', 'actual' => 'moc_actual_date'],
                    ['stage' => 'ELAS applied', 'plan' => null, 'actual' => 'elas_applied_date'],
                    ['stage' => 'Signed & sealed', 'plan' => 'signed_seal_plan_date', 'actual' => 'signed_seal_actual'],
                    ['stage' => 'Lot segregation', 'plan' => 'lotseg_plan_mob_date', 'actual' => 'lotseg_mob_date'],
                    ['stage' => 'Soil boring / SI', 'plan' => 'sbt_si_plan_date', 'actual' => 'sbt_si_report_received'],
                ],
                'milestone_fields' => [
                    'overall_status',
                    'saq_milestone',
                    'saq_submilestone',
                    'saq_lead',
                    'saq_officer',
                ],
                'remarks_field' => 'saq_remarks',
                'show_site_assignment' => true,
                'department' => 'SITE ACQUISITION',
            ],
            'power_trackers' => [
                'list_label' => 'Power Status Report',
                'document_title' => 'POWER STATUS REPORT',
                'layout' => 'status_report',
                'status_stamp' => true,
                'signatures' => true,
                'signature_style' => 'prepared',
                'company_name' => null,
                'company_address' => null,
                'subtitle_fields' => [],
                'subtitle_prefix' => null,
                'title_align' => 'center',
                'highlight_fields' => [],
                'currency_fields' => [],
                'terms_paragraphs' => [],
                'related_expand' => [
                    [
                        'field' => 'tower_site_id',
                        'title' => 'Tower Site',
                        'fields' => ['site_name', 'site_code', 'region', 'province', 'town'],
                    ],
                    [
                        'field' => 'electric_utility_id',
                        'title' => 'Electric Utility',
                        'fields' => ['name', 'utility_name'],
                    ],
                    [
                        'field' => 'power_vendor_id',
                        'title' => 'Power Vendor',
                        'fields' => ['name', 'vendor_name'],
                    ],
                ],
                'workflow_stages' => [
                    ['stage' => 'Permanent power application', 'plan' => null, 'actual' => 'permanent_power_application_date'],
                    ['stage' => 'Survey with utility', 'plan' => null, 'actual' => 'survey_with_utility_date'],
                    ['stage' => 'Project design & costing', 'plan' => null, 'actual' => 'project_design_costing_date'],
                    ['stage' => 'Payment / bill deposit', 'plan' => null, 'actual' => 'payment_bill_deposit_date'],
                    ['stage' => 'Line extension start', 'plan' => null, 'actual' => 'start_line_extension_date'],
                    ['stage' => 'Line extension complete', 'plan' => null, 'actual' => 'completion_line_extension_date'],
                    ['stage' => 'Final inspection', 'plan' => null, 'actual' => 'final_inspection_date'],
                    ['stage' => 'CFEI application', 'plan' => null, 'actual' => 'cfei_application_date'],
                    ['stage' => 'CFEI secured', 'plan' => null, 'actual' => 'cfei_secured_date'],
                    ['stage' => 'Energization (tempo)', 'plan' => null, 'actual' => 'energization_tempo_date'],
                    ['stage' => 'Energization (permanent)', 'plan' => null, 'actual' => 'energization_permanent_date'],
                ],
                'milestone_fields' => [
                    'energization_status',
                    'external_status',
                    'current_milestone',
                    'connection_type',
                    'main_tagging',
                ],
                'remarks_field' => null,
                'show_site_assignment' => true,
                'department' => 'POWER',
            ],
            'postcon_trackers' => [
                'list_label' => 'Postcon Status Report',
                'document_title' => 'POST-CONSTRUCTION STATUS REPORT',
                'layout' => 'status_report',
                'status_stamp' => true,
                'signatures' => true,
                'signature_style' => 'prepared',
                'company_name' => null,
                'company_address' => null,
                'subtitle_fields' => [],
                'subtitle_prefix' => null,
                'title_align' => 'center',
                'highlight_fields' => [],
                'currency_fields' => [],
                'terms_paragraphs' => [],
                'related_expand' => [
                    [
                        'field' => 'tower_site_id',
                        'title' => 'Tower Site',
                        'fields' => ['site_name', 'site_code', 'region', 'province', 'town'],
                    ],
                    [
                        'field' => 'saq_contractor_id',
                        'title' => 'SAQ Contractor',
                        'fields' => ['name', 'vendor_name'],
                    ],
                ],
                'workflow_stages' => [
                    ['stage' => 'RTB', 'plan' => null, 'actual' => 'rtb_date'],
                    ['stage' => 'CME RFTI (internal)', 'plan' => null, 'actual' => 'cme_rfti_actual_internal'],
                    ['stage' => 'CFEI applied', 'plan' => null, 'actual' => 'cfei_applied_date'],
                    ['stage' => 'CFEI secured', 'plan' => null, 'actual' => 'cfei_secured_date'],
                    ['stage' => 'Occupancy applied', 'plan' => null, 'actual' => 'occupancy_applied_date'],
                    ['stage' => 'Occupancy secured', 'plan' => null, 'actual' => 'occupancy_secured_date'],
                    ['stage' => 'FSIC applied', 'plan' => null, 'actual' => 'fsic_applied_date'],
                    ['stage' => 'FSIC secured', 'plan' => null, 'actual' => 'fsic_secured_date'],
                ],
                'milestone_fields' => [
                    'cfei_milestone',
                    'redline',
                    'logbook',
                    'site_photos',
                    'cme_requirements_complete',
                    'cme_progress',
                ],
                'remarks_field' => 'postcon_remarks',
                'show_site_assignment' => true,
                'department' => 'POST-CONSTRUCTION',
            ],
            'construction_projects' => [
                'list_label' => 'Construction Status Report',
                'document_title' => 'CONSTRUCTION STATUS REPORT',
                'layout' => 'status_report',
                'status_stamp' => true,
                'signatures' => true,
                'signature_style' => 'prepared',
                'company_name' => null,
                'company_address' => null,
                'subtitle_fields' => ['project_number'],
                'subtitle_prefix' => 'Project No:',
                'title_align' => 'center',
                'highlight_fields' => ['budget', 'total_cost'],
                'currency_fields' => ['budget'],
                'terms_paragraphs' => [],
                'related_expand' => [
                    [
                        'field' => 'tower_site_id',
                        'title' => 'Tower Site',
                        'fields' => ['site_name', 'site_code', 'region', 'province', 'town'],
                    ],
                    [
                        'field' => 'cme_vendor_id',
                        'title' => 'CME Vendor',
                        'fields' => ['name', 'vendor_name'],
                    ],
                ],
                'workflow_stages' => [
                    ['stage' => 'SKOM', 'plan' => null, 'actual' => 'skom_date'],
                    ['stage' => 'DDD', 'plan' => 'ddd_plan_date', 'actual' => 'ddd_actual_date'],
                    ['stage' => 'Civil works', 'plan' => 'cw_start_date', 'actual' => 'cw_completed_date'],
                    ['stage' => 'RTB', 'plan' => 'rtb_forecast', 'actual' => 'rtb_actual_date'],
                    ['stage' => 'Risk build declared', 'plan' => null, 'actual' => 'risk_build_declared_date'],
                ],
                'milestone_fields' => [
                    'project_name',
                    'status',
                    'cme_status',
                    'pm_in_charge',
                    'supervisor_in_charge',
                    'site_type',
                    'percentage_in_progress',
                ],
                'remarks_field' => 'ddd_remarks',
                'show_site_assignment' => true,
                'department' => 'CONSTRUCTION',
            ],
            'tower_sites' => [
                'list_label' => 'Site Profile',
                'document_title' => 'TOWER SITE PROFILE',
                'layout' => 'grouped',
                'status_stamp' => true,
                'signatures' => true,
                'signature_style' => 'prepared',
                'company_name' => null,
                'company_address' => null,
                'subtitle_fields' => ['site_code', 'site_name'],
                'subtitle_prefix' => null,
                'title_align' => 'center',
                'highlight_fields' => [],
                'currency_fields' => [],
                'terms_paragraphs' => [],
            ],
            'procurement_requests' => [
                'list_label' => 'PR Summary',
                'document_title' => 'PROCUREMENT REQUEST SUMMARY',
                'layout' => 'grouped',
                'status_stamp' => true,
                'signatures' => true,
                'signature_style' => 'prepared',
                'company_name' => null,
                'company_address' => null,
                'subtitle_fields' => ['pr_number', 'po_number'],
                'subtitle_prefix' => null,
                'title_align' => 'left',
                'highlight_fields' => ['pr_amount', 'po_amount'],
                'currency_fields' => ['pr_amount', 'po_amount', 'invoiced_amount'],
                'terms_paragraphs' => [],
                'related_expand' => [
                    [
                        'field' => 'tower_site_id',
                        'title' => 'Tower Site',
                        'fields' => ['site_name', 'site_code', 'region', 'province', 'town'],
                    ],
                ],
            ],
            'site_tickets' => [
                'list_label' => 'Ticket Print',
                'document_title' => 'SITE TICKET',
                'layout' => 'grouped',
                'status_stamp' => true,
                'signatures' => true,
                'signature_style' => 'prepared',
                'company_name' => null,
                'company_address' => null,
                'subtitle_fields' => ['ticket_number', 'priority'],
                'subtitle_prefix' => null,
                'title_align' => 'left',
                'highlight_fields' => [],
                'currency_fields' => [],
                'terms_paragraphs' => [],
                'related_expand' => [
                    [
                        'field' => 'tower_site_id',
                        'title' => 'Tower Site',
                        'fields' => ['site_name', 'site_code', 'region', 'province', 'town'],
                    ],
                ],
            ],
            'sales_transactions' => [
                'list_label' => 'Sales Invoice',
                'document_title' => 'SALES TRANSACTION',
                'layout' => 'grouped',
                'status_stamp' => true,
                'signatures' => true,
                'signature_style' => 'prepared',
                'company_name' => null,
                'company_address' => null,
                'subtitle_fields' => [],
                'subtitle_prefix' => null,
                'title_align' => 'left',
                'highlight_fields' => [],
                'currency_fields' => [],
                'terms_paragraphs' => [],
            ],
            'purchase_transactions' => [
                'list_label' => 'Purchase Receipt',
                'document_title' => 'PURCHASE TRANSACTION',
                'layout' => 'grouped',
                'status_stamp' => true,
                'signatures' => true,
                'signature_style' => 'prepared',
                'company_name' => null,
                'company_address' => null,
                'subtitle_fields' => [],
                'subtitle_prefix' => null,
                'title_align' => 'left',
                'highlight_fields' => [],
                'currency_fields' => [],
                'terms_paragraphs' => [],
            ],
            'bir_form_2307_certificates' => [
                'list_label' => 'BIR Form 2307',
                'document_title' => 'CERTIFICATE OF CREDITABLE TAX WITHHELD AT SOURCE',
                'layout' => 'bir_2307',
                'status_stamp' => false,
                'signatures' => false,
                'signature_style' => 'prepared',
                'company_name' => null,
                'company_address' => null,
                'subtitle_fields' => ['control_number', 'period_from', 'period_to'],
                'subtitle_prefix' => null,
                'title_align' => 'center',
                'highlight_fields' => [],
                'currency_fields' => [
                    'income_month1',
                    'income_month2',
                    'income_month3',
                    'tax_withheld',
                    'tax_rate',
                ],
                'terms_paragraphs' => [],
            ],
            default => [
                'list_label' => 'Print',
                'document_title' => '',
                'layout' => 'grouped',
                'status_stamp' => true,
                'signatures' => true,
                'signature_style' => 'prepared',
                'company_name' => null,
                'company_address' => null,
                'subtitle_fields' => [],
                'subtitle_prefix' => null,
                'title_align' => 'left',
                'highlight_fields' => [],
                'currency_fields' => [],
                'terms_paragraphs' => [],
            ],
        };
    }

    /**
     * @return list<array{name: string, sort_order: int, start_collapsed: bool, fields: list<string>}>
     */
    public static function sitePermitsGroups(): array
    {
        return [
            [
                'name' => 'Permit',
                'sort_order' => 10,
                'start_collapsed' => false,
                'fields' => [
                    'permit_number',
                    'permit_type',
                    'permit_stage',
                    'status',
                    'issuing_agency',
                    'permit_cost',
                    'permit_milestone',
                ],
            ],
            [
                'name' => 'Subject Site',
                'sort_order' => 20,
                'start_collapsed' => false,
                'fields' => ['tower_site_id'],
            ],
            [
                'name' => 'Processing Dates',
                'sort_order' => 30,
                'start_collapsed' => false,
                'fields' => [
                    'plan_date',
                    'applied_date',
                    'target_release',
                    'secured_date',
                    'issue_date',
                    'expiry_date',
                ],
            ],
            [
                'name' => 'Remarks',
                'sort_order' => 40,
                'start_collapsed' => false,
                'fields' => ['permit_remarks'],
            ],
        ];
    }

    /**
     * @return list<array{name: string, sort_order: int, start_collapsed: bool, fields: list<string>}>
     */
    public static function landLeasesGroups(): array
    {
        return [
            [
                'name' => 'Contract Parties',
                'sort_order' => 10,
                'start_collapsed' => false,
                'fields' => ['lessor_id'],
            ],
            [
                'name' => 'Leased Property (Tower Site)',
                'sort_order' => 20,
                'start_collapsed' => false,
                'fields' => ['tower_site_id'],
            ],
            [
                'name' => 'Lease Terms & Financials',
                'sort_order' => 30,
                'start_collapsed' => false,
                'fields' => [
                    'lease_number',
                    'status',
                    'start_date',
                    'end_date',
                    'period_of_lease_years',
                    'payment_terms',
                    'monthly_rent',
                    'escalation',
                    'security_deposit',
                    'advance_rental_months',
                    'payment_remarks',
                ],
            ],
            [
                'name' => 'Terms and Conditions',
                'sort_order' => 40,
                'start_collapsed' => false,
                'fields' => [],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $stored
     * @return PrintSettings
     */
    public static function merge(?array $stored, string $slug, string $entityName): array
    {
        $base = self::defaultsForSlug($slug);
        if ($base['document_title'] === '') {
            $base['document_title'] = strtoupper($entityName);
        }

        if (($base['layout'] ?? '') === 'saq_status_report') {
            $base['layout'] = 'status_report';
        }

        if (! is_array($stored) || $stored === []) {
            return self::withTemplatesCollection($base, [], null);
        }

        $templates = [];
        $defaultId = null;

        if (isset($stored['templates']) && is_array($stored['templates']) && $stored['templates'] !== []) {
            foreach ($stored['templates'] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $templates[] = self::normalizeTemplateRow($row, $base);
            }
            $defaultId = isset($stored['default_template_id']) && is_string($stored['default_template_id'])
                ? $stored['default_template_id']
                : null;
        } else {
            // Legacy single-template blob → one named template.
            $legacy = self::mergeTemplateFields($stored, $base);
            $legacy['id'] = self::stableLegacyId($slug);
            $legacy['name'] = (string) ($legacy['list_label'] ?: ($legacy['document_title'] ?: $entityName.' Template'));
            $legacy['updated_at'] = null;
            $templates[] = $legacy;
            $defaultId = $legacy['id'];
        }

        if ($templates === []) {
            return self::withTemplatesCollection($base, [], null);
        }

        return self::withTemplatesCollection($base, $templates, $defaultId);
    }

    /**
     * @param  PrintSettings  $base
     * @param  list<array<string, mixed>>  $templates
     * @return PrintSettings
     */
    public static function withTemplatesCollection(array $base, array $templates, ?string $defaultId): array
    {
        if ($templates === []) {
            $seed = self::mergeTemplateFields($base, $base);
            $seed['id'] = self::newTemplateId();
            $seed['name'] = (string) ($seed['list_label'] ?: ($seed['document_title'] ?: 'Print Template'));
            $seed['updated_at'] = null;
            $templates = [$seed];
            $defaultId = $seed['id'];
        }

        $ids = [];
        foreach ($templates as $t) {
            $ids[(string) ($t['id'] ?? '')] = true;
        }

        if ($defaultId === null || $defaultId === '' || ! isset($ids[$defaultId])) {
            $defaultId = (string) ($templates[0]['id'] ?? self::newTemplateId());
        }

        $active = $templates[0];
        foreach ($templates as $t) {
            if ((string) ($t['id'] ?? '') === $defaultId) {
                $active = $t;
                break;
            }
        }

        // Flatten active/default fields for backward-compatible consumers.
        $flat = self::mergeTemplateFields($active, $base);
        $flat['templates'] = array_values($templates);
        $flat['default_template_id'] = $defaultId;

        return $flat;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  PrintSettings  $base
     * @return array<string, mixed>
     */
    public static function normalizeTemplateRow(array $row, array $base): array
    {
        $merged = self::mergeTemplateFields($row, $base);
        $id = isset($row['id']) && is_string($row['id']) && $row['id'] !== ''
            ? $row['id']
            : self::newTemplateId();
        $name = isset($row['name']) && is_string($row['name']) && trim($row['name']) !== ''
            ? trim($row['name'])
            : (string) ($merged['list_label'] ?: ($merged['document_title'] ?: 'Print Template'));
        $merged['id'] = $id;
        $merged['name'] = $name;
        $merged['updated_at'] = isset($row['updated_at']) && is_string($row['updated_at'])
            ? $row['updated_at']
            : null;

        return $merged;
    }

    public static function newTemplateId(): string
    {
        return (string) \Illuminate\Support\Str::uuid();
    }

    public static function stableLegacyId(string $slug): string
    {
        // Deterministic so re-reads of legacy JSON keep the same id until saved as multi.
        return 'legacy-'.substr(hash('sha256', $slug), 0, 32);
    }

    /**
     * @param  array<string, mixed>  $stored
     * @param  PrintSettings  $base
     * @return PrintSettings
     */
    public static function mergeTemplateFields(array $stored, array $base): array
    {
        $merged = [
            'list_label' => (string) ($stored['list_label'] ?? $base['list_label']),
            'document_title' => (string) ($stored['document_title'] ?? $base['document_title']),
            'layout' => (string) ($stored['layout'] ?? $base['layout']),
            'status_stamp' => (bool) ($stored['status_stamp'] ?? $base['status_stamp']),
            'signatures' => (bool) ($stored['signatures'] ?? $base['signatures']),
            'signature_style' => (string) ($stored['signature_style'] ?? $base['signature_style']),
            'company_name' => array_key_exists('company_name', $stored)
                ? ($stored['company_name'] !== null ? (string) $stored['company_name'] : null)
                : $base['company_name'],
            'company_address' => array_key_exists('company_address', $stored)
                ? ($stored['company_address'] !== null ? (string) $stored['company_address'] : null)
                : $base['company_address'],
            'subtitle_fields' => isset($stored['subtitle_fields']) && is_array($stored['subtitle_fields'])
                ? array_values(array_map('strval', $stored['subtitle_fields']))
                : $base['subtitle_fields'],
            'subtitle_prefix' => array_key_exists('subtitle_prefix', $stored)
                ? ($stored['subtitle_prefix'] !== null ? (string) $stored['subtitle_prefix'] : null)
                : $base['subtitle_prefix'],
            'title_align' => (string) ($stored['title_align'] ?? $base['title_align']),
            'highlight_fields' => isset($stored['highlight_fields']) && is_array($stored['highlight_fields'])
                ? array_values(array_map('strval', $stored['highlight_fields']))
                : $base['highlight_fields'],
            'currency_fields' => isset($stored['currency_fields']) && is_array($stored['currency_fields'])
                ? array_values(array_map('strval', $stored['currency_fields']))
                : $base['currency_fields'],
            'terms_paragraphs' => isset($stored['terms_paragraphs']) && is_array($stored['terms_paragraphs'])
                ? array_values(array_map('strval', $stored['terms_paragraphs']))
                : $base['terms_paragraphs'],
            'related_expand' => isset($stored['related_expand']) && is_array($stored['related_expand'])
                ? $stored['related_expand']
                : ($base['related_expand'] ?? []),
            'workflow_stages' => isset($stored['workflow_stages']) && is_array($stored['workflow_stages'])
                ? $stored['workflow_stages']
                : ($base['workflow_stages'] ?? []),
            'milestone_fields' => isset($stored['milestone_fields']) && is_array($stored['milestone_fields'])
                ? array_values(array_map('strval', $stored['milestone_fields']))
                : ($base['milestone_fields'] ?? []),
            'remarks_field' => array_key_exists('remarks_field', $stored)
                ? ($stored['remarks_field'] !== null ? (string) $stored['remarks_field'] : null)
                : ($base['remarks_field'] ?? null),
            'show_site_assignment' => (bool) ($stored['show_site_assignment'] ?? $base['show_site_assignment'] ?? false),
            'department' => array_key_exists('department', $stored)
                ? ($stored['department'] !== null ? (string) $stored['department'] : null)
                : ($base['department'] ?? null),
            'orientation' => array_key_exists('orientation', $stored)
                ? ($stored['orientation'] !== null ? (string) $stored['orientation'] : null)
                : ($base['orientation'] ?? 'portrait'),
            'template_html' => array_key_exists('template_html', $stored)
                ? ($stored['template_html'] !== null ? (string) $stored['template_html'] : null)
                : ($base['template_html'] ?? null),
            'template_css' => array_key_exists('template_css', $stored)
                ? ($stored['template_css'] !== null ? (string) $stored['template_css'] : null)
                : ($base['template_css'] ?? null),
        ];

        if (! in_array($merged['orientation'] ?? '', ['portrait', 'landscape'], true)) {
            $merged['orientation'] = 'portrait';
        }

        if ($merged['layout'] === 'saq_status_report') {
            $merged['layout'] = 'status_report';
        }

        return $merged;
    }
}
