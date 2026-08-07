<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Services;

use App\Core\Support\AllowlistedSort;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class TenantUserIndexService
{
    public const IDS_MAX = 2000;

    private const SORTABLE = [
        'name',
        'email',
        'created_at',
        'updated_at',
    ];

    public function __construct(
        private readonly TenantUserImpersonationService $impersonationService,
        private readonly TenantUserSecuritySummaryService $securitySummary,
        private readonly TenantUserIndexQueryFilters $queryFilters,
    ) {}

    public function paginate(
        int $page,
        int $perPage,
        string $search,
        ?TenantUserIndexFilters $filters = null,
        ?string $sort = null,
    ): LengthAwarePaginator {
        $filters ??= new TenantUserIndexFilters;

        $query = TenantUser::query()
            ->with([
                'roles:id,name',
                'roles.permissions:id,name',
                'permissions:id,name',
            ]);

        $this->applyListConstraints($query, $search, $filters, $sort);

        return $query->paginate($perPage, ['*'], 'page', $page);
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
            $query->where(static function ($q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like);
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
                ];
            })->values()->all(),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }
}
