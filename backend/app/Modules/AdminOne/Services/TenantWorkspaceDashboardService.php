<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Services;

use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalApprovalStatus;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Notifications\Models\TenantNotification;
use App\Modules\Notifications\Services\TenantNotificationService;
use App\Modules\Notifications\Support\TenantNotificationAccess;
use App\Modules\Rollout\Services\RolloutDashboardMetricsService;
use App\Modules\Sites\Models\Site;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class TenantWorkspaceDashboardService
{
    private const STALE_APPROVAL_DAYS = 3;

    public function __construct(
        private readonly TenantNotificationService $notifications,
        private readonly RolloutDashboardMetricsService $rolloutMetrics,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(TenantUser $user): array
    {
        $tenantId = (string) (tenant('id') ?? 'unknown');

        return Cache::remember(
            "workspace:dashboard:{$tenantId}:{$user->id}",
            30,
            fn (): array => $this->buildUncached($user),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUncached(TenantUser $user): array
    {
        $kpis = [];
        $actions = [];
        $recentActivity = [];

        $notificationModules = TenantNotificationAccess::allowedModulesFor($user);
        $unreadNotifications = $notificationModules !== []
            ? $this->notifications->unreadCount((string) $user->id, $notificationModules)
            : 0;

        if ($notificationModules !== []) {
            $kpis[] = [
                'key' => 'unread_notifications',
                'label' => 'Unread notifications',
                'value' => (string) $unreadNotifications,
                'change' => 'Across enabled modules',
                'tone' => $unreadNotifications > 0 ? 'warning' : 'success',
            ];

            if ($unreadNotifications > 0) {
                $actions[] = [
                    'id' => 'ws-notifications',
                    'label' => 'Unread notifications',
                    'count' => $unreadNotifications,
                    'href' => '/notifications?unread_only=1',
                    'priority' => 'high',
                ];
            }

            $recentActivity = array_merge(
                $recentActivity,
                $this->recentNotifications((string) $user->id, $notificationModules),
            );
        }

        if ($user->can('e_approval:view')) {
            $awaitingMyApproval = 0;
            if ($user->can('e_approval:approve')) {
                $awaitingMyApproval = (int) DB::table('e_approval_request_approvals')
                    ->where('approver_id', $user->id)
                    ->where('status', EApprovalApprovalStatus::PENDING)
                    ->count();
            }

            $openSubmissions = EApprovalSubmission::query()
                ->whereNotIn('status', [
                    EApprovalSubmissionStatus::APPROVED,
                    EApprovalSubmissionStatus::REJECTED,
                    EApprovalSubmissionStatus::CANCELLED,
                ])
                ->count();

            $staleApprovals = EApprovalRequestApproval::query()
                ->where('status', EApprovalApprovalStatus::PENDING)
                ->where('created_at', '<=', Carbon::now()->subDays(self::STALE_APPROVAL_DAYS))
                ->count();

            if ($awaitingMyApproval > 0) {
                $kpis[] = [
                    'key' => 'ea_awaiting_my_approval',
                    'label' => 'E-Approval · awaiting you',
                    'value' => (string) $awaitingMyApproval,
                    'change' => 'Assigned approval steps',
                    'tone' => 'danger',
                ];
                $actions[] = [
                    'id' => 'ws-ea-awaiting',
                    'label' => 'E-Approval awaiting you',
                    'count' => $awaitingMyApproval,
                    'href' => '/e-approval/approvals?awaiting_me=1',
                    'priority' => 'high',
                ];
            }

            if ($staleApprovals > 0) {
                $kpis[] = [
                    'key' => 'ea_stale_approvals',
                    'label' => 'E-Approval · stale steps',
                    'value' => (string) $staleApprovals,
                    'change' => '>'.self::STALE_APPROVAL_DAYS.' days pending',
                    'tone' => 'danger',
                ];
            }

            if ($openSubmissions > 0 && $awaitingMyApproval === 0) {
                $kpis[] = [
                    'key' => 'ea_open_submissions',
                    'label' => 'E-Approval · open submissions',
                    'value' => (string) $openSubmissions,
                    'change' => 'In workflow',
                    'tone' => 'warning',
                ];
            }

            $recentActivity = array_merge($recentActivity, $this->recentUserEApprovalActivity($user));
        }

        if ($user->can('project_one:rollout:view')) {
            $rollout = $this->rolloutMetrics->build($user);
            if ($rollout !== null) {
                if (($rollout['gate_approvals_awaiting_me'] ?? 0) > 0) {
                    $kpis[] = [
                        'key' => 'rollout_gates_awaiting_me',
                        'label' => 'Gate approvals · awaiting you',
                        'value' => (string) $rollout['gate_approvals_awaiting_me'],
                        'change' => 'PROJECT-ONE formal gates',
                        'tone' => 'danger',
                    ];
                    $actions[] = [
                        'id' => 'ws-gate-approvals',
                        'label' => 'Gate approvals awaiting you',
                        'count' => (int) $rollout['gate_approvals_awaiting_me'],
                        'href' => '/project-one/gate-approvals?awaiting_me=1',
                        'priority' => 'high',
                    ];
                }

                if (($rollout['sla_at_risk'] ?? 0) > 0) {
                    $kpis[] = [
                        'key' => 'rollout_sla_risk',
                        'label' => 'Rollout SLA risk',
                        'value' => (string) $rollout['sla_at_risk'],
                        'change' => '≤10 working days to RFI',
                        'tone' => 'danger',
                    ];
                    $actions[] = [
                        'id' => 'ws-rollout-sla',
                        'label' => 'Rollouts at SLA risk',
                        'count' => (int) $rollout['sla_at_risk'],
                        'href' => '/project-one/rollouts',
                        'priority' => 'high',
                    ];
                }

                if (($rollout['active_rollouts'] ?? 0) > 0 && count($kpis) < 6) {
                    $kpis[] = [
                        'key' => 'active_rollouts',
                        'label' => 'Active rollouts',
                        'value' => (string) $rollout['active_rollouts'],
                        'change' => 'Open rollout programs',
                        'tone' => 'neutral',
                    ];
                }
            }
        }

        if ($user->can('sites:view') && Schema::connection('tenant')->hasTable('sites')) {
            $siteCount = Site::query()->count();
            $mappedSites = Site::query()
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->count();

            if (count($kpis) < 6) {
                $kpis[] = [
                    'key' => 'sites',
                    'label' => 'Sites',
                    'value' => (string) $siteCount,
                    'change' => $mappedSites > 0 ? "{$mappedSites} on map" : 'Registry total',
                    'tone' => 'neutral',
                ];
            }
        }

        if ($kpis === []) {
            $kpis[] = [
                'key' => 'workspace_ready',
                'label' => 'Workspace',
                'value' => 'Ready',
                'change' => 'No pending operational items for your role',
                'tone' => 'success',
            ];
        }

        usort($actions, static function (array $a, array $b): int {
            $priority = ['high' => 0, 'normal' => 1];
            $pa = $priority[$a['priority'] ?? 'normal'] ?? 1;
            $pb = $priority[$b['priority'] ?? 'normal'] ?? 1;
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }

            return ($b['count'] ?? 0) <=> ($a['count'] ?? 0);
        });

        $recentActivity = collect($recentActivity)
            ->sortByDesc('created_at')
            ->take(8)
            ->values()
            ->all();

        return [
            'environment' => app()->environment(),
            'kpis' => array_slice($kpis, 0, 6),
            'actions' => array_values($actions),
            'recent_activity' => $recentActivity,
            'quick_links' => $this->quickLinks($user),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function quickLinks(TenantUser $user): array
    {
        $links = [];

        if ($user->can('project_one:view')) {
            $links[] = ['label' => 'PROJECT-ONE', 'href' => '/project-one'];
        }
        if ($user->can('e_approval:view')) {
            $links[] = ['label' => 'E-Approval', 'href' => '/e-approval'];
        }
        if (TenantNotificationAccess::allowedModulesFor($user) !== []) {
            $links[] = ['label' => 'Notifications', 'href' => '/notifications'];
        }
        if ($user->can('sites:view')) {
            $links[] = ['label' => 'Sites', 'href' => '/sites'];
        }

        return $links;
    }

    /**
     * @param  list<string>  $modules
     * @return list<array<string, mixed>>
     */
    private function recentNotifications(string $userId, array $modules): array
    {
        if (! Schema::connection('tenant')->hasTable('tenant_notifications')) {
            return [];
        }

        return TenantNotification::query()
            ->where('user_id', $userId)
            ->whereIn('module', $modules)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(static fn (TenantNotification $n) => [
                'id' => (string) $n->id,
                'module' => $n->module,
                'label' => $n->type,
                'detail' => $n->body_preview ?? $n->message,
                'href' => $n->href,
                'created_at' => $n->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * User-scoped E-Approval workflow activity (submissions + approval steps).
     *
     * @return list<array<string, mixed>>
     */
    private function recentUserEApprovalActivity(TenantUser $user): array
    {
        $items = [];

        if ($user->can('e_approval:submissions:view')) {
            $submissions = EApprovalSubmission::query()
                ->where('requestor_id', $user->id)
                ->orderByDesc('updated_at')
                ->limit(4)
                ->get(['id', 'document_no', 'status', 'updated_at']);

            foreach ($submissions as $submission) {
                $items[] = [
                    'id' => 'ea-submission-'.$submission->id,
                    'module' => 'e_approval',
                    'label' => self::submissionActivityLabel((string) $submission->status),
                    'detail' => (string) ($submission->document_no ?: 'Your submission'),
                    'href' => '/e-approval/submissions/'.$submission->id,
                    'created_at' => $submission->updated_at?->toIso8601String(),
                ];
            }
        }

        if ($user->can('e_approval:approve')) {
            $approvals = EApprovalRequestApproval::query()
                ->with(['submission:id,document_no', 'step:id,step_order'])
                ->where('approver_id', $user->id)
                ->orderByRaw('COALESCE(acted_at, updated_at, created_at) DESC')
                ->limit(4)
                ->get();

            foreach ($approvals as $approval) {
                $documentNo = $approval->submission?->document_no;
                $stepOrder = $approval->step?->step_order;
                $stepLabel = $stepOrder !== null ? "Step {$stepOrder}" : 'Approval step';
                $detail = $documentNo !== null && $documentNo !== ''
                    ? "{$documentNo} · {$stepLabel}"
                    : $stepLabel;

                $items[] = [
                    'id' => 'ea-approval-'.$approval->id,
                    'module' => 'e_approval',
                    'label' => self::approvalActivityLabel((string) $approval->status),
                    'detail' => $detail,
                    'href' => $approval->submission_id !== null
                        ? '/e-approval/submissions/'.$approval->submission_id
                        : '/e-approval/approvals',
                    'created_at' => ($approval->acted_at ?? $approval->updated_at ?? $approval->created_at)?->toIso8601String(),
                ];
            }
        }

        return $items;
    }

    private static function submissionActivityLabel(string $status): string
    {
        return match ($status) {
            EApprovalSubmissionStatus::DRAFT => 'Draft submission',
            EApprovalSubmissionStatus::PENDING => 'Submission in review',
            EApprovalSubmissionStatus::RETURNED => 'Submission returned',
            EApprovalSubmissionStatus::AWAITING_DCF => 'Awaiting document control',
            EApprovalSubmissionStatus::APPROVED => 'Submission approved',
            EApprovalSubmissionStatus::REJECTED => 'Submission rejected',
            EApprovalSubmissionStatus::CANCELLED => 'Submission cancelled',
            default => 'Your submission',
        };
    }

    private static function approvalActivityLabel(string $status): string
    {
        return match ($status) {
            EApprovalApprovalStatus::PENDING => 'Approval awaiting you',
            EApprovalApprovalStatus::APPROVED => 'You approved a request',
            EApprovalApprovalStatus::REJECTED => 'You rejected a request',
            EApprovalApprovalStatus::CANCELLED => 'Approval step cancelled',
            default => 'Approval step update',
        };
    }
}
