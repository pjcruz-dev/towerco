<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Data;

/**
 * TowerCo Rollout Playbook v3.0.0 — BTS process reorder:
 * Pre-assessment (MNO) after SAQ select; MOC/COL before TSSR;
 * Site License + Handover after RFI (outside delivery SLA).
 */
final class RolloutPlaybookV3Definition
{
    public const VERSION = '3.0.0';

    /**
     * @return array<string, mixed>
     */
    public static function payload(): array
    {
        $payload = RolloutPlaybookV2Definition::payload();
        $payload['version'] = self::VERSION;
        $payload['name'] = 'TowerCo Rollout Playbook v3';
        $payload['changelog'] = 'v3: BTS process alignment — Pre-assessment (MNO) after candidate select; '
            .'MOC/COL moved before TSSR (pre–Day-1); post–Day-1 starts at Pre-Construction; '
            .'Site License Processing + Handover to Operations after RFI (excluded from delivery SLA). '
            .'RTB inherits the same timeline shape scaled to 85 WD post–Day-1.';
        $payload['milestone_derived_from_timeline'] = true;

        $btsTimeline = self::btsTimelineV3();
        $payload['timeline_templates']['bts'] = $btsTimeline;
        $payload['timeline_templates']['rtb'] = RolloutPlaybookTemplateScaler::scalePostDayOneTimeline($btsTimeline, 85);

        $btsMilestones = self::btsCycleTargetsV3();
        $payload['milestone_cycle_targets'] = [
            'bts' => $btsMilestones,
            'rtb' => RolloutPlaybookTemplateScaler::scalePostMocCycleTargets(
                $btsMilestones,
                85,
                'pre_construction',
            ),
            'colocation' => $payload['milestone_cycle_targets']['colocation'],
        ];

        return $payload;
    }

    /**
     * Canonical milestone weights in process order (used when deriving from timeline spans).
     *
     * @return list<array<string, mixed>>
     */
    public static function btsCycleTargetsV3(): array
    {
        return [
            ['phase_key' => 'endorsement_to_hunting', 'label' => 'Endorsement → Site Hunting Start', 'target_working_days' => 1],
            ['phase_key' => 'site_hunting', 'label' => 'Site Hunting (3 candidates)', 'target_working_days' => 5],
            ['phase_key' => 'pre_assessment', 'label' => 'Pre-assessment Approval (MNO)', 'target_working_days' => 2],
            ['phase_key' => 'moc_securing', 'label' => 'MOC Securing', 'target_working_days' => 4],
            ['phase_key' => 'col_social', 'label' => 'COL + Social Acceptability', 'target_working_days' => 4],
            ['phase_key' => 'tssr_creation', 'label' => 'TSSR Creation + Internal Review', 'target_working_days' => 3],
            ['phase_key' => 'tssr_mno_approval', 'label' => 'TSSR Submission → MNO Approval', 'target_working_days' => 9],
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
            ['phase_key' => 'site_license', 'label' => 'Site License Processing', 'target_working_days' => 7],
            ['phase_key' => 'handover_operations', 'label' => 'Handover to Operations', 'target_working_days' => 2],
            ['phase_key' => 'billing', 'label' => 'Billing', 'target_working_days' => 2],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function btsTimelineV3(): array
    {
        return [
            [
                'phase_key' => 'endorsement',
                'label' => 'Endorsement & Planning',
                'owner_role' => 'bd_pmo',
                'anchor' => 'endorsement',
                'working_day_start' => 0,
                'working_day_end' => 0,
                'gate' => 'Site Tracker enrolment',
            ],
            [
                'phase_key' => 'site_hunting',
                'label' => 'Site Hunting',
                'owner_role' => 'saq',
                'anchor' => 'endorsement',
                'working_day_start' => 1,
                'working_day_end' => 5,
                'gate' => '≥3 candidates selected',
            ],
            [
                'phase_key' => 'pre_assessment',
                'label' => 'Pre-assessment Approval (MNO)',
                'owner_role' => 'mno',
                'anchor' => 'endorsement',
                'working_day_start' => 6,
                'working_day_end' => 7,
                'gate' => 'MNO pre-assessment pass',
            ],
            [
                'phase_key' => 'moc_col',
                'label' => 'MOC + COL Securing',
                'owner_role' => 'saq',
                'anchor' => 'endorsement',
                'working_day_start' => 8,
                'working_day_end' => 15,
                'gate' => 'eLAS IRR Pass',
            ],
            [
                'phase_key' => 'tssr_creation',
                'label' => 'TSSR Creation & Review',
                'owner_role' => 'saq_engineering',
                'anchor' => 'endorsement',
                'working_day_start' => 16,
                'working_day_end' => 18,
                'gate' => 'Engineering Approval',
            ],
            [
                'phase_key' => 'tssr_mno_approval',
                'label' => 'TSSR MNO Approval',
                'owner_role' => 'mno',
                'anchor' => 'endorsement',
                'working_day_start' => 19,
                'working_day_end' => 27,
                'gate' => 'DAY 1 OF DELIVERY PERIOD',
            ],
            [
                'phase_key' => 'pre_construction',
                'label' => 'Pre-Construction',
                'owner_role' => 'engineering',
                'anchor' => 'tssr_approved',
                'working_day_start' => 1,
                'working_day_end' => 17,
                'gate' => 'VO Approval',
            ],
            [
                'phase_key' => 'permitting',
                'label' => 'Permitting',
                'owner_role' => 'saq',
                'anchor' => 'tssr_approved',
                'working_day_start' => 18,
                'working_day_end' => 37,
                'gate' => 'Risk Build gate',
            ],
            [
                'phase_key' => 'skom',
                'label' => 'SKOM / Mobilization',
                'owner_role' => 'cme',
                'anchor' => 'tssr_approved',
                'working_day_start' => 38,
                'working_day_end' => 38,
                'gate' => 'CSHP DOLE acknowledgement',
            ],
            [
                'phase_key' => 'construction',
                'label' => 'Construction + Energization',
                'owner_role' => 'cme_power',
                'anchor' => 'tssr_approved',
                'working_day_start' => 39,
                'working_day_end' => 115,
                'gate' => 'RFI Certificate',
            ],
            [
                'phase_key' => 'site_license',
                'label' => 'Site License Processing',
                'owner_role' => 'saq',
                'anchor' => 'tssr_approved',
                'working_day_start' => 116,
                'working_day_end' => 122,
                'gate' => 'Site license secured',
                'counts_toward_sla' => false,
            ],
            [
                'phase_key' => 'handover_operations',
                'label' => 'Handover to Operations',
                'owner_role' => 'bd_pmo',
                'anchor' => 'tssr_approved',
                'working_day_start' => 123,
                'working_day_end' => 124,
                'gate' => 'Ops acceptance',
                'counts_toward_sla' => false,
            ],
        ];
    }
}
