<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\RolloutTimelinePhase;
use App\Modules\Rollout\Support\TenantWorkingDaysCalendarFactory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

final class RolloutDashboardMetricsService
{
    public function __construct(
        private readonly TenantWorkingDaysCalendarFactory $calendarFactory,
        private readonly RolloutGateApprovalService $gateApprovals,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function build(?TenantUser $viewer = null): ?array
    {
        if (! Schema::connection('tenant')->hasTable('rollout_programs')) {
            return null;
        }

        $today = Carbon::today();

        $activeRollouts = RolloutProgram::query()
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();

        $awaitingDayOne = RolloutProgram::query()
            ->whereNull('tssr_approved_date')
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();

        $slaAtRisk = RolloutProgram::query()
            ->whereNotNull('target_rfi_working_date')
            ->whereNull('actual_rfi_date')
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->get()
            ->filter(function (RolloutProgram $program) use ($today): bool {
                $target = $program->target_rfi_working_date;
                if ($target === null) {
                    return false;
                }

                $remaining = $this->calendarFactory
                    ->make($program->region)
                    ->workingDaysBetween($today, Carbon::parse($target));

                return $remaining <= 10;
            })
            ->count();

        $recentRollouts = RolloutProgram::query()
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->map(static fn (RolloutProgram $p) => [
                'id' => $p->id,
                'rollout_ref' => $p->rollout_ref,
                'status' => $p->status,
                'mno' => $p->mno,
                'target_rfi_working_date' => $p->target_rfi_working_date?->toDateString(),
            ])
            ->values()
            ->all();

        return [
            'active_rollouts' => $activeRollouts,
            'awaiting_day_one' => $awaitingDayOne,
            'sla_at_risk' => $slaAtRisk,
            'pending_gates' => $this->pendingGatesCount(),
            'gate_approvals_in_review' => $this->gateApprovalsInReviewCount(),
            'gate_approvals_awaiting_me' => $this->gateApprovals->countAwaitingUser($viewer),
            'gate_approvals_preview' => $this->gateApprovals->previewAwaitingUser($viewer, 5),
            'open_saq_programs' => $this->openSaqProgramsCount(),
            'recent_rollouts' => $recentRollouts,
            'active_rollouts_by_project' => $this->activeRolloutsByProject(),
        ];
    }

    private function gateApprovalsInReviewCount(): int
    {
        if (! Schema::connection('tenant')->hasTable('rollout_gate_approval_requests')) {
            return 0;
        }

        return \App\Modules\Rollout\Models\RolloutGateApprovalRequest::query()
            ->where('status', \App\Modules\Rollout\Models\RolloutGateApprovalRequest::STATUS_IN_REVIEW)
            ->count();
    }

    private function pendingGatesCount(): int
    {
        if (! Schema::connection('tenant')->hasTable('rollout_timeline_phases')) {
            return 0;
        }

        return RolloutTimelinePhase::query()
            ->where('gate_status', 'pending')
            ->whereHas('rolloutProgram', static function ($query): void {
                $query->whereNotIn('status', ['completed', 'cancelled', 'batch']);
            })
            ->count();
    }

    private function openSaqProgramsCount(): int
    {
        return RolloutProgram::query()
            ->whereNotIn('status', ['completed', 'cancelled', 'batch'])
            ->whereNull('parent_rollout_id')
            ->where('status', 'saq')
            ->withCount('candidates')
            ->get()
            ->filter(static fn (RolloutProgram $program): bool => $program->candidates_count < 3)
            ->count();
    }

    /**
     * @return list<array{project_id: string|null, project_name: string, active_rollouts: int}>
     */
    private function activeRolloutsByProject(): array
    {
        return RolloutProgram::query()
            ->selectRaw('project_id, COUNT(*) as active_rollouts')
            ->whereNotIn('status', ['completed', 'cancelled', 'batch'])
            ->whereNull('parent_rollout_id')
            ->groupBy('project_id')
            ->orderByDesc('active_rollouts')
            ->limit(8)
            ->get()
            ->map(function ($row) {
                $project = $row->project_id
                    ? \App\Modules\ProjectOne\Models\Project::query()->find($row->project_id)
                    : null;

                return [
                    'project_id' => $row->project_id,
                    'project_name' => $project?->name ?? 'Unassigned',
                    'active_rollouts' => (int) $row->active_rollouts,
                ];
            })
            ->values()
            ->all();
    }
}
