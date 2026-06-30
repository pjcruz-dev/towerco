<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Services;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class TenantUserIndexFilters
{
    public function __construct(
        public readonly ?string $status = null,
        public readonly ?string $lastActive = null,
        public readonly ?string $mfa = null,
        public readonly ?string $role = null,
    ) {}

    public static function fromRequest(array $validated): self
    {
        $status = isset($validated['status']) ? (string) $validated['status'] : null;
        $lastActive = isset($validated['last_active']) ? (string) $validated['last_active'] : null;
        $mfa = isset($validated['mfa']) ? (string) $validated['mfa'] : null;
        $role = isset($validated['role']) ? trim((string) $validated['role']) : null;

        return new self(
            status: $status === 'all' ? null : $status,
            lastActive: $lastActive === 'all' ? null : $lastActive,
            mfa: $mfa === 'all' ? null : $mfa,
            role: $role === '' || $role === 'all' ? null : $role,
        );
    }
}

final class TenantUserIndexQueryFilters
{
    /**
     * @param  Builder<TenantUser>  $query
     */
    public function apply(Builder $query, TenantUserIndexFilters $filters): void
    {
        $this->applyStatusFilter($query, $filters->status);
        $this->applyLastActiveFilter($query, $filters->lastActive);
        $this->applyMfaFilter($query, $filters->mfa);
        $this->applyRoleFilter($query, $filters->role);
    }

    /**
     * @param  Builder<TenantUser>  $query
     */
    private function applyStatusFilter(Builder $query, ?string $status): void
    {
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }
    }

    /**
     * @param  Builder<TenantUser>  $query
     */
    private function applyLastActiveFilter(Builder $query, ?string $lastActive): void
    {
        if ($lastActive === null || $lastActive === '') {
            return;
        }

        if ($lastActive === 'never') {
            $query->whereNotExists(static function ($sub): void {
                $sub->select(DB::raw('1'))
                    ->from('auth_sessions')
                    ->whereColumn('auth_sessions.user_id', 'users.id')
                    ->where('auth_sessions.state', 'active')
                    ->whereNull('auth_sessions.revoked_at')
                    ->whereNotNull('auth_sessions.last_seen_at');
            });

            return;
        }

        $days = match ($lastActive) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            default => null,
        };

        if ($days === null) {
            return;
        }

        $since = now()->subDays($days);

        $query->whereIn('id', static function ($sub) use ($since): void {
            $sub->select('user_id')
                ->from('auth_sessions')
                ->where('state', 'active')
                ->whereNull('revoked_at')
                ->whereNotNull('last_seen_at')
                ->groupBy('user_id')
                ->havingRaw('MAX(last_seen_at) >= ?', [$since]);
        });
    }

    /**
     * @param  Builder<TenantUser>  $query
     */
    private function applyMfaFilter(Builder $query, ?string $mfa): void
    {
        if ($mfa === null || $mfa === '') {
            return;
        }

        $enrolledExists = static function ($sub): void {
            $sub->select(DB::raw('1'))
                ->from('mfa_factors')
                ->whereColumn('mfa_factors.user_id', 'users.id')
                ->whereNull('mfa_factors.disabled_at')
                ->whereNotNull('mfa_factors.verified_at');
        };

        if ($mfa === 'enrolled') {
            $query->whereExists($enrolledExists);
        } elseif ($mfa === 'not_enrolled') {
            $query->whereNotExists($enrolledExists);
        }
    }

    /**
     * @param  Builder<TenantUser>  $query
     */
    private function applyRoleFilter(Builder $query, ?string $role): void
    {
        if ($role === null || $role === '') {
            return;
        }

        $query->whereHas('roles', static function ($sub) use ($role): void {
            $sub->where('name', $role);
        });
    }
}
