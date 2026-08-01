<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Rollout\Data\RolloutPlaybookDefinitionRegistry;
use App\Modules\Rollout\Data\RolloutPlaybookV1Definition;
use App\Modules\Rollout\Data\RolloutPlaybookV2Definition;
use App\Modules\Rollout\Data\RolloutPlaybookV3Definition;
use PHPUnit\Framework\TestCase;

final class RolloutPlaybookDefinitionRegistryTest extends TestCase
{
    public function test_supported_versions_include_v1_v2_and_v3(): void
    {
        $versions = RolloutPlaybookDefinitionRegistry::supportedVersions();

        $this->assertContains(RolloutPlaybookV1Definition::VERSION, $versions);
        $this->assertContains(RolloutPlaybookV2Definition::VERSION, $versions);
        $this->assertContains(RolloutPlaybookV3Definition::VERSION, $versions);
    }

    public function test_v2_reduces_bts_sla_and_site_hunting_window(): void
    {
        $v1 = RolloutPlaybookDefinitionRegistry::payloadForVersion(RolloutPlaybookV1Definition::VERSION);
        $v2 = RolloutPlaybookDefinitionRegistry::payloadForVersion(RolloutPlaybookV2Definition::VERSION);

        $this->assertSame(120, $v1['delivery_periods']['bts']['working_days']);
        $this->assertSame(115, $v2['delivery_periods']['bts']['working_days']);
        $this->assertSame(85, $v2['delivery_periods']['rtb']['working_days']);

        $v1Hunting = collect($v1['timeline_templates']['bts'])->firstWhere('phase_key', 'site_hunting');
        $v2Hunting = collect($v2['timeline_templates']['bts'])->firstWhere('phase_key', 'site_hunting');

        $this->assertSame(8, $v1Hunting['working_day_end']);
        $this->assertSame(7, $v2Hunting['working_day_end']);

        $v2RtbConstruction = collect($v2['timeline_templates']['rtb'])->firstWhere('phase_key', 'construction');
        $this->assertNotNull($v2RtbConstruction);
        $this->assertSame(85, $v2RtbConstruction['working_day_end']);
    }

    public function test_v3_moves_moc_before_tssr_and_adds_closeout_phases(): void
    {
        $v3 = RolloutPlaybookDefinitionRegistry::payloadForVersion(RolloutPlaybookV3Definition::VERSION);
        $bts = collect($v3['timeline_templates']['bts']);

        $this->assertSame('endorsement', $bts->firstWhere('phase_key', 'moc_col')['anchor']);
        $this->assertNotNull($bts->firstWhere('phase_key', 'pre_assessment'));
        $this->assertNotNull($bts->firstWhere('phase_key', 'handover_operations'));
        $this->assertFalse($bts->firstWhere('phase_key', 'site_license')['counts_toward_sla']);
    }
}
