<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

/**
 * TowerOS telecom operations acronym glossary (platform-managed, tenant-facing).
 */
final class OperationalAcronymDefaults
{
    /**
     * @return list<array{acronym: string, definition: string, category?: string|null, sort_order?: int}>
     */
    public static function all(): array
    {
        return [
            ['acronym' => 'BFP', 'definition' => 'Bureau of Fire Protection', 'category' => 'Regulatory'],
            ['acronym' => 'BOI', 'definition' => 'Board of Investments', 'category' => 'Regulatory'],
            ['acronym' => 'BOQ', 'definition' => 'Bill of Quantities', 'category' => 'Construction'],
            ['acronym' => 'BP', 'definition' => 'Building Permit', 'category' => 'Permitting'],
            ['acronym' => 'BTS', 'definition' => 'Build-to-Suit', 'category' => 'Rollout'],
            ['acronym' => 'CAAP', 'definition' => 'Civil Aviation Authority of the Philippines', 'category' => 'Regulatory'],
            ['acronym' => 'CFEI', 'definition' => 'Certificate of Final Electrical Inspection', 'category' => 'Permitting'],
            ['acronym' => 'CME', 'definition' => 'Civil, Mechanical, Electrical (construction discipline)', 'category' => 'Rollout'],
            ['acronym' => 'COL', 'definition' => 'Contract of Lease', 'category' => 'Legal'],
            ['acronym' => 'CSHP', 'definition' => 'Construction Safety and Health Program', 'category' => 'Safety'],
            ['acronym' => 'DDD', 'definition' => 'Detailed Design Drawing', 'category' => 'Engineering'],
            ['acronym' => 'DENR ECC / CNC', 'definition' => 'Environmental Compliance Certificate / Cert. of Non-Coverage', 'category' => 'Regulatory'],
            ['acronym' => 'DICT', 'definition' => 'Department of Information and Communications Technology', 'category' => 'Regulatory'],
            ['acronym' => 'DLP', 'definition' => 'Defect Liability Period', 'category' => 'Construction'],
            ['acronym' => 'DTIP', 'definition' => 'Data Transmission Industry Participant (per RA 12234)', 'category' => 'Regulatory'],
            ['acronym' => 'eLAS', 'definition' => 'Electronic Lease Approval Sheet', 'category' => 'Legal'],
            ['acronym' => 'FM', 'definition' => 'Force Majeure (or Facilities Management, context-dependent)', 'category' => 'Operations'],
            ['acronym' => 'HOA', 'definition' => "Homeowners' Association", 'category' => 'Community'],
            ['acronym' => 'HTA', 'definition' => 'Hard-to-Acquire', 'category' => 'Rollout'],
            ['acronym' => 'LD', 'definition' => 'Liquidated Damages', 'category' => 'Legal'],
            ['acronym' => 'LTI', 'definition' => 'Lost-Time Injury', 'category' => 'Safety'],
            ['acronym' => 'MERALCO', 'definition' => 'Manila Electric Company', 'category' => 'Utilities'],
            ['acronym' => 'MLA', 'definition' => 'Master Lease Agreement', 'category' => 'Legal'],
            ['acronym' => 'MNO', 'definition' => 'Mobile Network Operator', 'category' => 'Rollout'],
            ['acronym' => 'MOC', 'definition' => 'Memorandum of Contract', 'category' => 'Legal'],
            ['acronym' => 'NCIP / FPIC', 'definition' => 'National Commission on Indigenous Peoples / Free Prior Informed Consent', 'category' => 'Regulatory'],
            ['acronym' => 'NCR', 'definition' => 'National Capital Region', 'category' => 'Geography'],
            ['acronym' => 'NHCP', 'definition' => 'National Historical Commission of the Philippines', 'category' => 'Regulatory'],
            ['acronym' => 'NSCP', 'definition' => 'National Structural Code of the Philippines', 'category' => 'Engineering'],
            ['acronym' => 'NTC', 'definition' => 'National Telecommunications Commission', 'category' => 'Regulatory'],
            ['acronym' => 'OBO', 'definition' => 'Office of the Building Official (LGU)', 'category' => 'Permitting'],
            ['acronym' => 'PEC', 'definition' => 'Philippine Electrical Code', 'category' => 'Engineering'],
            ['acronym' => 'PMO', 'definition' => 'Project Management Office', 'category' => 'Operations'],
            ['acronym' => 'PSH', 'definition' => 'Problematic Site Handling', 'category' => 'Rollout'],
            ['acronym' => 'RFI / RFTI', 'definition' => 'Ready for Installation / Ready for Telecom Installation', 'category' => 'Rollout'],
            ['acronym' => 'RGS', 'definition' => 'Revenue Generating Site', 'category' => 'Rollout'],
            ['acronym' => 'ROW', 'definition' => 'Right of Way', 'category' => 'Legal'],
            ['acronym' => 'RTB', 'definition' => 'Ready-to-Build', 'category' => 'Rollout'],
            ['acronym' => 'SAQ', 'definition' => 'Site Acquisition', 'category' => 'Rollout'],
            ['acronym' => 'SBT / SI', 'definition' => 'Soil Boring Test / Structural Investigation', 'category' => 'Engineering'],
            ['acronym' => 'SKOM', 'definition' => 'Site Kick-Off Meeting', 'category' => 'Operations'],
            ['acronym' => 'SLA', 'definition' => 'Service Level Agreement', 'category' => 'Operations'],
            ['acronym' => 'SR', 'definition' => 'Search Ring', 'category' => 'Rollout'],
            ['acronym' => 'SSOT', 'definition' => 'Single Source of Truth', 'category' => 'Operations'],
            ['acronym' => 'TCO ID', 'definition' => 'Tower Co Identifier (internal site ID)', 'category' => 'Rollout'],
            ['acronym' => 'TSSR', 'definition' => 'Technical Site Survey Report', 'category' => 'Engineering'],
            ['acronym' => 'VO', 'definition' => 'Variation Order', 'category' => 'Construction'],
        ];
    }
}
