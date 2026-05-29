<?php

declare(strict_types=1);

namespace App\Modules\ProjectOne\Services;

use App\Modules\ProjectOne\Models\Milestone;
use App\Modules\ProjectOne\Models\Project;
use App\Modules\ProjectOne\Models\ProjectApproval;
use App\Modules\Sites\Models\Site;
use Carbon\Carbon;

final class ProjectOneDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $activeProjects = Project::query()
            ->whereIn('status', ['planning', 'active', 'on_hold'])
            ->count();

        $pendingMilestones = Milestone::query()
            ->whereIn('status', ['pending', 'in_progress'])
            ->count();

        $overdueMilestones = Milestone::query()
            ->where(function ($q): void {
                $q->where('status', 'overdue')
                    ->orWhere(function ($q2): void {
                        $q2->whereNotIn('status', ['completed'])
                            ->whereNotNull('due_date')
                            ->whereDate('due_date', '<', Carbon::today());
                    });
            })
            ->count();

        $totalMilestones = Milestone::query()->count();
        $completedMilestones = Milestone::query()->where('status', 'completed')->count();
        $onTimePct = $totalMilestones > 0
            ? sprintf('%d%%', (int) round(($completedMilestones / $totalMilestones) * 100))
            : '—';

        $sites = Site::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('name')
            ->limit(100)
            ->get()
            ->map(fn (Site $s) => [
                'id' => (string) $s->id,
                'name' => $s->name,
                'lat' => (float) $s->latitude,
                'lng' => (float) $s->longitude,
                'status' => $this->siteHealth($s),
            ])
            ->values()
            ->all();

        $milestones = Milestone::query()
            ->with('project:id,name')
            ->orderBy('due_date')
            ->limit(25)
            ->get()
            ->map(fn (Milestone $m) => [
                'id' => (string) $m->id,
                'name' => $m->name,
                'targetDate' => $m->due_date?->toDateString() ?? '',
                'progressPercent' => $this->milestoneProgress($m),
                'status' => $this->milestoneUiBucket($m),
                'workflowStatus' => $m->status,
            ])
            ->values()
            ->all();

        $dueThisWeek = Milestone::query()
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->count();

        $pendingApprovals = ProjectApproval::query()
            ->where('status', 'pending')
            ->orderByDesc('submitted_at')
            ->limit(50)
            ->get()
            ->map(static fn (ProjectApproval $a) => $a->toDashboardApproval())
            ->values()
            ->all();

        $pendingApprovalCount = ProjectApproval::query()->where('status', 'pending')->count();

        return [
            'kpis' => [
                [
                    'key' => 'active_projects',
                    'label' => 'Active projects',
                    'value' => (string) $activeProjects,
                    'change' => 'Live from tenant DB',
                    'tone' => 'success',
                ],
                [
                    'key' => 'pending_milestones',
                    'label' => 'Open milestones',
                    'value' => (string) $pendingMilestones,
                    'change' => 'Pending or in progress',
                    'tone' => 'warning',
                ],
                [
                    'key' => 'sla_risk',
                    'label' => 'Milestone SLA risk',
                    'value' => (string) $overdueMilestones,
                    'change' => 'Overdue or past due',
                    'tone' => $overdueMilestones > 0 ? 'danger' : 'success',
                ],
                [
                    'key' => 'on_time',
                    'label' => 'Milestones completed',
                    'value' => $onTimePct,
                    'change' => $totalMilestones > 0 ? 'Share of all milestones' : 'No milestones yet',
                    'tone' => 'success',
                ],
                [
                    'key' => 'pending_approvals',
                    'label' => 'Pending approvals',
                    'value' => (string) $pendingApprovalCount,
                    'change' => 'Awaiting review',
                    'tone' => $pendingApprovalCount > 0 ? 'warning' : 'neutral',
                ],
            ],
            'sites' => $sites,
            'approvals' => $pendingApprovals,
            'milestones' => $milestones,
            'actions' => [
                [
                    'id' => 'ac-approvals',
                    'label' => 'Pending approvals',
                    'count' => $pendingApprovalCount,
                    'href' => '/project-one/approvals',
                    'priority' => $pendingApprovalCount > 0 ? 'high' : 'normal',
                ],
                [
                    'id' => 'ac-1',
                    'label' => 'Open milestones (action queue)',
                    'count' => $pendingMilestones,
                    'href' => '/project-one',
                    'priority' => $overdueMilestones > 0 ? 'high' : 'normal',
                ],
                [
                    'id' => 'ac-2',
                    'label' => 'Milestones due this week',
                    'count' => $dueThisWeek,
                    'href' => '/project-one',
                    'priority' => 'normal',
                ],
                [
                    'id' => 'ac-3',
                    'label' => 'Sites on map',
                    'count' => count($sites),
                    'href' => '/gis',
                    'priority' => 'normal',
                ],
            ],
        ];
    }

    private function siteHealth(Site $site): string
    {
        return match ($site->status) {
            'under_construction' => 'warning',
            'decommissioned' => 'critical',
            default => 'healthy',
        };
    }

    private function milestoneProgress(Milestone $m): int
    {
        return match ($m->status) {
            'completed' => 100,
            'in_progress' => 55,
            'overdue' => 25,
            default => 15,
        };
    }

    private function milestoneUiBucket(Milestone $m): string
    {
        if ($m->status === 'completed') {
            return 'on_track';
        }
        if ($m->status === 'overdue') {
            return 'blocked';
        }
        if ($m->due_date !== null && $m->due_date->lt(Carbon::today()) && $m->status !== 'completed') {
            return 'blocked';
        }
        if ($m->status === 'in_progress') {
            return 'on_track';
        }

        return 'at_risk';
    }
}
