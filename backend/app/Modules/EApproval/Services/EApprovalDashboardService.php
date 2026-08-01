<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalAuditLog;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalApprovalStatus;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class EApprovalDashboardService
{
    private const STALE_APPROVAL_DAYS = 3;

    private const QUEUE_LIMIT = 8;

    public function __construct(
        private readonly EApprovalFinanceProcurementKpiService $financeProcurementKpis,
        private readonly TenantEnabledModulesResolver $enabledModules,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(?TenantUser $user): array
    {
        $tenantId = (string) (tenant('id') ?? 'unknown');
        $userId = $user?->id ?? 'guest';

        return Cache::remember(
            "eapproval:dashboard:v2:{$tenantId}:{$userId}",
            30,
            fn (): array => $this->buildUncached($user),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUncached(?TenantUser $user): array
    {
        $canApprove = $user !== null && $user->can('e_approval:approve');
        $canCreate = $user !== null && $user->can('e_approval:submissions:create');
        $canManageForms = $user !== null && $user->can('e_approval:forms:manage');
        $canAudit = $user !== null && $user->can('e_approval:audit:view');

        $awaitingMyApproval = 0;
        $staleMyApprovals = 0;
        if ($canApprove && $user !== null) {
            $awaitingMyApproval = (int) DB::table('e_approval_request_approvals')
                ->where('approver_id', $user->id)
                ->where('status', EApprovalApprovalStatus::PENDING)
                ->count();

            $staleMyApprovals = (int) DB::table('e_approval_request_approvals')
                ->where('approver_id', $user->id)
                ->where('status', EApprovalApprovalStatus::PENDING)
                ->where('created_at', '<=', Carbon::now()->subDays(self::STALE_APPROVAL_DAYS))
                ->count();
        }

        $myOpenSubmissions = 0;
        $myReturned = 0;
        $myDrafts = 0;
        if ($user !== null) {
            $myOpenSubmissions = (int) EApprovalSubmission::query()
                ->where('requestor_id', $user->id)
                ->whereNotIn('status', [
                    EApprovalSubmissionStatus::APPROVED,
                    EApprovalSubmissionStatus::REJECTED,
                    EApprovalSubmissionStatus::CANCELLED,
                ])
                ->count();

            $myReturned = (int) EApprovalSubmission::query()
                ->where('requestor_id', $user->id)
                ->where('status', EApprovalSubmissionStatus::RETURNED)
                ->count();

            $myDrafts = (int) EApprovalSubmission::query()
                ->where('requestor_id', $user->id)
                ->where('status', EApprovalSubmissionStatus::DRAFT)
                ->count();
        }

        $publishedForms = EApprovalForm::query()->where('status', 'published')->count();
        $draftForms = EApprovalForm::query()->where('status', '!=', 'published')->count();

        $kpis = array_values(array_filter([
            $canApprove ? [
                'key' => 'awaiting_my_approval',
                'label' => 'Awaiting my approval',
                'value' => (string) $awaitingMyApproval,
                'change' => 'Assigned to you',
                'tone' => $awaitingMyApproval > 0 ? 'danger' : 'success',
                'href' => '/e-approval/approvals?awaiting_me=1',
            ] : null,
            [
                'key' => 'my_returned',
                'label' => 'Returned to me',
                'value' => (string) $myReturned,
                'change' => 'Needs resubmit',
                'tone' => $myReturned > 0 ? 'warning' : 'neutral',
                'href' => '/e-approval/submissions?status=returned&mine=1',
            ],
            [
                'key' => 'my_open_submissions',
                'label' => 'My open submissions',
                'value' => (string) $myOpenSubmissions,
                'change' => 'In progress',
                'tone' => $myOpenSubmissions > 0 ? 'warning' : 'success',
                'href' => '/e-approval/submissions?mine=1',
            ],
            $canApprove ? [
                'key' => 'stale_my_approvals',
                'label' => 'SLA at risk',
                'value' => (string) $staleMyApprovals,
                'change' => '>'.self::STALE_APPROVAL_DAYS.' days pending',
                'tone' => $staleMyApprovals > 0 ? 'danger' : 'neutral',
                'href' => '/e-approval/approvals?awaiting_me=1',
            ] : null,
            $canManageForms ? [
                'key' => 'published_forms',
                'label' => 'Published forms',
                'value' => (string) $publishedForms,
                'change' => $draftForms > 0 ? $draftForms.' draft'.($draftForms === 1 ? '' : 's') : 'Ready for requestors',
                'tone' => 'neutral',
                'href' => '/e-approval/forms',
            ] : null,
        ]));

        $financeCounts = [];
        $financeKpis = [];
        $financeActions = [];
        if ($this->financeProcurementModuleEnabled()) {
            $financeCounts = $this->financeProcurementKpis->counts();
            $financeKpis = $this->financeProcurementKpis->kpiCards($financeCounts);
            $financeActions = $this->financeProcurementKpis->actions($financeCounts);
        }

        $queues = [
            'awaiting_approval' => $canApprove && $user !== null
                ? $this->awaitingApprovalQueue($user)
                : [],
            'my_attention' => $user !== null
                ? $this->myAttentionQueue($user)
                : [],
        ];

        $recentAudit = [];
        if ($canAudit) {
            $recentAudit = EApprovalAuditLog::query()
                ->with('user:id,name')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get()
                ->map(static fn (EApprovalAuditLog $log) => [
                    'id' => (string) $log->id,
                    'action' => $log->action,
                    'target_id' => $log->target_id,
                    'user_name' => $log->user?->name,
                    'created_at' => $log->created_at?->toIso8601String(),
                ])
                ->values()
                ->all();
        }

        return [
            'kpis' => $kpis,
            'finance_kpis' => $financeKpis,
            'finance_counts' => $financeCounts,
            'queues' => $queues,
            'capabilities' => [
                'can_approve' => $canApprove,
                'can_create' => $canCreate,
                'can_manage_forms' => $canManageForms,
                'can_audit' => $canAudit,
            ],
            'actions' => array_values(array_filter([
                $awaitingMyApproval > 0 ? [
                    'id' => 'ea-awaiting-approval',
                    'label' => 'Approvals awaiting you',
                    'count' => $awaitingMyApproval,
                    'href' => '/e-approval/approvals?awaiting_me=1',
                    'priority' => 'high',
                ] : null,
                $myReturned > 0 ? [
                    'id' => 'ea-returned',
                    'label' => 'Returned submissions',
                    'count' => $myReturned,
                    'href' => '/e-approval/submissions?status=returned&mine=1',
                    'priority' => 'high',
                ] : null,
                $myDrafts > 0 ? [
                    'id' => 'ea-drafts',
                    'label' => 'Draft submissions',
                    'count' => $myDrafts,
                    'href' => '/e-approval/submissions?status=draft&mine=1',
                    'priority' => 'medium',
                ] : null,
                ...$financeActions,
            ])),
            'recent_audit' => $recentAudit,
            'phase' => 'P7',
            'message' => 'Your E-Approval inbox — approvals, returns, and open requests.',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function awaitingApprovalQueue(TenantUser $user): array
    {
        return EApprovalRequestApproval::query()
            ->with(['submission.form:id,name', 'submission.requestor:id,name,email', 'step'])
            ->where('approver_id', $user->id)
            ->where('status', EApprovalApprovalStatus::PENDING)
            ->orderBy('created_at')
            ->limit(self::QUEUE_LIMIT)
            ->get()
            ->map(static function (EApprovalRequestApproval $approval): array {
                $submission = $approval->submission;

                return [
                    'id' => (string) $approval->id,
                    'submission_id' => $submission ? (string) $submission->id : null,
                    'document_no' => $submission?->document_no,
                    'form_name' => $submission?->form?->name,
                    'requestor_name' => $submission?->requestor?->name,
                    'status' => (string) ($submission?->status ?? $approval->status),
                    'step_order' => $approval->step?->step_order,
                    'waiting_since' => $approval->created_at?->toIso8601String(),
                    'href' => $submission ? '/e-approval/submissions/'.$submission->id : '/e-approval/approvals?awaiting_me=1',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function myAttentionQueue(TenantUser $user): array
    {
        return EApprovalSubmission::query()
            ->with(['form:id,name'])
            ->where('requestor_id', $user->id)
            ->whereIn('status', [
                EApprovalSubmissionStatus::RETURNED,
                EApprovalSubmissionStatus::DRAFT,
            ])
            ->orderByRaw("CASE WHEN status = ? THEN 0 WHEN status = ? THEN 1 ELSE 2 END", [
                EApprovalSubmissionStatus::RETURNED,
                EApprovalSubmissionStatus::DRAFT,
            ])
            ->orderByDesc('updated_at')
            ->limit(self::QUEUE_LIMIT)
            ->get()
            ->map(static function (EApprovalSubmission $submission): array {
                return [
                    'id' => (string) $submission->id,
                    'submission_id' => (string) $submission->id,
                    'document_no' => $submission->document_no,
                    'form_name' => $submission->form?->name,
                    'requestor_name' => null,
                    'status' => (string) $submission->status,
                    'step_order' => $submission->current_step,
                    'waiting_since' => $submission->updated_at?->toIso8601String(),
                    'href' => '/e-approval/submissions/'.$submission->id,
                ];
            })
            ->values()
            ->all();
    }

    private function financeProcurementModuleEnabled(): bool
    {
        $enabled = $this->enabledModules->resolveForCurrentTenant();

        return in_array('procurement_one', $enabled, true)
            || in_array('finance_one', $enabled, true);
    }
}
