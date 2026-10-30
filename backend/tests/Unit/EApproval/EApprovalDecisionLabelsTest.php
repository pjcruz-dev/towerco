<?php

declare(strict_types=1);

namespace Tests\Unit\EApproval;

use App\Modules\EApproval\Support\EApprovalWorkflowStepDefinitionSupport;
use Tests\TestCase;

final class EApprovalDecisionLabelsTest extends TestCase
{
    public function test_a_step_keeps_approve_as_validated(): void
    {
        $stored = EApprovalWorkflowStepDefinitionSupport::buildStoredCondition([
            'type' => 'user',
            'approverId' => 'denver',
            'decision_labels' => [
                'approve' => 'Validated',
                'reject' => '',
                'revision' => 'Request revision',
            ],
        ], 'user');

        $this->assertIsArray($stored);
        $this->assertSame('Validated', $stored['decision_labels']['approve']);
        $this->assertSame('Reject', $stored['decision_labels']['reject']);

        $labels = EApprovalWorkflowStepDefinitionSupport::decisionLabels(['condition' => $stored]);
        $this->assertSame('Validated', $labels['approve']);
    }

    public function test_default_words_are_not_stored(): void
    {
        $stored = EApprovalWorkflowStepDefinitionSupport::buildStoredCondition([
            'type' => 'user',
            'approverId' => 'denver',
            'decision_labels' => [
                'approve' => 'Approve',
                'reject' => 'Reject',
                'revision' => 'Request revision',
            ],
        ], 'user');

        $this->assertTrue($stored === null || ! array_key_exists('decision_labels', $stored));
    }

    public function test_a_review_step_does_not_keep_decision_words(): void
    {
        $stored = EApprovalWorkflowStepDefinitionSupport::buildStoredCondition([
            'type' => 'user',
            'duty' => 'review',
            'decision_labels' => ['approve' => 'Validated'],
        ], 'user');

        $this->assertIsArray($stored);
        $this->assertArrayNotHasKey('decision_labels', $stored);
    }
}
