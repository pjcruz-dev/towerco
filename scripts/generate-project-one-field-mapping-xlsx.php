<?php

declare(strict_types=1);

/**
 * Generates Project One manual tracker → TowerOS field mapping workbook.
 *
 * Usage: php scripts/generate-project-one-field-mapping-xlsx.php
 */

require __DIR__.'/../backend/vendor/autoload.php';

use App\Modules\ProcurementOne\Support\ProcurementExcelWorkbookWriter;

$mappingRows = [
    ['#', 'Manual Field', 'Category', 'Map Status', 'TowerOS Module', 'Table / Entity', 'System Field', 'How to Get Value in System', 'Gap / Action'],
    [1, 'TCO SITE ID', 'Site ID', 'Mapped', 'Rollout', 'rollout_programs', 'tco_site_id', 'Auto-generated on rollout create; shown in rollout detail & export', ''],
    [2, 'MNO Anchor Site ID', 'Site ID', 'Missing', '', '', '', 'Not stored today', 'Add mno_anchor_site_id on rollout or site external refs'],
    [3, 'MNO Anchor', 'Site ID', 'Mapped', 'Rollout', 'rollout_programs', 'mno', 'Values: globe, smart, dito', 'Map display names to enum'],
    [4, 'Globe Project Batch Tagging', 'Site ID', 'Partial', 'Rollout', 'rollout_programs', 'parent_rollout_id + search_ring_name', 'Batch parent (status=batch) groups sites; child rollouts inherit batch', 'Add explicit batch_tag / globe_batch_ref if needed'],
    [5, 'Project Type', 'Site ID', 'Mapped', 'Rollout', 'rollout_programs', 'project_type', 'bts, rtb, colocation/colo', ''],
    [6, 'ALLIANCE TAGGING', 'Site ID', 'Missing', '', '', '', 'Not in schema', 'Add custom tag field or tenant metadata on rollout'],
    [7, 'Region', 'Geography', 'Mapped', 'Rollout', 'rollout_programs', 'region', 'Set on rollout create/update', ''],
    [8, 'Area', 'Geography', 'Missing', '', '', '', 'Only region + territory exist', 'Add area or map to territory hierarchy'],
    [9, 'Territory', 'Geography', 'Mapped', 'Rollout', 'rollout_programs', 'territory', 'Set on rollout create/update', ''],
    [10, 'Search Ring Name', 'Geography', 'Mapped', 'Rollout', 'rollout_programs', 'search_ring_name', 'Direct field', ''],
    [11, 'Latitude (Actual)', 'Geography', 'Partial', 'Sites / SAQ', 'sites or site_candidates', 'latitude', 'Canonical coords on linked site; hunting coords on selected candidate', 'Ensure candidate→site promotion copies coords'],
    [12, 'Longitude (Actual)', 'Geography', 'Partial', 'Sites / SAQ', 'sites or site_candidates', 'longitude', 'Same as latitude', 'Same'],
    [13, 'Full Address', 'Geography', 'Missing', '', '', '', 'sites has no address', 'Add full_address (or structured address) on sites'],
    [14, 'Solution', 'Technical', 'Missing', '', '', '', 'Not distinct from project_type', 'Add solution if different from project type'],
    [15, 'Tower Height', 'Technical', 'Partial', 'TowerOne', 'towers', 'height_m', 'Via site → tower record', 'Link rollout site to tower'],
    [16, 'Tower Type', 'Technical', 'Partial', 'TowerOne / Sites', 'towers.tower_type or sites.type', 'tower_type / type', 'Tower module or site type (macro, rooftop, etc.)', 'Clarify which manual value maps where'],
    [17, 'Wind Speed', 'Technical', 'Missing', '', '', '', 'Not in schema', 'Add on site/tower engineering profile'],
    [18, 'MNO 2', 'Coloc', 'Different Model', 'Rollout', 'child rollout_programs', 'mno', 'Model as colocation child rollout, not column', 'Build coloc registry UI or import as child rows'],
    [19, 'Coloc 2 Site ID', 'Coloc', 'Different Model', 'Rollout', 'child rollout', 'tco_site_id or endorsement_ref', 'Per-tenant coloc rollout', 'Same'],
    [20, 'Coloc 2 Site Name', 'Coloc', 'Different Model', 'Sites', 'sites', 'name', 'Via linked site on child rollout', 'Same'],
    [21, 'RFTI Date (Coloc 2)', 'Coloc', 'Different Model', 'Rollout', 'child rollout', 'actual_rfi_date', 'Record RFI on coloc rollout', 'Same'],
    [22, 'SL Remarks (Coloc 2)', 'Coloc', 'Missing', '', '', '', 'No remarks field for site license', 'Add site_license_remarks on rollout'],
    [23, 'MNO 3', 'Coloc', 'Different Model', 'Rollout', '2nd child rollout', 'mno', 'Same pattern as Coloc 2', 'Same'],
    [24, 'Coloc 3 Site ID', 'Coloc', 'Different Model', 'Rollout', '2nd child', 'tco_site_id', 'Same', 'Same'],
    [25, 'Coloc 3 Site Name', 'Coloc', 'Different Model', 'Sites', 'sites', 'name', 'Same', 'Same'],
    [26, 'RFTI Date (Coloc 3)', 'Coloc', 'Different Model', 'Rollout', '2nd child', 'actual_rfi_date', 'Same', 'Same'],
    [27, 'SL Remarks (3)', 'Coloc', 'Missing', '', '', '', 'Same as Coloc 2 remarks', 'Add site_license_remarks'],
    [28, 'MOC Secured', 'Permitting', 'Partial', 'Rollout Playbook', 'milestone moc_securing / phase moc_col', 'actual_end_date', 'Derived from timeline, not standalone column', 'Optional: add explicit moc_secured_date'],
    [29, 'Brgy. Clearance Applied', 'Permitting', 'Missing', '', '', '', 'Not granular', 'Add permit tracker sub-entity or custom phase'],
    [30, 'Brgy Clearance Secured', 'Permitting', 'Missing', '', '', '', 'LGU clearance exists as optional custom phase only', 'Add brgy_clearance permit record'],
    [31, 'Locational Clearance Applied', 'Permitting', 'Partial', 'Rollout Playbook', 'milestone locational_clearance', 'start date', 'Milestone cycle; no separate Applied vs Secured', 'Split applied/secured if required'],
    [32, 'Locational Clearance Secured', 'Permitting', 'Partial', 'Rollout Playbook', 'phase permitting / milestone locational_clearance', 'actual_end_date', 'Use phase actual end or bulk phase dates API', 'Same'],
    [33, 'Excavation Permit Applied', 'Permitting', 'Missing', '', '', '', 'Not in playbook milestones', 'Add permit sub-tracker'],
    [34, 'Excavation Permit Secured', 'Permitting', 'Missing', '', '', '', 'Same', 'Same'],
    [35, 'Building Permit Applied', 'Permitting', 'Partial', 'Rollout Playbook', 'milestone building_permit', 'start date', 'Milestone in cycle', 'No applied/secured split'],
    [36, 'Building Permit Secured', 'Permitting', 'Partial', 'Rollout Playbook', 'phase / milestone building_permit', 'actual_end_date', 'Phase actual dates', 'Same'],
    [37, 'Occupancy Permit Applied', 'Permitting', 'Missing', '', '', '', 'Not in playbook', 'Add permit tracker'],
    [38, 'Occupancy Permit Secured', 'Permitting', 'Missing', '', '', '', 'Same', 'Same'],
    [39, 'CFEI Applied', 'Permitting', 'Missing', '', '', '', 'Not in playbook', 'Add permit tracker'],
    [40, 'CFEI Secured', 'Permitting', 'Missing', '', '', '', 'Same', 'Same'],
    [41, 'Date Endorsed by Globe', 'Milestones', 'Mapped', 'Rollout', 'rollout_programs', 'endorsement_date', 'Direct field (+ endorsement_ref)', ''],
    [42, 'TSSR Submitted', 'Milestones', 'Partial', 'Rollout Playbook', 'phase tssr_creation / milestone tssr_mno_approval', 'actual_start_date or actual_end_date', 'From timeline phase dates', 'Add explicit tssr_submitted_date if needed'],
    [43, 'TSSR Approved', 'Milestones', 'Mapped', 'Rollout', 'rollout_programs', 'tssr_approved_date', 'Day-1 trigger for BTS; POST .../tssr-approved', ''],
    [44, 'Risk Build Declared Date', 'Milestones', 'Partial', 'Rollout Playbook', 'phase permitting', 'actual_end_date (Risk Build gate)', 'Permitting phase gate, not dedicated date column', 'Add risk_build_declared_date for Excel parity'],
    [45, 'CW Start Date', 'Milestones', 'Partial', 'Rollout Playbook', 'phase construction / milestone construction', 'actual_start_date', 'Timeline + CME reports', ''],
    [46, 'CW Completed Date', 'Milestones', 'Partial', 'Rollout Playbook', 'milestone construction', 'actual_end_date', 'Milestone cycle segment end', ''],
    [47, 'Energization Tempo Date', 'Milestones', 'Missing', '', '', '', 'Energization merged into construction phase in timeline', 'Add energization_tempo_date or split energization milestone'],
    [48, 'Energization (Permanent)', 'Milestones', 'Partial', 'Rollout Playbook', 'milestone energization', 'actual_end_date', 'Fine-grained milestone in cycle', 'Tempo vs permanent not split'],
    [49, 'RFTI Docs Submitted', 'Milestones', 'Partial', 'Rollout Playbook', 'milestone rfti_submission', 'milestone dates', 'Milestone cycle', ''],
    [50, 'RFTI Docs Signed (Tempo)', 'Milestones', 'Missing', '', '', '', 'No tempo vs permanent RFTI split', 'Add rfti_signed_tempo_date'],
    [51, 'RFT Docs Signed (Permanent)', 'Milestones', 'Mapped', 'Rollout', 'rollout_programs', 'actual_rfi_date', 'POST .../rfi-recorded (RFI in system = RFT in manual)', ''],
    [52, 'SL Submitted', 'Milestones', 'Partial', 'Rollout Playbook', 'milestone site_license', 'start / target dates', 'Milestone cycle', ''],
    [53, 'SL Signed', 'Milestones', 'Mapped', 'Rollout', 'rollout_programs', 'site_license_executed_date', 'Day-1 for colocation; direct field', ''],
];

$gapSummaryRows = [
    ['Priority', 'Gap Area', 'Manual Fields Affected', 'Recommended Action', 'Suggested Module'],
    ['P1', 'MNO external site reference', 'MNO Anchor Site ID', 'Add mno_anchor_site_id on rollout_programs or site external refs table', 'Rollout / Sites'],
    ['P1', 'Site address', 'Full Address', 'Add full_address (or structured address fields) on sites', 'Sites'],
    ['P1', 'Geography hierarchy', 'Area', 'Add area field or formal region → area → territory hierarchy', 'Rollout'],
    ['P1', 'Permit tracker', 'Brgy, Excavation, Occupancy, CFEI (Applied/Secured pairs)', 'New rollout_permits table: permit_type, applied_date, secured_date, notes', 'Rollout'],
    ['P1', 'Reporting tags', 'Globe Project Batch Tagging, ALLIANCE TAGGING', 'Add batch_tag and alliance_tag on rollout_programs', 'Rollout'],
    ['P1', 'Coloc tenant registry', 'MNO 2/3, Coloc Site ID/Name, RFTI, SL Remarks', 'Child rollouts + optional rollout_coloc_tenants summary table with remarks', 'Rollout'],
    ['P2', 'Split milestone dates', 'TSSR Submitted, Risk Build, Energization Tempo, RFTI Signed Tempo', 'Add dedicated date columns or extend milestone cycle with sub-checkpoints', 'Rollout Playbook'],
    ['P2', 'Permit applied vs secured', 'Locational, Building (partial today)', 'Extend permit tracker with applied_date + secured_date per type', 'Rollout'],
    ['P3', 'Engineering profile', 'Solution, Wind Speed', 'Add on site/tower engineering metadata', 'TowerOne / Sites'],
    ['', 'Architecture note', 'All coloc columns', 'Manual = flat columns; TowerOS = child rollouts via parent_rollout_id', 'Rollout'],
    ['', 'Terminology', 'RFTI / RFT', 'System uses actual_rfi_date (RFI = Ready for Integration)', 'Rollout'],
];

$phaseReferenceRows = [
    ['Manual Date Column', 'Primary System Target', 'Table / Phase Key', 'Field', 'Import Method'],
    ['Date Endorsed by Globe', 'Rollout header', 'rollout_programs', 'endorsement_date', 'PATCH rollout'],
    ['TSSR Submitted', 'Timeline phase', 'tssr_creation', 'actual_end_date', 'Bulk phase dates API'],
    ['TSSR Approved', 'Rollout header', 'rollout_programs', 'tssr_approved_date', 'POST .../tssr-approved'],
    ['MOC Secured', 'Milestone / phase', 'moc_securing / moc_col', 'actual_end_date', 'Bulk phase dates API'],
    ['Locational Clearance Applied', 'Milestone', 'locational_clearance', 'start (phase actual_start)', 'Bulk phase dates API'],
    ['Locational Clearance Secured', 'Milestone', 'locational_clearance', 'actual_end_date', 'Bulk phase dates API'],
    ['Building Permit Applied', 'Milestone', 'building_permit', 'start', 'Bulk phase dates API'],
    ['Building Permit Secured', 'Milestone', 'building_permit', 'actual_end_date', 'Bulk phase dates API'],
    ['Risk Build Declared Date', 'Timeline phase', 'permitting', 'actual_end_date', 'Bulk phase dates API or new field'],
    ['CW Start Date', 'Timeline / milestone', 'construction', 'actual_start_date', 'Bulk phase dates API'],
    ['CW Completed Date', 'Milestone', 'construction', 'actual_end_date', 'Bulk phase dates API'],
    ['Energization Tempo Date', 'Not available', '', '', 'Add new field'],
    ['Energization (Permanent)', 'Milestone', 'energization', 'actual_end_date', 'Bulk phase dates API'],
    ['RFTI Docs Submitted', 'Milestone', 'rfti_submission', 'target/actual dates', 'Bulk phase dates API'],
    ['RFTI Docs Signed (Tempo)', 'Not available', '', '', 'Add new field'],
    ['RFT Docs Signed (Permanent)', 'Rollout header', 'rollout_programs', 'actual_rfi_date', 'POST .../rfi-recorded'],
    ['SL Submitted', 'Milestone', 'site_license', 'start dates', 'Bulk phase dates API'],
    ['SL Signed', 'Rollout header', 'rollout_programs', 'site_license_executed_date', 'PATCH rollout'],
    ['RFTI Date (Coloc 2/3)', 'Child rollout', 'rollout_programs (child)', 'actual_rfi_date', 'Create child rollout + record RFI'],
];

$milestoneCycleRows = [
    ['phase_key', 'label', 'target_working_days (BTS)', 'Maps to Manual Tracker'],
    ['endorsement_to_hunting', 'Endorsement → Site Hunting Start', 1, 'Date Endorsed by Globe (indirect)'],
    ['site_hunting', 'Site Hunting (3 candidates)', 7, ''],
    ['pre_assessment', 'Pre-assessment Approval', 2, ''],
    ['tssr_creation', 'TSSR Creation + Internal Review', 2, 'TSSR Submitted (partial)'],
    ['tssr_mno_approval', 'TSSR Submission → MNO Approval', 9, 'TSSR Submitted → TSSR Approved'],
    ['moc_securing', 'MOC Securing', 8, 'MOC Secured'],
    ['col_social', 'COL + Social Acceptability', 8, ''],
    ['pre_construction', 'Pre-Construction Works', 7, ''],
    ['ddd', 'DDD', 5, ''],
    ['boq', 'BOQ', 2, ''],
    ['permit_prep', 'Permit Requirement Prep', 1, ''],
    ['locational_clearance', 'Locational/Zoning Clearance', 14, 'Locational Clearance Applied/Secured'],
    ['building_permit', 'Building Permit Application', 14, 'Building Permit Applied/Secured'],
    ['skom', 'SKOM / Mobilization', 1, ''],
    ['construction', 'Construction Phase', 44, 'CW Start / CW Completed'],
    ['energization', 'Energization', 15, 'Energization Tempo / Permanent'],
    ['rfti_submission', 'RFTI Submission', 7, 'RFTI Docs Submitted'],
    ['site_license', 'Site License', 7, 'SL Submitted / SL Signed (partial)'],
    ['billing', 'Billing', 2, ''],
];

$systemOnlyRows = [
    ['System Field', 'Module', 'Table', 'Description', 'In Manual Tracker?'],
    ['rollout_ref', 'Rollout', 'rollout_programs', 'Unique rollout reference', 'No'],
    ['endorsement_ref', 'Rollout', 'rollout_programs', 'MNO endorsement reference', 'No'],
    ['sla_working_days', 'Rollout', 'rollout_programs', 'SLA budget (working days)', 'No'],
    ['target_rfi_working_date', 'Rollout', 'rollout_programs', 'Computed target RFI date', 'No'],
    ['sla_variance_working_days', 'Rollout', 'rollout_programs', 'SLA variance', 'No'],
    ['doa_execution_date', 'Rollout', 'rollout_programs', 'RTB Day-1 trigger', 'No'],
    ['saq_owner_id / cme_pm_id / pmo_owner_id', 'Rollout', 'rollout_programs', 'Assigned owners', 'No'],
    ['project_id', 'Rollout', 'rollout_programs', 'Link to QMS project', 'No'],
    ['site_code', 'Sites', 'sites', 'Canonical site code', 'No'],
    ['gate_status / gate_label', 'Rollout Playbook', 'rollout_timeline_phases', 'Per-phase gate tracking', 'No'],
    ['site_candidates', 'SAQ', 'site_candidates', '≥3 hunting candidates with lease package', 'No'],
    ['cme_daily_reports', 'CME', 'cme_daily_reports', 'Daily construction progress', 'No'],
    ['site_profitability_records', 'Finance', 'site_profitability_records', 'VO, LD, profitability status', 'No'],
    ['rollout_gate_approval_requests', 'Governance', 'rollout_gate_approval_requests', 'Gate approval workflow', 'No'],
];

$scorecardRows = [
    ['Map Status', 'Count', 'Description'],
    ['Mapped', 12, 'Direct 1:1 field exists'],
    ['Partial', 18, 'Exists but different shape (phases, related entity, merged fields)'],
    ['Different Model', 8, 'Coloc columns → child rollouts'],
    ['Missing', 15, 'No system field today'],
    ['Total manual fields', 53, 'From manual site tracker screenshot'],
    ['', '', ''],
    ['Generated', date('Y-m-d H:i:s T'), 'TowerOS Project One field mapping', ''],
    ['Source', 'Manual site tracker (Excel) vs Project One + Rollout module', '', ''],
];

$writer = new ProcurementExcelWorkbookWriter();
$writer->addSheet('Field Mapping', $mappingRows);
$writer->addSheet('Gap Summary', $gapSummaryRows);
$writer->addSheet('Phase Reference', $phaseReferenceRows);
$writer->addSheet('Milestone Cycle', $milestoneCycleRows);
$writer->addSheet('System Only Fields', $systemOnlyRows);
$writer->addSheet('Scorecard', $scorecardRows);

$outputDir = __DIR__.'/../docs/exports';
if (! is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

$outputPath = $outputDir.'/project-one-manual-field-mapping.xlsx';
file_put_contents($outputPath, $writer->toBinaryString());

echo "Created: {$outputPath}\n";
echo 'Size: '.number_format(filesize($outputPath)).' bytes'."\n";
