<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Services;

use App\Core\Support\AllowlistedSort;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class TenantUserIndexService
{
    public const IDS_MAX = 2000;

    private const SORTABLE = [
        'name',
        'email',
        'created_at',
        'updated_at',
    ];

    private ?bool $orgColumns = null;

    private ?bool $licenseColumns = null;

    private ?bool $departmentColumn = null;

    private ?bool $managerDepartmentColumn = null;

    public function __construct(
        private readonly TenantUserImpersonationService $impersonationService,
        private readonly TenantUserSecuritySummaryService $securitySummary,
        private readonly TenantUserIndexQueryFilters $queryFilters,
        private readonly TenantUserDepartmentDisplay $departmentDisplay,
    ) {}

    public function paginate(
        int $page,
        int $perPage,
        string $search,
        ?TenantUserIndexFilters $filters = null,
        ?string $sort = null,
    ): LengthAwarePaginator {
        $filters ??= new TenantUserIndexFilters;

        $eager = [
            'roles:id,name',
            'roles.permissions:id,name',
            'permissions:id,name',
        ];
        if ($this->hasOrgColumns()) {
            $managerColumns = ['id', 'name', 'email'];
            if ($this->hasDepartmentColumn()) {
                $managerColumns[] = 'department';
            }
            $eager[] = 'manager:'.implode(',', $managerColumns);
        }

        $query = TenantUser::query()->with($eager);
        if ($this->hasOrgColumns()) {
            $query->withCount([
                'directReports as direct_report_count' => static function ($q): void {
                    $q->where('is_active', true);
                },
            ]);
        }

        $this->applyListConstraints($query, $search, $filters, $sort);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $this->departmentDisplay->warm($paginator->getCollection());

        return $paginator;
    }

    /**
     * IDs for the current Team & Access filters (select-all across pages).
     *
     * @return array{ids: list<string>, total: int, truncated: bool}
     */
    public function ids(
        string $search,
        ?TenantUserIndexFilters $filters = null,
        ?string $sort = null,
        int $limit = self::IDS_MAX,
    ): array {
        $filters ??= new TenantUserIndexFilters;
        $limit = max(1, min($limit, self::IDS_MAX));

        $base = TenantUser::query();
        $this->applyListConstraints($base, $search, $filters, $sort);

        $total = (clone $base)->count();
        $ids = $base->limit($limit)->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->values()
            ->all();

        return [
            'ids' => $ids,
            'total' => $total,
            'truncated' => $total > count($ids),
        ];
    }

    /**
     * @param  Builder<TenantUser>  $query
     */
    private function applyListConstraints(
        Builder $query,
        string $search,
        TenantUserIndexFilters $filters,
        ?string $sort,
    ): void {
        $this->queryFilters->apply($query, $filters);

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $hasDepartment = $this->hasDepartmentColumn();
            $hasOrg = $this->hasOrgColumns();
            $hasEntraManagerDept = $this->hasManagerDepartmentColumn();
            $hasJobTitle = Schema::connection('tenant')->hasColumn('users', 'job_title');

            $query->where(function ($q) use ($like, $hasDepartment, $hasOrg, $hasEntraManagerDept, $hasJobTitle): void {
                $q->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like);

                if ($hasJobTitle) {
                    $q->orWhere('job_title', 'like', $like);
                }

                if ($hasDepartment) {
                    $q->orWhere('department', 'like', $like);
                }

                if ($hasOrg) {
                    $q->orWhereHas('manager', static function ($manager) use ($like, $hasDepartment): void {
                        $manager->where(static function ($inner) use ($like, $hasDepartment): void {
                            $inner->where('name', 'like', $like);
                            if ($hasDepartment) {
                                $inner->orWhere('department', 'like', $like);
                            }
                        });
                    });
                }

                if ($hasEntraManagerDept) {
                    $q->orWhere('entra_manager_department', 'like', $like)
                        ->orWhere('entra_manager_name', 'like', $like);
                }
            });
        }

        [$column, $direction] = AllowlistedSort::resolve(
            (string) ($sort ?? 'name:asc'),
            self::SORTABLE,
            'name',
            'asc',
        );
        $query->orderBy($column, $direction);
    }

    private function hasOrgColumns(): bool
    {
        return $this->orgColumns ??= Schema::connection('tenant')->hasColumn('users', 'manager_id');
    }

    private function hasDepartmentColumn(): bool
    {
        return $this->departmentColumn ??= Schema::connection('tenant')->hasColumn('users', 'department');
    }

    private function hasManagerDepartmentColumn(): bool
    {
        return $this->managerDepartmentColumn ??= Schema::connection('tenant')->hasColumn('users', 'entra_manager_department');
    }

    private function hasLicenseColumns(): bool
    {
        return $this->licenseColumns ??= Schema::connection('tenant')->hasColumn('users', 'entra_licensed');
    }

    /**
     * Display-only department: own Entra dept, else nearest manager up the chain, else Entra snapshot.
     *
     * @return array{department: ?string, department_display: ?string, department_inherited: bool, entra_manager_department: ?string, manager_department: ?string}
     */
    private function resolveDepartmentFields(TenantUser $user): array
    {
        return $this->departmentDisplay->resolve($user);
    }

    /**
     * @return list<string>
     */
    private function licenseNames(TenantUser $user): array
    {
        $names = $user->entra_license_names;
        if (! is_array($names)) {
            return [];
        }

        return array_values(array_filter($names, static fn (mixed $name): bool => is_string($name) && $name !== ''));
    }

    /**
     * @param  TenantUser|null  $viewer  Current admin listing users (for impersonation eligibility).
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function asPayload(LengthAwarePaginator $paginator, ?TenantUser $viewer = null): array
    {
        $userIds = $paginator->getCollection()
            ->map(static fn (TenantUser $user): string => (string) $user->id)
            ->values()
            ->all();

        $securityByUser = $this->securitySummary->summarizeForUserIds($userIds);

        $actorMayImpersonate = $viewer !== null
            && $this->impersonationService->actorMayImpersonate($viewer);

        return [
            'data' => $paginator->getCollection()->map(function (TenantUser $user) use ($viewer, $securityByUser, $actorMayImpersonate): array {
                $canImpersonate = $actorMayImpersonate
                    && $viewer !== null
                    && $this->impersonationService->isTargetImpersonatable($viewer, $user);
                $security = $securityByUser[(string) $user->id] ?? [
                    'last_active_at' => null,
                    'auth_methods' => [],
                    'mfa_enrolled' => false,
                    'mfa_required' => false,
                ];

                $department = $this->resolveDepartmentFields($user);

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_active' => $user->isActive(),
                    'deactivated_at' => $user->deactivated_at?->toIso8601String(),
                    'roles' => $user->roles->pluck('name')->values()->all(),
                    'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
                    'can_impersonate' => $canImpersonate,
                    'created_at' => $user->created_at?->toIso8601String(),
                    'updated_at' => $user->updated_at?->toIso8601String(),
                    'last_active_at' => $security['last_active_at'],
                    'auth_methods' => $security['auth_methods'],
                    'mfa_enrolled' => $security['mfa_enrolled'],
                    'mfa_required' => $security['mfa_required'],
                    'job_title' => $this->hasOrgColumns() ? $user->job_title : null,
                    'department' => $department['department'],
                    'department_display' => $department['department_display'],
                    'department_inherited' => $department['department_inherited'],
                    'entra_manager_department' => $department['entra_manager_department'],
                    'manager' => $this->hasOrgColumns() && $user->manager !== null ? [
                        'id' => (string) $user->manager->id,
                        'name' => (string) $user->manager->name,
                        'email' => (string) $user->manager->email,
                        'department' => $department['manager_department'],
                    ] : null,
                    'entra_manager_name' => $this->hasOrgColumns() ? $user->entra_manager_name : null,
                    'entra_manager_email' => $this->hasOrgColumns() ? $user->entra_manager_email : null,
                    'direct_report_count' => $this->hasOrgColumns() ? (int) ($user->direct_report_count ?? 0) : 0,
                    'entra_org_synced_at' => $this->hasOrgColumns() ? $user->entra_org_synced_at?->toIso8601String() : null,
                    'entra_licensed' => $this->hasLicenseColumns() ? $user->entra_licensed : null,
                    'entra_license_label' => $this->hasLicenseColumns() ? $user->entra_license_label : null,
                    'entra_license_names' => $this->hasLicenseColumns() ? $this->licenseNames($user) : [],
                ];
            })->values()->all(),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'filter_options' => $this->filterOptions(),
            ],
        ];
    }

    /**
     * Distinct values for Users list advanced filters.
     *
     * @return array{
     *   departments: list<string>,
     *   has_unassigned_department: bool,
     *   managers: list<array{id: string, name: string, email: string}>,
     *   has_unassigned_manager: bool,
     *   licenses: list<string>,
     *   has_unassigned_license: bool
     * }
     */
    public function filterOptions(): array
    {
        $departments = [];
        $hasUnassignedDepartment = false;
        if ($this->hasDepartmentColumn()) {
            $departments = $this->departmentDisplay->distinctDisplayDepartments();
            $hasUnassignedDepartment = $this->departmentDisplay->hasUnassignedDisplayDepartment();
        }

        $managers = [];
        $hasUnassignedManager = false;
        if ($this->hasOrgColumns()) {
            $managerIds = TenantUser::query()
                ->whereNotNull('manager_id')
                ->distinct()
                ->pluck('manager_id')
                ->map(static fn ($id): string => (string) $id)
                ->all();

            if ($managerIds !== []) {
                $managers = TenantUser::query()
                    ->whereIn('id', $managerIds)
                    ->orderBy('name')
                    ->get(['id', 'name', 'email'])
                    ->map(static fn (TenantUser $user): array => [
                        'id' => (string) $user->id,
                        'name' => (string) $user->name,
                        'email' => (string) $user->email,
                    ])
                    ->values()
                    ->all();
            }

            $hasUnassignedManager = TenantUser::query()->whereNull('manager_id')->exists();
        }

        $licenses = [];
        $hasUnassignedLicense = false;
        if ($this->hasLicenseColumns()) {
            $licenses = TenantUser::query()
                ->whereNotNull('entra_license_label')
                ->where('entra_license_label', '!=', '')
                ->distinct()
                ->orderBy('entra_license_label')
                ->pluck('entra_license_label')
                ->map(static fn ($value): string => trim((string) $value))
                ->filter(static fn (string $value): bool => $value !== '')
                ->unique()
                ->values()
                ->all();

            $hasUnassignedLicense = TenantUser::query()
                ->where(static function ($q): void {
                    $q->whereNull('entra_license_label')
                        ->orWhere('entra_license_label', '')
                        ->orWhere('entra_licensed', false);
                })
                ->exists();
        }

        return [
            'departments' => $departments,
            'has_unassigned_department' => $hasUnassignedDepartment,
            'managers' => $managers,
            'has_unassigned_manager' => $hasUnassignedManager,
            'licenses' => $licenses,
            'has_unassigned_license' => $hasUnassignedLicense,
        ];
    }
}
