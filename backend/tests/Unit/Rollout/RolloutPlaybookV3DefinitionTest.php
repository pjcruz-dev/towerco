<?php

declare(strict_types=1);

namespace Tests\Unit\Rollout;

use App\Modules\Rollout\Data\RolloutPlaybookMilestoneDeriver;
use App\Modules\Rollout\Data\RolloutPlaybookTemplateScaler;
use App\Modules\Rollout\Data\RolloutPlaybookV3Definition;
use Tests\TestCase;

final class RolloutPlaybookV3DefinitionTest extends TestCase
{
    public function test_bts_timeline_order_and_day_one_pivot(): void
    {
        $snapshot = RolloutPlaybookV3Definition::payload();
        $keys = array_column($snapshot['timeline_templates']['bts'], 'phase_key');

        $this->assertSame([
            'endorsement',
            'site_hunting',
            'pre_assessment',
            'moc_col',
            'tssr_creation',
            'tssr_mno_approval',
            'pre_construction',
            'permitting',
            'skom',
            'construction',
            'site_license',
            'handover_operations',
        ], $keys);

        $moc = collect($snapshot['timeline_templates']['bts'])->firstWhere('phase_key', 'moc_col');
        $this->assertSame('endorsement', $moc['anchor']);

        $this->assertSame('pre_construction', RolloutPlaybookMilestoneDeriver::postDayOneStartKey($snapshot, 'bts'));
    }

    public function test_derives_twenty_bts_milestone_rows_without_duplicates(): void
    {
        $snapshot = RolloutPlaybookV3Definition::payload();
        $rows = RolloutPlaybookMilestoneDeriver::deriveForProjectType($snapshot, 'bts');

        $this->assertCount(20, $rows);
        $this->assertCount(20, array_unique(array_column($rows, 'phase_key')));

        $preAssessment = collect($rows)->firstWhere('phase_key', 'pre_assessment');
        $this->assertSame('pre_assessment', $preAssessment['timeline_phase_key']);

        $siteLicense = collect($rows)->firstWhere('phase_key', 'site_license');
        $this->assertSame('site_license', $siteLicense['timeline_phase_key']);

        $handover = collect($rows)->firstWhere('phase_key', 'handover_operations');
        $this->assertSame('handover_operations', $handover['timeline_phase_key']);
    }

    public function test_post_day_one_sla_budget_excludes_closeout_phases(): void
    {
        $timeline = RolloutPlaybookV3Definition::btsTimelineV3();
        $slaSpan = 0;

        foreach ($timeline as $phase) {
            if (($phase['anchor'] ?? '') !== 'tssr_approved') {
                continue;
            }
            if (array_key_exists('counts_toward_sla', $phase) && ! (bool) $phase['counts_toward_sla']) {
                continue;
            }

            $start = (int) $phase['working_day_start'];
            $end = (int) $phase['working_day_end'];
            $slaSpan += max(0, $end - $start + 1);
        }

        $this->assertSame(115, $slaSpan);

        $scaled = RolloutPlaybookTemplateScaler::scalePostDayOneTimeline($timeline, 85);
        $scaledSpan = 0;
        foreach ($scaled as $phase) {
            if (($phase['anchor'] ?? '') !== 'tssr_approved') {
                continue;
            }
            if (array_key_exists('counts_toward_sla', $phase) && ! (bool) $phase['counts_toward_sla']) {
                continue;
            }
            $scaledSpan += max(0, (int) $phase['working_day_end'] - (int) $phase['working_day_start'] + 1);
        }

        $this->assertSame(85, $scaledSpan);
        $this->assertSame(122, collect($scaled)->firstWhere('phase_key', 'site_license')['working_day_end']);
    }
}
