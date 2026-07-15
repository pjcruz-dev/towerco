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
            "eapproval:dashboard:{$tenantId}:{$userId}",
            30,
            fn (): array => $this->buildUncached($user),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUncached(?TenantUser $user): array
    {
        $publishedForms = EApprovalForm::query()->where('status', 'published')->count();
        $draftForms = EApprovalForm::query()->where('status', '!=', 'published')->count();
        $openSubmissions = EApprovalSubmission::query()
            ->whereNotIn('status', [EApprovalSubmissionStatus::APPROVED, EApprovalSubmissionStatus::REJECTED, EApprovalSubmissionStatus::CANCELLED])
            ->count();

        $awaitingMyApproval = 0;
        if ($user !== null && $user->can('e_approval:approve')) {
            $awaitingMyApproval = (int) DB::table('e_approval_request_approvals')
                ->where('approver_id', $user->id)
                ->where('status', EApprovalApprovalStatus::PENDING)
                ->count();
        }

        $submissions30d = EApprovalSubmission::query()
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->count();

        $staleApprovals = EApprovalRequestApproval::query()
            ->where('status', EApprovalApprovalStatus::PENDING)
            ->where('created_at', '<=', Carbon::now()->subDays(self::STALE_APPROVAL_DAYS))
            ->count();

        $recentAudit = EApprovalAuditLog::query()
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(8)
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

        $financeCounts = [];
        $financeKpis = [];
        $financeActions = [];
        if ($this->financeProcurementModuleEnabled()) {
            $financeCounts = $this->financeProcurementKpis->counts();
            $financeKpis = $this->financeProcurementKpis->kpiCards($financeCounts);
            $financeActions = $this->financeProcurementKpis->actions($financeCounts);
        }

        return [
            'kpis' => [
                [
                    'key' => 'published_forms',
                    'label' => 'Published forms',
                    'value' => (string) $publishedForms,
                    'change' => 'Available to requestors',
                    'tone' => 'success',
                ],
                [
                    'key' => 'open_submissions',
                    'label' => 'Open submissions',
                    'value' => (string) $openSubmissions,
                    'change' => 'Pending workflow completion',
                    'tone' => $openSubmissions > 0 ? 'warning' : 'success',
                ],
                [
                    'key' => 'awaiting_my_approval',
                    'label' => 'Awaiting my approval',
                    'value' => (string) $awaitingMyApproval,
                    'change' => 'Assigned approval steps',
                    'tone' => $awaitingMyApproval > 0 ? 'danger' : 'success',
                ],
                [
                    'key' => 'submissions_30d',
                    'label' => 'Submissions (30d)',
                    'value' => (string) $submissions30d,
                    'change' => 'Volume trend',
                    'tone' => 'neutral',
                ],
                [
                    'key' => 'stale_approvals',
                    'label' => 'Stale approvals',
                    'value' => (string) $staleApprovals,
                    'change' => '>'.self::STALE_APPROVAL_DAYS.' days pending',
                    'tone' => $staleApprovals > 0 ? 'danger' : 'success',
                ],
                [
                    'key' => 'draft_forms',
                    'label' => 'Draft forms',
                    'value' => (string) $draftForms,
                    'change' => 'Not yet published',
                    'tone' => $draftForms > 0 ? 'warning' : 'neutral',
                ],
            ],
            'finance_kpis' => $financeKpis,
            'finance_counts' => $financeCounts,
            'actions' => array_values(array_filter([
                $awaitingMyApproval > 0 ? [
                    'id' => 'ea-awaiting-approval',
                    'label' => 'Approvals awaiting you',
                    'count' => $awaitingMyApproval,
                    'href' => '/e-approval/approvals?awaiting_me=1',
                    'priority' => 'high',
                ] : null,
                $staleApprovals > 0 ? [
                    'id' => 'ea-stale-approvals',
                    'label' => 'Stale approval steps',
                    'count' => $staleApprovals,
                    'href' => '/e-approval/approvals',
                    'priority' => 'high',
                ] : null,
                ...$financeActions,
            ])),
            'recent_audit' => $recentAudit,
            'phase' => 'P7',
            'message' => $this->financeProcurementModuleEnabled()
                ? 'Finance and procurement KPIs, form builder, templates, and approval workflows are active.'
                : 'Form builder, templates, and approval workflows are active.',
        ];
    }

    private function financeProcurementModuleEnabled(): bool
    {
        $enabled = $this->enabledModules->resolveForCurrentTenant();

        return in_array('procurement_one', $enabled, true)
            || in_array('finance_one', $enabled, true);
    }
}
