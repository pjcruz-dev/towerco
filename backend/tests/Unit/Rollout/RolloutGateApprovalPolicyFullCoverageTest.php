<?php

declare(strict_types=1);

namespace Tests\Unit\Rollout;

use App\Modules\Rollout\Data\RolloutGateApprovalPolicyFullCoverage;
use App\Modules\Rollout\Data\RolloutPlaybookV2Definition;
use Tests\TestCase;

final class RolloutGateApprovalPolicyFullCoverageTest extends TestCase
{
    public function test_v2_coverage_enables_all_bts_phases(): void
    {
        $policies = RolloutGateApprovalPolicyFullCoverage::forPlaybookV2();
        $btsTimeline = RolloutPlaybookV2Definition::payload()['timeline_templates']['bts'];

        $this->assertCount(count($btsTimeline), $policies['bts']);

        foreach ($policies['bts'] as $phaseKey => $policy) {
            $this->assertTrue($policy['enabled'], "Phase {$phaseKey} should be enabled");
            $this->assertNotEmpty($policy['chain'], "Phase {$phaseKey} should have a chain");
        }
    }

    public function test_construction_chain_includes_tenant_admin(): void
    {
        $policies = RolloutGateApprovalPolicyFullCoverage::forPlaybookV2();

        $this->assertSame(['cme', 'pmo', 'tenant_admin'], $policies['bts']['construction']['chain']);
    }
}
