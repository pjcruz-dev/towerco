<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Services;

use App\Modules\EApproval\Models\EApprovalAuditLog;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Workspace\Models\TenantActivityLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class WorkspaceAuditIndexService
{
    private const LEGACY_FETCH_CAP = 120;

    public function paginate(
        TenantUser $viewer,
        int $page,
        int $perPage,
        ?string $module = null,
        ?string $search = null,
        ?string $from = null,
        ?string $to = null,
    ): LengthAwarePaginator {
        $perPage = max(1, min($perPage, 100));
        $page = max(1, $page);

        if ($this->canViewLegacyEApprovalAudit($viewer)) {
            return $this->paginateFederated($viewer, $page, $perPage, $module, $search, $from, $to);
        }

        return $this->paginateWorkspaceLogs($viewer, $page, $perPage, $module, $search, $from, $to);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function asPayload(LengthAwarePaginator $paginator): array
    {
        return $paginator->getCollection()
            ->map(static fn (array $row): array => $row)
            ->values()
            ->all();
    }

    private function canViewLegacyEApprovalAudit(TenantUser $viewer): bool
    {
        return $viewer->can('e_approval:audit:view');
    }

    private function paginateWorkspaceLogs(
        TenantUser $viewer,
        int $page,
        int $perPage,
        ?string $module,
        ?string $search,
        ?string $from,
        ?string $to,
    ): LengthAwarePaginator {
        $query = TenantActivityLog::query()
            ->with('actor:id,name,email')
            ->orderByDesc('created_at');

        $this->applyWorkspaceFilters($query, $module, $search, $from, $to);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return $paginator->through(fn (TenantActivityLog $log): array => $this->mapWorkspaceLog($log));
    }

    private function paginateFederated(
        TenantUser $viewer,
        int $page,
        int $perPage,
        ?string $module,
        ?string $search,
        ?string $from,
        ?string $to,
    ): LengthAwarePaginator {
        $workspaceRows = $this->fetchWorkspaceRows($module, $search, $from, $to, self::LEGACY_FETCH_CAP);
        $eApprovalRows = $this->fetchEApprovalRows($module, $search, $from, $to, self::LEGACY_FETCH_CAP);
        $authRows = $viewer->can('user:manage')
            ? $this->fetchAuthRows($module, $search, $from, $to, self::LEGACY_FETCH_CAP)
            : [];

        $merged = Collection::make([...$workspaceRows, ...$eApprovalRows, ...$authRows])
            ->sortByDesc(static fn (array $row): string => (string) ($row['created_at'] ?? ''))
            ->values();

        $total = $merged->count();
        $slice = $merged->slice(($page - 1) * $perPage, $perPage)->values();

        return new Paginator(
            $slice,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchWorkspaceRows(
        ?string $module,
        ?string $search,
        ?string $from,
        ?string $to,
        int $limit,
    ): array {
        $query = TenantActivityLog::query()
            ->with('actor:id,name,email')
            ->orderByDesc('created_at')
            ->limit($limit);

        $this->applyWorkspaceFilters($query, $module, $search, $from, $to);

        return $query->get()
            ->map(fn (TenantActivityLog $log): array => $this->mapWorkspaceLog($log))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchEApprovalRows(
        ?string $module,
        ?string $search,
        ?string $from,
        ?string $to,
        int $limit,
    ): array {
        if ($module !== null && $module !== '' && $module !== 'all' && $module !== 'e_approval') {
            return [];
        }

        $query = EApprovalAuditLog::query()
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->limit($limit);

        if ($from !== null && $from !== '') {
            $query->where('created_at', '>=', $from);
        }

        if ($to !== null && $to !== '') {
            $query->where('created_at', '<=', $to);
        }

        if ($search !== null && $search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(static function ($inner) use ($like): void {
                $inner->where('action', 'like', $like)
                    ->orWhere('target_id', 'like', $like)
                    ->orWhere('remarks', 'like', $like)
                    ->orWhereHas('user', static fn ($user) => $user
                        ->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like));
            });
        }

        return $query->get()
            ->map(fn (EApprovalAuditLog $log): array => [
                'id' => 'ea:'.$log->id,
                'source' => 'e_approval',
                'module' => 'e_approval',
                'action' => $log->action,
                'summary' => $log->remarks,
                'entity_type' => 'submission',
                'entity_id' => $log->target_id,
                'entity_label' => null,
                'actor' => $log->user ? [
                    'id' => (string) $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                ] : null,
                'ip_address' => null,
                'metadata' => null,
                'created_at' => $log->created_at?->toIso8601String(),
                'href' => $log->target_id ? '/e-approval/submissions/'.$log->target_id : '/e-approval/audit',
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAuthRows(
        ?string $module,
        ?string $search,
        ?string $from,
        ?string $to,
        int $limit,
    ): array {
        if ($module !== null && $module !== '' && $module !== 'all' && $module !== 'team_access') {
            return [];
        }

        $query = DB::connection('tenant')->table('auth_audit_logs')
            ->orderByDesc('created_at')
            ->limit($limit);

        if ($from !== null && $from !== '') {
            $query->where('created_at', '>=', $from);
        }

        if ($to !== null && $to !== '') {
            $query->where('created_at', '<=', $to);
        }

        if ($search !== null && $search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(static function ($inner) use ($like): void {
                $inner->where('event', 'like', $like)
                    ->orWhere('ip_address', 'like', $like)
                    ->orWhere('context', 'like', $like);
            });
        }

        $rows = $query->get();
        $userIds = $rows->pluck('user_id')->filter()->unique()->values()->all();
        $users = $userIds === []
            ? collect()
            : TenantUser::query()->whereIn('id', $userIds)->get(['id', 'name', 'email'])->keyBy('id');

        return $rows->map(function ($row) use ($users): array {
            $event = (string) ($row->event ?? '');
            $user = $row->user_id !== null ? $users->get((string) $row->user_id) : null;
            $context = null;
            if ($row->context !== null && $row->context !== '') {
                $decoded = json_decode((string) $row->context, true);
                if (is_array($decoded)) {
                    $context = $decoded;
                }
            }

            return [
                'id' => 'auth:'.$row->id,
                'source' => 'auth',
                'module' => 'team_access',
                'action' => $event,
                'summary' => $this->authEventLabel($event),
                'entity_type' => 'user',
                'entity_id' => $row->user_id !== null ? (string) $row->user_id : null,
                'entity_label' => $user?->email,
                'actor' => $user ? [
                    'id' => (string) $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ] : null,
                'ip_address' => $row->ip_address !== null ? (string) $row->ip_address : null,
                'metadata' => $context,
                'created_at' => $row->created_at !== null
                    ? Carbon::parse((string) $row->created_at)->toIso8601String()
                    : null,
                'href' => $user ? '/users?search='.rawurlencode((string) $user->email) : '/users',
            ];
        })->all();
    }

    /**
     * @param  Builder<TenantActivityLog>  $query
     */
    private function applyWorkspaceFilters(
        Builder $query,
        ?string $module,
        ?string $search,
        ?string $from,
        ?string $to,
    ): void {
        if ($module !== null && $module !== '' && $module !== 'all') {
            $query->where('module', $module);
        }

        if ($from !== null && $from !== '') {
            $query->where('created_at', '>=', $from);
        }

        if ($to !== null && $to !== '') {
            $query->where('created_at', '<=', $to);
        }

        if ($search !== null && $search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(static function ($inner) use ($like): void {
                $inner->where('action', 'like', $like)
                    ->orWhere('summary', 'like', $like)
                    ->orWhere('entity_label', 'like', $like)
                    ->orWhere('entity_id', 'like', $like)
                    ->orWhereHas('actor', static fn ($actor) => $actor
                        ->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like));
            });
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function mapWorkspaceLog(TenantActivityLog $log): array
    {
        return [
            'id' => (string) $log->id,
            'source' => 'workspace',
            'module' => $log->module,
            'action' => $log->action,
            'summary' => $log->summary,
            'entity_type' => $log->entity_type,
            'entity_id' => $log->entity_id,
            'entity_label' => $log->entity_label,
            'actor' => $log->actor ? [
                'id' => (string) $log->actor->id,
                'name' => $log->actor->name,
                'email' => $log->actor->email,
            ] : null,
            'ip_address' => $log->ip_address,
            'metadata' => $log->metadata_json,
            'created_at' => $log->created_at?->toIso8601String(),
            'href' => $this->hrefFor($log->module, $log->entity_type, $log->entity_id),
        ];
    }

    private function hrefFor(?string $module, ?string $entityType, ?string $entityId): ?string
    {
        if ($entityId === null || $entityId === '') {
            return null;
        }

        return match ($module) {
            'e_approval' => '/e-approval/submissions/'.$entityId,
            'documents' => $entityType === 'controlled_document'
                ? '/documents/controlled?document='.$entityId
                : '/documents',
            'procurement_one' => match ($entityType) {
                'pr' => '/procurement/prs/'.$entityId,
                'po' => '/procurement/pos/'.$entityId,
                default => '/procurement',
            },
            default => null,
        };
    }

    private function authEventLabel(string $event): string
    {
        return match ($event) {
            'auth.login.success' => 'Signed in',
            'auth.login.failed' => 'Sign-in failed',
            'auth.logout' => 'Signed out',
            'auth.logout_all' => 'Signed out all sessions',
            'auth.session.revoked' => 'Session revoked',
            'auth.admin.sessions_revoked' => 'All sessions revoked by administrator',
            default => str_replace(['auth.', '_'], ['', ' '], $event),
        };
    }
}
