<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Data;

/**
 * TowerCo Rollout Playbook v1.0.0 — working-day SLA targets (§1.2, §1.4, §9.3).
 */
final class RolloutPlaybookV1Definition
{
    public const VERSION = '1.0.0';

    /**
     * @return array<string, mixed>
     */
    public static function payload(): array
    {
        return [
            'version' => self::VERSION,
            'name' => 'TowerCo Rollout Playbook v1',
            'status' => 'published',
            'sla_working_days_only' => true,
            'delivery_periods' => [
                'bts' => ['working_days' => 120, 'day_one_trigger' => 'tssr_approved'],
                'rtb' => ['working_days' => 85, 'day_one_trigger' => 'doa_execution_plus_15wd'],
                'colocation' => ['working_days' => 30, 'day_one_trigger' => 'site_license_executed'],
            ],
            'milestone_cycle_targets' => [
                'bts' => self::btsCycleTargets(),
                'rtb' => RolloutPlaybookTemplateScaler::scalePostMocCycleTargets(self::btsCycleTargets(), 85),
                'colocation' => self::colocationCycleTargets(),
            ],
            'timeline_templates' => [
                'bts' => self::btsTimeline(),
                'rtb' => self::rtbTimeline(),
                'colocation' => self::colocationTimeline(),
            ],
            'form_schemas' => [
                'site_candidate' => ['version' => 1],
                'site_hunting_daily_log' => ['version' => 1],
                'cme_daily_report' => ['version' => 1],
                'site_profitability' => ['version' => 1],
            ],
            'changelog' => 'Initial publish aligned to TowerCo_Rollout_Playbook_v1.docx. All SLA counts use working days (Mon–Fri).',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function btsCycleTargets(): array
    {
        return [
            ['phase_key' => 'endorsement_to_hunting', 'label' => 'Endorsement → Site Hunting Start', 'target_working_days' => 1],
            ['phase_key' => 'site_hunting', 'label' => 'Site Hunting (3 candidates)', 'target_working_days' => 7],
            ['phase_key' => 'pre_assessment', 'label' => 'Pre-assessment Approval', 'target_working_days' => 2],
            ['phase_key' => 'tssr_creation', 'label' => 'TSSR Creation + Internal Review', 'target_working_days' => 2],
            ['phase_key' => 'tssr_mno_approval', 'label' => 'TSSR Submission → MNO Approval', 'target_working_days' => 9],
            ['phase_key' => 'moc_securing', 'label' => 'MOC Securing', 'target_working_days' => 8],
            ['phase_key' => 'col_social', 'label' => 'COL + Social Acceptability', 'target_working_days' => 8],
            ['phase_key' => 'pre_construction', 'label' => 'Pre-Construction Works', 'target_working_days' => 7],
            ['phase_key' => 'ddd', 'label' => 'DDD', 'target_working_days' => 5],
            ['phase_key' => 'boq', 'label' => 'BOQ', 'target_working_days' => 2],
            ['phase_key' => 'permit_prep', 'label' => 'Permit Requirement Prep', 'target_working_days' => 1],
            ['phase_key' => 'locational_clearance', 'label' => 'Locational/Zoning Clearance', 'target_working_days' => 14],
            ['phase_key' => 'building_permit', 'label' => 'Building Permit Application', 'target_working_days' => 14],
            ['phase_key' => 'skom', 'label' => 'SKOM / Mobilization', 'target_working_days' => 1],
            ['phase_key' => 'construction', 'label' => 'Construction Phase', 'target_working_days' => 44],
            ['phase_key' => 'energization', 'label' => 'Energization', 'target_working_days' => 15],
            ['phase_key' => 'rfti_submission', 'label' => 'RFTI Submission', 'target_working_days' => 7],
            ['phase_key' => 'site_license', 'label' => 'Site License', 'target_working_days' => 7],
            ['phase_key' => 'billing', 'label' => 'Billing', 'target_working_days' => 2],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function btsTimeline(): array
    {
        return [
            ['phase_key' => 'endorsement', 'label' => 'Endorsement & Planning', 'owner_role' => 'bd_pmo', 'anchor' => 'endorsement', 'working_day_start' => 0, 'working_day_end' => 0, 'gate' => 'Site Tracker enrolment'],
            ['phase_key' => 'site_hunting', 'label' => 'Site Hunting', 'owner_role' => 'saq', 'anchor' => 'endorsement', 'working_day_start' => 1, 'working_day_end' => 8, 'gate' => '≥3 candidates'],
            ['phase_key' => 'tssr_creation', 'label' => 'TSSR Creation & Review', 'owner_role' => 'saq_engineering', 'anchor' => 'endorsement', 'working_day_start' => 9, 'working_day_end' => 11, 'gate' => 'Engineering Approval'],
            ['phase_key' => 'tssr_mno_approval', 'label' => 'TSSR MNO Approval', 'owner_role' => 'mno', 'anchor' => 'endorsement', 'working_day_start' => 12, 'working_day_end' => 20, 'gate' => 'DAY 1 OF DELIVERY PERIOD'],
            ['phase_key' => 'moc_col', 'label' => 'MOC + COL Securing', 'owner_role' => 'saq', 'anchor' => 'tssr_approved', 'working_day_start' => 1, 'working_day_end' => 8, 'gate' => 'eLAS IRR Pass'],
            ['phase_key' => 'pre_construction', 'label' => 'Pre-Construction', 'owner_role' => 'engineering', 'anchor' => 'tssr_approved', 'working_day_start' => 9, 'working_day_end' => 25, 'gate' => 'VO Approval'],
            ['phase_key' => 'permitting', 'label' => 'Permitting', 'owner_role' => 'saq', 'anchor' => 'tssr_approved', 'working_day_start' => 26, 'working_day_end' => 45, 'gate' => 'Risk Build gate'],
            ['phase_key' => 'skom', 'label' => 'SKOM / Mobilization', 'owner_role' => 'cme', 'anchor' => 'tssr_approved', 'working_day_start' => 46, 'working_day_end' => 46, 'gate' => 'CSHP DOLE acknowledgement'],
            ['phase_key' => 'construction', 'label' => 'Construction + Energization', 'owner_role' => 'cme_power', 'anchor' => 'tssr_approved', 'working_day_start' => 47, 'working_day_end' => 120, 'gate' => 'RFI Certificate'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rtbTimeline(): array
    {
        return RolloutPlaybookTemplateScaler::scalePostDayOneTimeline(self::btsTimeline(), 85);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function colocationCycleTargets(): array
    {
        return [
            ['phase_key' => 'site_license', 'label' => 'Site License Execution', 'target_working_days' => 1],
            ['phase_key' => 'implementation', 'label' => 'Colocation Implementation', 'target_working_days' => 27],
            ['phase_key' => 'billing', 'label' => 'Billing', 'target_working_days' => 2],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function colocationTimeline(): array
    {
        return [
            ['phase_key' => 'site_license', 'label' => 'Site License Execution', 'owner_role' => 'bd', 'anchor' => 'tssr_approved', 'working_day_start' => 0, 'working_day_end' => 0, 'gate' => 'DAY 1'],
            ['phase_key' => 'implementation', 'label' => 'Colocation Implementation', 'owner_role' => 'cme', 'anchor' => 'tssr_approved', 'working_day_start' => 1, 'working_day_end' => 29, 'gate' => 'RFI Certificate'],
        ];
    }
}
