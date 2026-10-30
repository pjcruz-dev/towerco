<?php

declare(strict_types=1);

namespace Tests\Unit\EApproval;

use App\Modules\EApproval\Support\EApprovalWorkflowStepDefinitionSupport;
use Tests\TestCase;

final class EApprovalStepWhenScopeTest extends TestCase
{
    public function test_a_step_condition_stays_marked_as_that_step(): void
    {
        $stored = EApprovalWorkflowStepDefinitionSupport::buildStoredCondition([
            'when_scope' => 'step',
            'when' => [
                ['field' => 'amount', 'operator' => 'gt', 'value' => '1000'],
            ],
        ], 'user');

        $this->assertIsArray($stored);
        $this->assertSame('step', $stored['when_scope']);

        $definition = EApprovalWorkflowStepDefinitionSupport::normalizePayloadStep([
            'type' => 'user',
            'approverId' => 'denver',
            'when' => [
                ['field' => 'amount', 'operator' => 'gt', 'value' => '1000'],
            ],
            'condition' => ['when_scope' => 'step'],
        ], 4);

        $this->assertSame('step', $definition['when_scope']);
    }

    public function test_an_if_else_case_does_not_keep_a_step_scope(): void
    {
        $stored = EApprovalWorkflowStepDefinitionSupport::buildStoredCondition([
            'when' => [
                ['field' => 'subsidiary', 'operator' => 'equals', 'value' => 'ATC'],
            ],
            'condition' => ['when_scope' => 'branch'],
        ], 'user');

        $this->assertIsArray($stored);
        $this->assertArrayNotHasKey('when_scope', $stored);
    }

    public function test_clearing_conditions_drops_the_step_scope(): void
    {
        $stored = EApprovalWorkflowStepDefinitionSupport::buildStoredCondition([
            'when_scope' => 'step',
            'when' => [],
            'condition' => [
                'when_scope' => 'step',
                'fallback_approver_id' => 'peter',
            ],
        ], 'user');

        $this->assertIsArray($stored);
        $this->assertArrayNotHasKey('when_scope', $stored);
        $this->assertSame('peter', $stored['fallback_approver_id']);
    }
}
