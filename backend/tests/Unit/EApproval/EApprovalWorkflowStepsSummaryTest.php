<?php

declare(strict_types=1);

namespace Tests\Unit\EApproval;

use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Models\EApprovalWorkflowStep;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class EApprovalWorkflowStepsSummaryTest extends TestCase
{
    public function test_rejected_submission_marks_the_rejecting_step_instead_of_pending(): void
    {
        $submission = new EApprovalSubmission;
        $submission->status = 'rejected';
        $submission->current_step = 2;
        $submission->approval_cycle = 1;
        $submission->workflow_snapshot_json = json_encode([
            'steps' => [
                ['step_order' => 1, 'approver_type' => 'user', 'approver_id' => 'u1'],
                ['step_order' => 2, 'approver_type' => 'user', 'approver_id' => 'u2'],
                ['step_order' => 3, 'approver_type' => 'user', 'approver_id' => 'u3'],
                ['step_order' => 4, 'approver_type' => 'user', 'approver_id' => 'u4'],
            ],
        ], JSON_THROW_ON_ERROR);
        $submission->setRelation('approvals', new Collection([
            $this->approval(1, 'approved', 'Peter Joseph Cruz'),
            $this->approval(2, 'rejected', 'Denver Arquiza'),
        ]));

        $method = new ReflectionMethod(EApprovalSubmission::class, 'workflowStepsSummary');
        /** @var list<array<string, mixed>> $rows */
        $rows = $method->invoke($submission);

        $this->assertSame('completed', $rows[0]['state']);
        $this->assertSame('Approved', $rows[0]['status_label']);
        $this->assertSame('Peter Joseph Cruz', $rows[0]['approver_name']);

        $this->assertSame('rejected', $rows[1]['state']);
        $this->assertSame('Rejected', $rows[1]['status_label']);
        $this->assertSame('Denver Arquiza', $rows[1]['approver_name']);

        $this->assertSame('skipped', $rows[2]['state']);
        $this->assertSame('Not reached', $rows[2]['status_label']);
        $this->assertSame('skipped', $rows[3]['state']);
        $this->assertSame('Not reached', $rows[3]['status_label']);
    }

    private function approval(int $order, string $status, string $name): EApprovalRequestApproval
    {
        $step = new EApprovalWorkflowStep;
        $step->step_order = $order;

        $approver = new TenantUser;
        $approver->name = $name;

        $approval = new EApprovalRequestApproval;
        $approval->status = $status;
        $approval->approval_cycle = 1;
        $approval->setRelation('step', $step);
        $approval->setRelation('approver', $approver);

        return $approval;
    }
}
