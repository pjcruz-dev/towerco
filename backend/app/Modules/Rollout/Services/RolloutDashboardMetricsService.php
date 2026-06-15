<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProjectOne\Models\Project;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\RolloutTimelinePhase;
use App\Modules\Tenancy\Support\TenantScopedCache;
use Illuminate\Support\Facades\Schema;

final class RolloutDashboardMetricsService
{
    public function __construct(
        private readonly RolloutSlaAtRiskService $slaAtRisk,
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

        $tenantId = (string) (tenant('id') ?? 'unknown');
        $userId = $viewer?->id ?? 'guest';

        return TenantScopedCache::remember(
            "project_one:rollout_dashboard:{$tenantId}:{$userId}",
            30,
            fn (): array => $this->buildUncached($viewer),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUncached(?TenantUser $viewer): array
    {
        $activeRollouts = RolloutProgram::query()
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();

        $awaitingDayOne = RolloutProgram::query()
            ->whereNull('tssr_approved_date')
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();

        $slaAtRisk = $this->slaAtRisk->count();

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
            ->select(['id'])
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
        $rows = RolloutProgram::query()
            ->selectRaw('project_id, COUNT(*) as active_rollouts')
            ->whereNotIn('status', ['completed', 'cancelled', 'batch'])
            ->whereNull('parent_rollout_id')
            ->groupBy('project_id')
            ->orderByDesc('active_rollouts')
            ->limit(8)
            ->get();

        $projectIds = $rows->pluck('project_id')->filter()->unique()->values()->all();
        $projectNames = $projectIds === [] || ! Schema::connection('tenant')->hasTable('projects')
            ? collect()
            : Project::query()->whereIn('id', $projectIds)->pluck('name', 'id');

        return $rows->map(static function ($row) use ($projectNames): array {
            $projectId = $row->project_id;

            return [
                'project_id' => $projectId,
                'project_name' => $projectId !== null
                    ? (string) ($projectNames[(string) $projectId] ?? 'Unassigned')
                    : 'Unassigned',
                'active_rollouts' => (int) $row->active_rollouts,
            ];
        })->values()->all();
    }
}
