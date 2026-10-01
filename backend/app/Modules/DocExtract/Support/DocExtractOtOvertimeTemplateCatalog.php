<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Support;

/**
 * Canonical column set for Alliance Towers OT / agency DocuSign packets.
 * Keep labels aligned with the tracker spreadsheet headers.
 */
final class DocExtractOtOvertimeTemplateCatalog
{
    public const NAME = 'OT overtime (Agency / DocuSign)';

    public const DESCRIPTION = 'Agency OT packet fields: WCN / WO / PO, resource, OT period, amount, DocuSign, CPM and LM approvals.';

    /**
     * @return array{
     *   name: string,
     *   description: string,
     *   status: string,
     *   fields: list<array{key: string, label: string, type: string, description: string|null, hint: string|null}>
     * }
     */
    public static function definition(): array
    {
        return [
            'name' => self::NAME,
            'description' => self::DESCRIPTION,
            'status' => DocExtractTemplateStatus::PUBLISHED,
            'fields' => self::fields(),
        ];
    }

    /**
     * @return list<array{key: string, label: string, type: string, description: string|null, hint: string|null}>
     */
    public static function fields(): array
    {
        return [
            self::field('agency_name', 'Agency Name', 'text', 'Agency Name / Vendor / Supplier / Supplier Name'),
            self::field('date_received_from_agency', 'Date Received From Agency', 'date', 'Date Received From Agency / Date Received'),
            self::field('wcn_reference_no', 'WCN Reference No.', 'text', 'WCN Reference No / WCN / Reference No / Reference No.'),
            self::field('work_order_id', 'Work Order ID', 'text', 'Work Order ID / Work Order / WO ID / WO No'),
            self::field('po_no', 'PO No.', 'text', 'PO No / PO Number / Purchase Order / Purchase Order No'),
            self::field('resource_name', 'Resource Name (Last, First)', 'text', 'Resource Name / Employee Name / Name (Last, First) / Name'),
            self::field('signum', 'Signum', 'text', 'Signum / Employee ID / Resource ID'),
            self::field('job_role', 'Job Role', 'text', 'Job Role / Role / Position / Job Title'),
            self::field('ot_period_from', 'OT Period (From)', 'date', 'OT Period From / Overtime From / Period From / From / Service Start Date'),
            self::field('ot_period_to', 'OT Period (To)', 'date', 'OT Period To / Overtime To / Period To / To / Service End Date'),
            self::field('type_of_ot', 'Type of OT', 'text', 'Type of OT / OT Type / Overtime Type'),
            self::field('amount_php', 'Amount (Php)', 'currency', 'Amount (Php) / Amount / OT Amount / Total Amount'),
            self::field('status', 'Status', 'text', 'Status / OT Status'),
            self::field('docusign_name', 'Docusign Name', 'text', 'DocuSign Name / Envelope Name / Document Name'),
            self::field('docusign_id', 'Docusign Id', 'text', 'DocuSign Id / DocuSign ID / Envelope ID'),
            self::field('docusign_date', 'Docusign Date', 'date', 'DocuSign Date / Envelope Date / Completed Date'),
            self::field('cpm', 'CPM', 'text', 'CPM / CPM Approver / Construction Project Manager'),
            self::field('cpm_approval_date', 'CPM Approval Date', 'date', 'CPM Approval Date / CPM Approved'),
            self::field('lm', 'LM', 'text', 'LM / Line Manager / LM Approver'),
            self::field('lm_approval_date', 'LM Approval Date', 'date', 'LM Approval Date / Line Manager Approval Date / LM Approved'),
        ];
    }

    /**
     * @return array{key: string, label: string, type: string, description: string|null, hint: string|null}
     */
    private static function field(string $key, string $label, string $type, string $hint): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'description' => null,
            'hint' => $hint,
        ];
    }
}
