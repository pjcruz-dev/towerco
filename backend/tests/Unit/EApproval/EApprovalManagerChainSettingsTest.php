<?php

declare(strict_types=1);

namespace Tests\Unit\EApproval;

use App\Modules\EApproval\Support\EApprovalWorkflowStepDefinitionSupport;
use Tests\TestCase;

final class EApprovalManagerChainSettingsTest extends TestCase
{
    public function test_reporting_line_is_stored_on_a_manager_step_and_cleared_when_off(): void
    {
        $stored = EApprovalWorkflowStepDefinitionSupport::buildStoredCondition([
            'type' => 'manager',
            'manager_chain' => true,
            'manager_chain_stop_titles' => 'Director, Vice President',
            'manager_chain_max_levels' => 4,
        ], 'manager');

        $this->assertIsArray($stored);
        $this->assertTrue($stored['manager_chain']);
        $this->assertSame('Director, Vice President', $stored['manager_chain_stop_titles']);
        $this->assertSame(4, $stored['manager_chain_max_levels']);

        $definition = EApprovalWorkflowStepDefinitionSupport::withManagerChain([], ['condition' => $stored]);
        $this->assertTrue($definition['manager_chain']);
        $settings = EApprovalWorkflowStepDefinitionSupport::managerChainSettings($definition);
        $this->assertNotNull($settings);
        $this->assertSame(['Director', 'Vice President'], $settings['stop_titles']);

        $cleared = EApprovalWorkflowStepDefinitionSupport::buildStoredCondition([
            'type' => 'manager',
            'manager_chain' => false,
            'condition' => $stored,
        ], 'manager');
        $this->assertNull($cleared);
    }
}
