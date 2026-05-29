<?php

declare(strict_types=1);

namespace Tests\Unit\Rollout;

use App\Modules\Rollout\Data\RolloutPlaybookTemplateScaler;
use App\Modules\Rollout\Data\RolloutPlaybookV1Definition;
use App\Modules\Rollout\Data\RolloutPlaybookV2Definition;
use PHPUnit\Framework\TestCase;

final class RolloutPlaybookTemplateScalerTest extends TestCase
{
    public function test_rtb_timeline_post_day_one_ends_at_eighty_five_in_v2(): void
    {
        $rtbTimeline = RolloutPlaybookV2Definition::payload()['timeline_templates']['rtb'];
        $construction = collect($rtbTimeline)->firstWhere('phase_key', 'construction');

        $this->assertNotNull($construction);
        $this->assertSame(85, $construction['working_day_end']);
    }

    public function test_rtb_milestones_post_moc_sum_to_eighty_five_in_v2(): void
    {
        $targets = RolloutPlaybookV2Definition::payload()['milestone_cycle_targets']['rtb'];
        $postMoc = false;
        $postSum = 0;

        foreach ($targets as $target) {
            if (($target['phase_key'] ?? '') === 'moc_securing') {
                $postMoc = true;
            }

            if ($postMoc) {
                $postSum += (int) $target['target_working_days'];
            }
        }

        $this->assertSame(85, $postSum);
    }

    public function test_colocation_milestones_sum_to_thirty(): void
    {
        $targets = RolloutPlaybookV1Definition::payload()['milestone_cycle_targets']['colocation'];
        $sum = array_sum(array_map(static fn (array $row): int => (int) $row['target_working_days'], $targets));

        $this->assertCount(3, $targets);
        $this->assertSame(30, $sum);
    }

    public function test_scale_post_day_one_timeline_preserves_pre_day_one_phases(): void
    {
        $bts = RolloutPlaybookV1Definition::payload()['timeline_templates']['bts'];
        $scaled = RolloutPlaybookTemplateScaler::scalePostDayOneTimeline($bts, 85);

        $this->assertSame(
            collect($bts)->firstWhere('phase_key', 'site_hunting'),
            collect($scaled)->firstWhere('phase_key', 'site_hunting'),
        );
    }
}
