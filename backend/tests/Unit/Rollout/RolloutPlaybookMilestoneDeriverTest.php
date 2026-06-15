<?php

declare(strict_types=1);

namespace Tests\Unit\Rollout;

use App\Modules\Rollout\Data\RolloutPlaybookMilestoneDeriver;
use App\Modules\Rollout\Data\RolloutPlaybookMilestoneResolver;
use App\Modules\Rollout\Data\RolloutPlaybookV2Definition;
use Tests\TestCase;

final class RolloutPlaybookMilestoneDeriverTest extends TestCase
{
    public function test_derives_nineteen_bts_rows_from_timeline(): void
    {
        $snapshot = RolloutPlaybookV2Definition::payload();
        $rows = RolloutPlaybookMilestoneDeriver::deriveForProjectType($snapshot, 'bts');

        $this->assertCount(19, $rows);
        $this->assertSame('moc_securing', RolloutPlaybookMilestoneDeriver::postDayOneStartKey($snapshot, 'bts'));
    }

    public function test_site_hunting_group_matches_timeline_window(): void
    {
        $snapshot = RolloutPlaybookV2Definition::payload();
        $rows = RolloutPlaybookMilestoneDeriver::deriveForProjectType($snapshot, 'bts');

        $siteHunting = collect($rows)->firstWhere('phase_key', 'site_hunting');
        $preAssessment = collect($rows)->firstWhere('phase_key', 'pre_assessment');

        $this->assertNotNull($siteHunting);
        $this->assertNotNull($preAssessment);
        $this->assertSame('site_hunting', $siteHunting['timeline_phase_key']);
        $this->assertSame(7, (int) $siteHunting['target_working_days'] + (int) $preAssessment['target_working_days']);
    }

    public function test_custom_timeline_phase_becomes_milestone_row(): void
    {
        $snapshot = RolloutPlaybookV2Definition::payload();
        $snapshot['timeline_templates']['bts'][] = [
            'phase_key' => 'lgu_clearance',
            'label' => 'LGU Clearance',
            'owner_role' => 'saq',
            'anchor' => 'tssr_approved',
            'working_day_start' => 9,
            'working_day_end' => 12,
            'sort_order' => 45,
            'is_custom' => true,
            'counts_toward_sla' => true,
        ];

        usort($snapshot['timeline_templates']['bts'], static fn (array $a, array $b): int => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

        $rows = RolloutPlaybookMilestoneDeriver::deriveForProjectType($snapshot, 'bts');

        $custom = collect($rows)->firstWhere('phase_key', 'lgu_clearance');
        $this->assertNotNull($custom);
        $this->assertTrue($custom['is_custom']);
        $this->assertSame(4, $custom['target_working_days']);
        $this->assertSame('moc_securing', RolloutPlaybookMilestoneDeriver::postDayOneStartKey($snapshot, 'bts'));
    }

    public function test_colocation_rows_sum_to_delivery_period(): void
    {
        $snapshot = RolloutPlaybookV2Definition::payload();
        $rows = RolloutPlaybookMilestoneDeriver::deriveForProjectType($snapshot, 'colocation');

        $this->assertCount(3, $rows);
        $this->assertSame(30, array_sum(array_column($rows, 'target_working_days')));
    }

    public function test_resolver_prefers_derived_targets_when_timeline_present(): void
    {
        $snapshot = RolloutPlaybookV2Definition::payload();

        $this->assertTrue(RolloutPlaybookMilestoneResolver::shouldDeriveFromTimeline($snapshot));
        $this->assertCount(19, RolloutPlaybookMilestoneResolver::targetsForProjectType($snapshot, 'bts'));
    }

    public function test_first_tssr_approved_custom_phase_becomes_post_day_one_pivot(): void
    {
        $snapshot = RolloutPlaybookV2Definition::payload();
        $timeline = $snapshot['timeline_templates']['bts'];

        $insertAt = null;
        foreach ($timeline as $index => $phase) {
            if (($phase['phase_key'] ?? '') === 'moc_col') {
                $insertAt = $index;
                break;
            }
        }

        $this->assertNotNull($insertAt);

        $custom = [
            'phase_key' => 'lgu_clearance',
            'label' => 'LGU Clearance',
            'owner_role' => 'saq',
            'anchor' => 'tssr_approved',
            'working_day_start' => 1,
            'working_day_end' => 4,
            'is_custom' => true,
            'counts_toward_sla' => true,
        ];

        array_splice($timeline, $insertAt, 0, [$custom]);
        $snapshot['timeline_templates']['bts'] = $timeline;

        $this->assertSame('lgu_clearance', RolloutPlaybookMilestoneDeriver::postDayOneStartKey($snapshot, 'bts'));

        $rows = RolloutPlaybookMilestoneDeriver::deriveForProjectType($snapshot, 'bts');
        $this->assertCount(20, $rows);

        $customRow = collect($rows)->firstWhere('phase_key', 'lgu_clearance');
        $this->assertNotNull($customRow);
        $this->assertTrue($customRow['is_custom']);
    }
}
