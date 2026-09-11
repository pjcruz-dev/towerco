<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Services;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class TenantUserIndexQueryFilters
{
    private ?bool $departmentColumn = null;

    private ?bool $orgColumns = null;

    private ?bool $licenseColumns = null;

    private ?bool $managerDepartmentColumn = null;

    /**
     * @param  Builder<TenantUser>  $query
     */
    public function apply(Builder $query, TenantUserIndexFilters $filters): void
    {
        $this->applyStatusFilter($query, $filters->status);
        $this->applyLastActiveFilter($query, $filters->lastActive);
        $this->applyMfaFilter($query, $filters->mfa);
        $this->applyRoleFilter($query, $filters->role);
        $this->applyDepartmentFilter($query, $filters->department);
        $this->applyManagerFilter($query, $filters->managerId);
        $this->applyLicenseFilter($query, $filters->license);
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

    /**
     * Match the Users table Department column (own Entra dept, else manager, else Entra snapshot).
     *
     * @param  Builder<TenantUser>  $query
     */
    private function applyDepartmentFilter(Builder $query, ?string $department): void
    {
        if ($department === null || $department === '' || ! $this->hasDepartmentColumn()) {
            return;
        }

        $hasOrg = $this->hasOrgColumns();
        $hasEntraManagerDept = $this->hasManagerDepartmentColumn();

        if ($department === TenantUserIndexFilters::NONE) {
            $query->where(function (Builder $sub) use ($hasOrg, $hasEntraManagerDept): void {
                $this->whereOwnDepartmentBlank($sub);
                if ($hasOrg) {
                    $sub->where(function (Builder $inner): void {
                        $inner->whereNull('manager_id')
                            ->orWhereDoesntHave('manager', static function (Builder $manager): void {
                                $manager->whereNotNull('department')
                                    ->where('department', '!=', '');
                            });
                    });
                }
                if ($hasEntraManagerDept) {
                    $sub->where(function (Builder $inner): void {
                        $inner->whereNull('entra_manager_department')
                            ->orWhere('entra_manager_department', '');
                    });
                }
            });

            return;
        }

        $query->where(function (Builder $sub) use ($department, $hasOrg, $hasEntraManagerDept): void {
            $sub->where('department', $department);

            if ($hasOrg) {
                $sub->orWhere(function (Builder $inner) use ($department): void {
                    $this->whereOwnDepartmentBlank($inner);
                    $inner->whereHas('manager', static function (Builder $manager) use ($department): void {
                        $manager->where('department', $department);
                    });
                });
            }

            if ($hasEntraManagerDept) {
                $sub->orWhere(function (Builder $inner) use ($department, $hasOrg): void {
                    $this->whereOwnDepartmentBlank($inner);
                    if ($hasOrg) {
                        $inner->where(function (Builder $mgr): void {
                            $mgr->whereNull('manager_id')
                                ->orWhereDoesntHave('manager', static function (Builder $manager): void {
                                    $manager->whereNotNull('department')
                                        ->where('department', '!=', '');
                                });
                        });
                    }
                    $inner->where('entra_manager_department', $department);
                });
            }
        });
    }

    /**
     * @param  Builder<TenantUser>  $query
     */
    private function whereOwnDepartmentBlank(Builder $query): void
    {
        $query->where(static function (Builder $sub): void {
            $sub->whereNull('department')
                ->orWhere('department', '')
                ->orWhereRaw("TRIM(department) = ''");
        });
    }

    /**
     * @param  Builder<TenantUser>  $query
     */
    private function applyManagerFilter(Builder $query, ?string $managerId): void
    {
        if ($managerId === null || $managerId === '' || ! $this->hasOrgColumns()) {
            return;
        }

        if ($managerId === TenantUserIndexFilters::NONE) {
            $query->whereNull('manager_id');

            return;
        }

        $query->where('manager_id', $managerId);
    }

    /**
     * @param  Builder<TenantUser>  $query
     */
    private function applyLicenseFilter(Builder $query, ?string $license): void
    {
        if ($license === null || $license === '' || ! $this->hasLicenseColumns()) {
            return;
        }

        if ($license === TenantUserIndexFilters::NONE) {
            $query->where(static function ($sub): void {
                $sub->whereNull('entra_license_label')
                    ->orWhere('entra_license_label', '')
                    ->orWhere('entra_licensed', false);
            });

            return;
        }

        $query->where('entra_license_label', $license);
    }

    private function hasDepartmentColumn(): bool
    {
        return $this->departmentColumn ??= Schema::connection('tenant')->hasColumn('users', 'department');
    }

    private function hasOrgColumns(): bool
    {
        return $this->orgColumns ??= Schema::connection('tenant')->hasColumn('users', 'manager_id');
    }

    private function hasLicenseColumns(): bool
    {
        return $this->licenseColumns ??= Schema::connection('tenant')->hasColumn('users', 'entra_licensed');
    }

    private function hasManagerDepartmentColumn(): bool
    {
        return $this->managerDepartmentColumn ??= Schema::connection('tenant')->hasColumn('users', 'entra_manager_department');
    }
}
