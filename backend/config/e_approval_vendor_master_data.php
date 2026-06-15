<?php

declare(strict_types=1);

/**
 * Canonical vendor master data shape produced from vendor_registration approvals.
 *
 * @see \App\Modules\EApproval\Services\EApprovalVendorMasterDataMapper
 */
return [
    'schema_version' => 1,
    'set_key' => 'vendors',
    'set_name' => 'Vendors',

    /** Submission value fields copied into vendor rows (excluding file attachments). */
    'value_fields' => [
        'company_name',
        'tax_id',
        'vendor_category',
        'contact_name',
        'contact_email',
        'contact_phone',
        'registered_address',
        'services_offered',
        'bank_name',
        'bank_account_no',
    ],

    /** File field whose attachments are stored under compliance_documents. */
    'attachment_field' => 'compliance_documents',

    /**
     * Dot-path segments joined for master-data lookup subtitles (category · email · tax id).
     *
     * @var list<string>
     */
    'lookup_subtitle_paths' => [
        'vendor_category',
        'contact.email',
        'tax_id',
    ],

    /**
     * Vendor row dedupe when syncing approved registrations.
     *
     * - tax_id: primary match on normalized tax ID (row code or data_json)
     * - company_name: secondary match on normalized company name
     *
     * When block_merge_on_tax_id_conflict is true, company-name matches are skipped if both
     * rows have different non-empty tax IDs (avoids merging distinct legal entities).
     */
    'dedupe' => [
        'match_by' => ['tax_id', 'company_name'],
        'company_name_requires_email_match' => false,
        'block_merge_on_tax_id_conflict' => true,
        'company_name_suffixes' => [
            'INCORPORATED',
            'CORPORATION',
            'COMPANY',
            'INC',
            'CORP',
            'LTD',
            'CO',
        ],
    ],
];
