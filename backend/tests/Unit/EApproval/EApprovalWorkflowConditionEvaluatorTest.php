<?php

declare(strict_types=1);

namespace Tests\Unit\EApproval;

use App\Modules\EApproval\Support\EApprovalWorkflowConditionEvaluator;
use PHPUnit\Framework\TestCase;

final class EApprovalWorkflowConditionEvaluatorTest extends TestCase
{
    private EApprovalWorkflowConditionEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new EApprovalWorkflowConditionEvaluator;
    }

    public function test_and_requires_every_condition(): void
    {
        $when = [
            ['field' => 'region', 'operator' => 'equals', 'value' => 'PH'],
            ['field' => 'amount', 'operator' => 'gt', 'value' => '1000'],
        ];

        $this->assertTrue($this->evaluator->matchesWhen($when, ['region' => 'PH', 'amount' => '2000'], 'and'));
        $this->assertFalse($this->evaluator->matchesWhen($when, ['region' => 'PH', 'amount' => '500'], 'and'));
    }

    public function test_or_accepts_any_condition(): void
    {
        $when = [
            ['field' => 'urgent', 'operator' => 'equals', 'value' => 'yes'],
            ['field' => 'amount', 'operator' => 'gt', 'value' => '5000'],
        ];

        $this->assertTrue($this->evaluator->matchesWhen($when, ['urgent' => 'yes', 'amount' => '10'], 'or'));
        $this->assertTrue($this->evaluator->matchesWhen($when, ['urgent' => 'no', 'amount' => '9000'], 'or'));
        $this->assertFalse($this->evaluator->matchesWhen($when, ['urgent' => 'no', 'amount' => '10'], 'or'));
    }

    public function test_stored_condition_honors_when_logic(): void
    {
        $condition = [
            'when_logic' => 'or',
            'when' => [
                ['field' => 'dept', 'operator' => 'equals', 'value' => 'IT'],
                ['field' => 'dept', 'operator' => 'equals', 'value' => 'HR'],
            ],
        ];

        $this->assertTrue($this->evaluator->matchesStoredCondition($condition, ['dept' => 'HR']));
        $this->assertFalse($this->evaluator->matchesStoredCondition($condition, ['dept' => 'FIN']));
    }

    public function test_empty_value_does_not_match_numeric_comparisons(): void
    {
        $lte = ['field' => 'non_po', 'operator' => 'lte', 'value' => '5000'];
        $gt = ['field' => 'non_po', 'operator' => 'gt', 'value' => '5000'];

        $this->assertFalse($this->evaluator->matches($lte, ['non_po' => '']));
        $this->assertFalse($this->evaluator->matches($gt, ['non_po' => '']));
        $this->assertFalse($this->evaluator->matches($lte, []));
        $this->assertTrue($this->evaluator->matches($lte, ['non_po' => '5000']));
        $this->assertTrue($this->evaluator->matches($gt, ['non_po' => '5001']));
    }
}
