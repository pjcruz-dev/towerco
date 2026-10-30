<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Support\EApprovalApprovalStatus;
use App\Modules\EApproval\Support\EApprovalReviewStep;
use App\Modules\EApproval\Support\EApprovalWorkflowStepDefinitionSupport;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Approves a pending person when their step's day count has elapsed.
 *
 * Any one: the first due person finishes the band.
 * All: each person is approved on their own clock, and the band waits for everyone.
 * At least N: each auto-approval counts toward N.
 *
 * A step that releases cash, records a settlement, or only reviews stays manual.
 */
final class EApprovalAutoApproveService
{
    public function __construct(
        private readonly ApprovalDecisionService $decisions,
        private readonly EApprovalCommentService $comments,
    ) {}

    public function run(): int
    {
        $approved = 0;
        $now = Carbon::now();

        $pending = EApprovalRequestApproval::query()
            ->where('status', EApprovalApprovalStatus::PENDING)
            ->with(['step', 'approver', 'submission'])
            ->orderBy('created_at')
            ->get();

        foreach ($pending as $approval) {
            if ($this->approveIfDue($approval, $now)) {
                $approved++;
            }
        }

        return $approved;
    }

    private function approveIfDue(EApprovalRequestApproval $approval, Carbon $now): bool
    {
        $step = $approval->step;
        $condition = is_array($step?->condition) ? $step->condition : [];
        $days = EApprovalWorkflowStepDefinitionSupport::autoApproveDays([], $condition);
        if ($days === null || EApprovalReviewStep::isReviewStep($step)) {
            return false;
        }

        $action = strtolower(trim((string) ($condition['finance_action'] ?? '')));
        if (in_array($action, ['release', 'settlement', 'payment'], true)) {
            return false;
        }
        if (EApprovalWorkflowStepDefinitionSupport::proofOfPaymentEnabled($condition)) {
            return false;
        }

        if (! EApprovalWorkflowStepDefinitionSupport::approvalIsDue($approval->created_at, $days, $now)) {
            return false;
        }

        $actor = $approval->approver;
        if (! $actor instanceof TenantUser || $actor->is_active === false) {
            return false;
        }

        $submission = $approval->submission;
        if ($submission === null) {
            return false;
        }

        $note = __('Auto-approved after :days working day(s) with no decision.', ['days' => $days]);

        try {
            $this->decisions->decide($approval, 'approved', $note, null, $actor);
        } catch (ValidationException) {
            return false;
        }

        $this->comments->add($submission, $note, $actor, notifyStakeholders: true);

        return true;
    }
}
