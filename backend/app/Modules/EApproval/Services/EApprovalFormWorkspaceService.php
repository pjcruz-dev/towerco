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
use App\Modules\EApproval\Support\EApprovalSubmissionFieldFilter;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Support\TenantScopedCache;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class EApprovalFormWorkspaceService
{
    public function findPublishedFormBySlug(string $slug): ?EApprovalForm
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        // Fast path: resolve the form id from a short-lived slug map, then load one row.
        $formId = $this->publishedSlugMap()[$slug] ?? null;
        if ($formId !== null) {
            $form = EApprovalForm::query()->where('status', 'published')->find($formId);
            if ($form !== null) {
                $config = EApprovalFormWorkspaceSupport::configFromForm($form);
                if ($config !== null && ($config['slug'] ?? '') === $slug) {
                    return $form;
                }
            }
        }

        // Fallback (stale/missing cache): authoritative scan of published forms.
        foreach (EApprovalForm::query()->where('status', 'published')->get() as $form) {
            $config = EApprovalFormWorkspaceSupport::configFromForm($form);
            if ($config !== null && ($config['slug'] ?? '') === $slug) {
                return $form;
            }
        }

        return null;
    }

    /**
     * Cached slug -> published form id map (short TTL; the fallback scan keeps lookups correct
     * even if a form's slug changed within the TTL window).
     *
     * @return array<string, string>
     */
    private function publishedSlugMap(): array
    {
        $tenantId = (string) (tenant('id') ?? 'unknown');

        return TenantScopedCache::remember(
            "e_approval:workspace_slug_map:{$tenantId}",
            60,
            static function (): array {
                $map = [];
                foreach (EApprovalForm::query()->where('status', 'published')->get() as $form) {
                    $config = EApprovalFormWorkspaceSupport::configFromForm($form);
                    $slug = is_array($config) ? trim((string) ($config['slug'] ?? '')) : '';
                    if ($slug !== '') {
                        $map[$slug] = (string) $form->id;
                    }
                }

                return $map;
            },
        );
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
     * @param  array{
     *   status?: string|null,
     *   from?: string|null,
     *   to?: string|null,
     *   subsidiary?: string|null,
     *   department?: string|null,
     *   mine?: bool
     * }  $filters
     * @return array<string, mixed>
     */
    public function buildDashboard(string $slug, TenantUser $viewer, array $filters = []): array
    {
        // Resolve + authorize outside the cache so 404/403 are never cached.
        $context = $this->resolveWorkspaceContext($slug, $viewer);

        $normalized = $this->normalizeDashboardFilters($filters);
        $tenantId = (string) (tenant('id') ?? 'unknown');
        $key = sprintf(
            'e_approval:workspace_dashboard:%s:%s:%s:%s',
            $tenantId,
            (string) $context['form']->id,
            (string) $viewer->id,
            md5((string) json_encode($normalized)),
        );

        return TenantScopedCache::remember(
            $key,
            30,
            fn (): array => $this->buildDashboardData($context, $viewer, $normalized),
        );
    }

    /**
     * @param  array{form: EApprovalForm, workspace: array<string, mixed>, form_ids: list<string>}  $context
     * @param  array{
     *   status?: string|null,
     *   from?: string|null,
     *   to?: string|null,
     *   subsidiary?: string|null,
     *   department?: string|null,
     *   mine?: bool
     * }  $filters
     * @return array<string, mixed>
     */
    private function buildDashboardData(array $context, TenantUser $viewer, array $filters = []): array
    {
        $form = $context['form'];
        $workspace = $context['workspace'];
        $formIds = $context['form_ids'];
        $isMultiForm = count($formIds) > 1;

        $formId = (string) $form->id;
        $forceOwn = (bool) ($filters['mine'] ?? false);
        $canViewAll = $this->viewerCanSeeAllInWorkspace($viewer, $workspace) && ! $forceOwn;
        $canSubmit = $viewer->can('e_approval:submissions:create');
        $canExport = $this->viewerCanExport($viewer, $workspace);
        $canManageForm = $viewer->can('e_approval:forms:manage');

        $scopedQuery = $this->scopedSubmissionsQuery($formIds, $viewer, $canViewAll, $filters);

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
            $awaitingQuery = DB::table('e_approval_request_approvals as a')
                ->join('e_approval_submissions as s', 's.id', '=', 'a.submission_id')
                ->whereIn('s.form_id', $formIds)
                ->where('a.approver_id', $viewer->id)
                ->where('a.status', EApprovalApprovalStatus::PENDING);

            $awaitingQuery = $this->applyColumnFiltersToApprovalsJoin($awaitingQuery, $formIds, $filters);
            $awaitingMyApproval = (int) $awaitingQuery->count();
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
        $subsidiaryBreakdown = $this->subsidiaryBreakdown(clone $scopedQuery, $formIds);
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
            'filter_options' => $this->workspaceFilterOptions($formIds),
            'applied_filters' => [
                'status' => $filters['status'] ?? null,
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
                'subsidiary' => $filters['subsidiary'] ?? null,
                'department' => $filters['department'] ?? null,
                'mine' => (bool) ($filters['mine'] ?? false),
            ],
            'kpis' => $kpis,
            'status_breakdown' => $statusBreakdown,
            'subsidiary_breakdown' => $subsidiaryBreakdown,
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
     * @param  array{
     *   status?: string|null,
     *   from?: string|null,
     *   to?: string|null,
     *   subsidiary?: string|null,
     *   department?: string|null,
     *   mine?: bool
     * }  $filters
     */
    public function scopedSubmissionsQuery(array $formIds, TenantUser $viewer, bool $canViewAll, array $filters = []): Builder
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

        $status = isset($filters['status']) && is_string($filters['status']) ? trim($filters['status']) : '';
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        if (isset($filters['from']) && is_string($filters['from']) && $filters['from'] !== '') {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (isset($filters['to']) && is_string($filters['to']) && $filters['to'] !== '') {
            $query->where('created_at', '<=', $filters['to']);
        }

        EApprovalSubmissionFieldFilter::applyWorkspaceColumnFilters($query, $formIds, [
            'subsidiary' => $filters['subsidiary'] ?? null,
            'department' => $filters['department'] ?? null,
        ]);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *   status: string|null,
     *   from: string|null,
     *   to: string|null,
     *   subsidiary: string|null,
     *   department: string|null,
     *   mine: bool
     * }
     */
    private function normalizeDashboardFilters(array $filters): array
    {
        $status = isset($filters['status']) ? trim((string) $filters['status']) : '';
        $subsidiary = isset($filters['subsidiary']) ? trim((string) $filters['subsidiary']) : '';
        $department = isset($filters['department']) ? trim((string) $filters['department']) : '';

        return [
            'status' => ($status !== '' && $status !== 'all') ? $status : null,
            'from' => isset($filters['from']) && is_string($filters['from']) && $filters['from'] !== ''
                ? $filters['from']
                : null,
            'to' => isset($filters['to']) && is_string($filters['to']) && $filters['to'] !== ''
                ? $filters['to']
                : null,
            'subsidiary' => $subsidiary !== '' ? $subsidiary : null,
            'department' => $department !== '' ? $department : null,
            'mine' => (bool) ($filters['mine'] ?? false),
        ];
    }

    /**
     * @param  list<string>  $formIds
     * @return array{subsidiaries: list<string>, departments: list<string>}
     */
    private function workspaceFilterOptions(array $formIds): array
    {
        $subsidiaries = app(EApprovalSubsidiaryLogoCatalogService::class)->codes();
        $departments = TenantUser::query()
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->distinct()
            ->orderBy('department')
            ->limit(100)
            ->pluck('department')
            ->map(static fn ($value): string => trim((string) $value))
            ->filter(static fn (string $value): bool => $value !== '')
            ->values()
            ->all();

        // Union form field choice values when present.
        $choiceRows = DB::table('e_approval_form_fields')
            ->whereIn('form_id', $formIds)
            ->whereIn('name', ['subsidiary', 'department'])
            ->get(['name', 'options']);

        foreach ($choiceRows as $row) {
            $options = is_string($row->options) ? json_decode($row->options, true) : $row->options;
            if (! is_array($options)) {
                continue;
            }
            $choices = $options['choices'] ?? [];
            if (! is_array($choices)) {
                continue;
            }
            foreach ($choices as $choice) {
                if (! is_array($choice)) {
                    continue;
                }
                $code = trim((string) ($choice['value'] ?? ''));
                if ($code === '') {
                    continue;
                }
                if ((string) $row->name === 'subsidiary' && ! in_array($code, $subsidiaries, true)) {
                    $subsidiaries[] = $code;
                }
                if ((string) $row->name === 'department' && ! in_array($code, $departments, true)) {
                    $departments[] = $code;
                }
            }
        }

        sort($subsidiaries);
        sort($departments);

        return [
            'subsidiaries' => array_values($subsidiaries),
            'departments' => array_values($departments),
        ];
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  list<string>  $formIds
     * @param  array{subsidiary?: string|null, department?: string|null}  $filters
     * @return \Illuminate\Database\Query\Builder
     */
    private function applyColumnFiltersToApprovalsJoin($query, array $formIds, array $filters)
    {
        foreach (['subsidiary' => $filters['subsidiary'] ?? null, 'department' => $filters['department'] ?? null] as $fieldName => $raw) {
            $value = is_string($raw) ? trim($raw) : '';
            if ($value === '') {
                continue;
            }
            $candidates = array_values(array_unique([$value, mb_strtoupper($value), mb_strtolower($value)]));
            $query->whereExists(function ($exists) use ($formIds, $fieldName, $candidates): void {
                $exists->select(DB::raw(1))
                    ->from('e_approval_form_values as fv')
                    ->join('e_approval_form_fields as ff', 'ff.id', '=', 'fv.field_id')
                    ->whereColumn('fv.submission_id', 's.id')
                    ->whereIn('ff.form_id', $formIds)
                    ->where('ff.name', $fieldName)
                    ->where(function ($match) use ($candidates): void {
                        foreach ($candidates as $candidate) {
                            $match->orWhere('fv.value', $candidate);
                        }
                    });
            });
        }

        return $query;
    }

    /**
     * @return list<array{status: string, label: string, count: int}>
     */
    private function statusBreakdown(Builder $scopedQuery): array
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
     * @param  list<string>  $formIds
     * @return list<array{key: string, label: string, count: int}>
     */
    private function subsidiaryBreakdown(Builder $scopedQuery, array $formIds): array
    {
        if ($formIds === []) {
            return [];
        }

        $submissionIds = (clone $scopedQuery)->select('id');

        $rows = DB::table('e_approval_form_values as fv')
            ->join('e_approval_form_fields as ff', 'ff.id', '=', 'fv.field_id')
            ->whereIn('ff.form_id', $formIds)
            ->where('ff.name', 'subsidiary')
            ->whereIn('fv.submission_id', $submissionIds)
            ->whereNotNull('fv.value')
            ->where('fv.value', '!=', '')
            ->groupBy('fv.value')
            ->orderByDesc(DB::raw('count(*)'))
            ->limit(12)
            ->get([
                'fv.value',
                DB::raw('count(*) as aggregate'),
            ]);

        $breakdown = [];
        foreach ($rows as $row) {
            $code = trim((string) ($row->value ?? ''));
            if ($code === '') {
                continue;
            }
            $breakdown[] = [
                'key' => $code,
                'label' => $code,
                'count' => (int) ($row->aggregate ?? 0),
            ];
        }

        return $breakdown;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentActivity(Builder $scopedQuery): array
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
