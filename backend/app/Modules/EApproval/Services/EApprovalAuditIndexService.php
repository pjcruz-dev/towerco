<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalAuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class EApprovalAuditIndexService
{
    public function paginate(
        int $page,
        int $perPage,
        ?string $action = null,
        ?string $search = null,
        ?string $from = null,
        ?string $to = null,
    ): LengthAwarePaginator {
        $query = EApprovalAuditLog::query()
            ->with('user:id,name,email')
            ->orderByDesc('created_at');

        $this->applyFilters($query, $action, $search, $from, $to);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function asPayload(LengthAwarePaginator $paginator): array
    {
        return $paginator->getCollection()->map(static function (EApprovalAuditLog $log): array {
            return [
                'id' => (string) $log->id,
                'action' => $log->action,
                'target_id' => $log->target_id,
                'remarks' => $log->remarks,
                'created_at' => $log->created_at?->toIso8601String(),
                'user' => $log->user ? [
                    'id' => (string) $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                ] : null,
            ];
        })->values()->all();
    }

    /**
     * @param  Builder<EApprovalAuditLog>  $query
     */
    private function applyFilters(Builder $query, ?string $action, ?string $search, ?string $from, ?string $to): void
    {
        if ($action !== null && $action !== '' && $action !== 'all') {
            $query->where('action', $action);
        }

        if ($from !== null && $from !== '') {
            $query->where('created_at', '>=', $from);
        }

        if ($to !== null && $to !== '') {
            $query->where('created_at', '<=', $to);
        }

        if ($search !== null && $search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(static function ($q) use ($like): void {
                $q->where('action', 'like', $like)
                    ->orWhere('target_id', 'like', $like)
                    ->orWhere('remarks', 'like', $like)
                    ->orWhereHas('user', static fn ($u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like));
            });
        }
    }
}
