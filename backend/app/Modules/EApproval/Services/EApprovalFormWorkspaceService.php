<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalAuditLog;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalApprovalStatus;
use App\Modules\EApproval\Support\EApprovalFormWorkspaceAccessSupport;
use App\Modules\EApproval\Support\EApprovalFormWorkspaceDashboardSupport;
use App\Modules\EApproval\Support\EApprovalFormWorkspaceSupport;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Identity\Models\TenantUser;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class EApprovalFormWorkspaceService
{
    public function findPublishedFormBySlug(string $slug): ?EApprovalForm
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        $forms = EApprovalForm::query()
            ->where('status', 'published')
            ->get();

        foreach ($forms as $form) {
            $config = EApprovalFormWorkspaceSupport::configFromForm($form);
            if ($config !== null && ($config['slug'] ?? '') === $slug) {
                return $form;
            }
        }

        return null;
    }

    public function findWorkspaceForm(string $slug): ?EApprovalForm
    {
        return $this->findPublishedFormBySlug($slug);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolveWorkspaceConfig(EApprovalForm $form): ?array
    {
        return EApprovalFormWorkspaceSupport::configFromForm($form);
    }

    /**
     * @param  array<string, mixed>  $workspace
     */
    public function viewerCanExport(TenantUser $viewer, array $workspace): bool
    {
        if ($viewer->can('e_approval:audit:view')) {
            return true;
        }

        return ($workspace['actions']['show_export'] ?? false) === true
            && $viewer->can('e_approval:forms:manage');
    }

    /**
     * @return list<array{form_id: string, form_name: string, slug: string, title: string, description: string|null, is_multi_form: bool}>
     */
    public function listSidebarWorkspaces(TenantUser $viewer): array
    {
        $items = [];

        foreach (EApprovalForm::query()->where('status', 'published')->orderBy('name')->get() as $form) {
            $config = EApprovalFormWorkspaceSupport::configFromForm($form);
            if ($config === null || ($config['nav']['show_in_sidebar'] ?? true) !== true) {
                continue;
            }

            if (! EApprovalFormWorkspaceAccessSupport::viewerCanAccessWorkspace($viewer, $config, $form)) {
                continue;
            }

            $title = trim((string) ($config['title'] ?? '')) ?: (string) $form->name;
            $items[] = [
                'form_id' => (string) $form->id,
                'form_name' => (string) $form->name,
                'slug' => (string) $config['slug'],
                'title' => $title,
                'description' => trim((string) ($config['description'] ?? '')) ?: null,
                'is_multi_form' => ($config['forms']['mode'] ?? 'single') === 'multi',
            ];
        }

        return $items;
    }

    /**
     * @return array{form: EApprovalForm, workspace: array<string, mixed>, form_ids: list<string>}
     */
    public function resolveWorkspaceContext(string $slug, TenantUser $viewer): array
    {
        $form = $this->findWorkspaceForm($slug);
        if ($form === null) {
            abort(404);
        }

        $workspace = $this->resolveWorkspaceConfig($form);
        if ($workspace === null) {
            abort(404);
        }

        if (! EApprovalFormWorkspaceAccessSupport::viewerCanAccessWorkspace($viewer, $workspace, $form)) {
            abort(403);
        }

        $workspace['dashboard'] = EApprovalFormWorkspaceDashboardSupport::normalizeDashboard(
            is_array($workspace['dashboard'] ?? null) ? $workspace['dashboard'] : null,
            $form,
        );

        return [
            'form' => $form,
            'workspace' => $workspace,
            'form_ids' => EApprovalFormWorkspaceAccessSupport::resolveFormIds($form, $workspace),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDashboard(string $slug, TenantUser $viewer): array
    {
        $context = $this->resolveWorkspaceContext($slug, $viewer);
        $form = $context['form'];
        $workspace = $context['workspace'];
        $formIds = $context['form_ids'];
        $isMultiForm = count($formIds) > 1;

        $formId = (string) $form->id;
        $canViewAll = $this->viewerCanSeeAllInWorkspace($viewer, $workspace);
        $canSubmit = $viewer->can('e_approval:submissions:create');
        $canExport = $this->viewerCanExport($viewer, $workspace);
        $canManageForm = $viewer->can('e_approval:forms:manage');

        $scopedQuery = $this->scopedSubmissionsQuery($formIds, $viewer, $canViewAll);

        $pending = (clone $scopedQuery)
            ->where('status', EApprovalSubmissionStatus::PENDING)
            ->count();

        $returned = (clone $scopedQuery)
            ->where('status', EApprovalSubmissionStatus::RETURNED)
            ->count();

        $approved30d = (clone $scopedQuery)
            ->where('status', EApprovalSubmissionStatus::APPROVED)
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->count();

        $rejected30d = (clone $scopedQuery)
            ->where('status', EApprovalSubmissionStatus::REJECTED)
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->count();

        $awaitingMyApproval = 0;
        if ($viewer->can('e_approval:approve')) {
            $awaitingMyApproval = (int) DB::table('e_approval_request_approvals as a')
                ->join('e_approval_submissions as s', 's.id', '=', 'a.submission_id')
                ->whereIn('s.form_id', $formIds)
                ->where('a.approver_id', $viewer->id)
                ->where('a.status', EApprovalApprovalStatus::PENDING)
                ->count();
        }

        $kpis = [
            [
                'key' => 'pending',
                'label' => 'Pending',
                'value' => (string) $pending,
                'change' => 'Awaiting approval',
                'tone' => $pending > 0 ? 'warning' : 'success',
            ],
            [
                'key' => 'returned',
                'label' => 'Needs revision',
                'value' => (string) $returned,
                'change' => 'Returned to requestor',
                'tone' => $returned > 0 ? 'warning' : 'default',
            ],
            [
                'key' => 'approved_30d',
                'label' => 'Approved (30d)',
                'value' => (string) $approved30d,
                'change' => 'Last 30 days',
                'tone' => 'success',
            ],
            [
                'key' => 'rejected_30d',
                'label' => 'Rejected (30d)',
                'value' => (string) $rejected30d,
                'change' => 'Last 30 days',
                'tone' => $rejected30d > 0 ? 'danger' : 'default',
            ],
        ];

        if ($viewer->can('e_approval:approve')) {
            $kpis[] = [
                'key' => 'awaiting_my_approval',
                'label' => 'Awaiting you',
                'value' => (string) $awaitingMyApproval,
                'change' => 'Your approval queue for this form',
                'tone' => $awaitingMyApproval > 0 ? 'warning' : 'success',
            ];
        }

        $newRequestMode = (string) ($workspace['actions']['new_request_mode'] ?? 'focused');
        $newRequestHref = $newRequestMode === 'standard'
            ? '/e-approval/request/'.$formId
            : '/e-approval/focus/'.$formId.'?controlled_mode=new';

        $statusBreakdown = $this->statusBreakdown(clone $scopedQuery);
        $recentActivity = $this->recentActivity(clone $scopedQuery);
        $recentAudit = $this->recentWorkspaceAudit($formIds);

        $linkedForms = EApprovalForm::query()
            ->whereIn('id', $formIds)
            ->get(['id', 'name'])
            ->map(static fn (EApprovalForm $item): array => [
                'id' => (string) $item->id,
                'name' => (string) $item->name,
            ])
            ->values()
            ->all();

        return [
            'form' => [
                'id' => $formId,
                'name' => (string) $form->name,
                'description' => $form->description,
                'status' => (string) $form->status,
                'category' => (string) $form->category,
            ],
            'forms' => $linkedForms,
            'is_multi_form' => $isMultiForm,
            'workspace' => $workspace,
            'dashboard' => $workspace['dashboard'],
            'available_columns' => EApprovalFormWorkspaceDashboardSupport::availableTableColumns($form),
            'kpis' => $kpis,
            'status_breakdown' => $statusBreakdown,
            'recent_activity' => $recentActivity,
            'recent_audit' => $recentAudit,
            'viewer' => [
                'can_submit' => $canSubmit,
                'can_export' => $canExport,
                'can_manage_form' => $canManageForm,
                'list_scope' => $canViewAll ? 'all' : 'own',
                'new_request_href' => $newRequestHref,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $workspace
     */
    public function viewerCanSeeAllInWorkspace(TenantUser $viewer, array $workspace): bool
    {
        if ($viewer->can('e_approval:audit:view')) {
            return true;
        }

        if ($viewer->can('e_approval:forms:manage') && ($workspace['visibility'] ?? 'own') === 'workspace_all') {
            return true;
        }

        return false;
    }

    /**
     * @param  list<string>  $formIds
     */
    public function scopedSubmissionsQuery(array $formIds, TenantUser $viewer, bool $canViewAll): \Illuminate\Database\Eloquent\Builder
    {
        $query = EApprovalSubmission::query()->whereIn('form_id', $formIds);
        if (! $canViewAll) {
            $query->where(static function ($scoped) use ($viewer): void {
                $scoped->where('requestor_id', $viewer->id)
                    ->orWhereIn('id', EApprovalRequestApproval::query()
                        ->where('approver_id', $viewer->id)
                        ->select('submission_id'));
            });
        }

        return $query;
    }

    /**
     * @return list<array{status: string, label: string, count: int}>
     */
    private function statusBreakdown(\Illuminate\Database\Eloquent\Builder $scopedQuery): array
    {
        $counts = (clone $scopedQuery)
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $labels = [
            EApprovalSubmissionStatus::PENDING => 'Pending',
            EApprovalSubmissionStatus::RETURNED => 'Needs revision',
            EApprovalSubmissionStatus::APPROVED => 'Approved',
            EApprovalSubmissionStatus::REJECTED => 'Rejected',
            EApprovalSubmissionStatus::CANCELLED => 'Cancelled',
        ];

        $breakdown = [];
        foreach ($labels as $status => $label) {
            $count = (int) ($counts[$status] ?? 0);
            if ($count === 0) {
                continue;
            }
            $breakdown[] = [
                'status' => $status,
                'label' => $label,
                'count' => $count,
            ];
        }

        return $breakdown;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentActivity(\Illuminate\Database\Eloquent\Builder $scopedQuery): array
    {
        return (clone $scopedQuery)
            ->with(['requestor:id,name,email', 'form:id,name'])
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(static function (EApprovalSubmission $submission): array {
                return [
                    'id' => (string) $submission->id,
                    'document_no' => (string) $submission->document_no,
                    'status' => (string) $submission->status,
                    'form_name' => (string) ($submission->form?->name ?? ''),
                    'requestor_name' => (string) ($submission->requestor?->name ?? ''),
                    'created_at' => $submission->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $formIds
     * @return list<array<string, mixed>>
     */
    private function recentWorkspaceAudit(array $formIds): array
    {
        $submissionIds = EApprovalSubmission::query()
            ->whereIn('form_id', $formIds)
            ->orderByDesc('created_at')
            ->limit(250)
            ->pluck('id');

        return EApprovalAuditLog::query()
            ->with('user:id,name,email')
            ->where(static function ($query) use ($formIds, $submissionIds): void {
                $query->whereIn('target_id', $formIds);
                if ($submissionIds->isNotEmpty()) {
                    $query->orWhereIn('target_id', $submissionIds);
                }
            })
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(static function (EApprovalAuditLog $log): array {
                return [
                    'id' => (string) $log->id,
                    'action' => (string) $log->action,
                    'target_id' => (string) $log->target_id,
                    'remarks' => $log->remarks,
                    'created_at' => $log->created_at?->toIso8601String(),
                    'user_name' => (string) ($log->user?->name ?? ''),
                ];
            })
            ->values()
            ->all();
    }
}
