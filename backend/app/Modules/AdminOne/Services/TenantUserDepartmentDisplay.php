<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Services;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Display department = own Entra dept, else nearest manager up the chain, else Entra manager snapshot.
 */
final class TenantUserDepartmentDisplay
{
    public const MAX_DEPTH = 20;

    /** @var array<string, ?string> */
    private array $departmentById = [];

    /** @var array<string, ?string> */
    private array $managerIdById = [];

    private ?bool $departmentColumn = null;

    private ?bool $orgColumns = null;

    private ?bool $managerDepartmentColumn = null;

    /**
     * Prefetch ancestors for a page of users so display resolution stays in-memory.
     *
     * @param  Collection<int, TenantUser>  $users
     */
    public function warm(Collection $users): void
    {
        if (! $this->hasDepartmentColumn()) {
            return;
        }

        foreach ($users as $user) {
            $id = (string) $user->id;
            $this->departmentById[$id] = $this->normalize($user->department ?? null);
            if ($this->hasOrgColumns()) {
                $this->managerIdById[$id] = $user->manager_id !== null ? (string) $user->manager_id : null;
            }
        }

        if (! $this->hasOrgColumns()) {
            return;
        }

        $pending = collect($this->managerIdById)
            ->filter(static fn (?string $managerId): bool => $managerId !== null && $managerId !== '')
            ->unique()
            ->values();

        for ($depth = 0; $depth < self::MAX_DEPTH && $pending->isNotEmpty(); $depth++) {
            $missing = $pending->filter(fn (string $id): bool => ! array_key_exists($id, $this->departmentById))->values();
            if ($missing->isEmpty()) {
                break;
            }

            $rows = TenantUser::query()
                ->whereIn('id', $missing->all())
                ->get(['id', 'department', 'manager_id']);

            $next = collect();
            foreach ($rows as $row) {
                $id = (string) $row->id;
                $this->departmentById[$id] = $this->normalize($row->department);
                $managerId = $row->manager_id !== null ? (string) $row->manager_id : null;
                $this->managerIdById[$id] = $managerId;
                if ($managerId !== null && $managerId !== '' && ! array_key_exists($managerId, $this->departmentById)) {
                    $next->push($managerId);
                }
            }

            foreach ($missing as $id) {
                if (! array_key_exists($id, $this->departmentById)) {
                    $this->departmentById[$id] = null;
                    $this->managerIdById[$id] = null;
                }
            }

            $pending = $next->unique()->values();
        }
    }

    /**
     * @return array{department: ?string, department_display: ?string, department_inherited: bool, entra_manager_department: ?string, manager_department: ?string}
     */
    public function resolve(TenantUser $user): array
    {
        if (! $this->hasDepartmentColumn()) {
            return [
                'department' => null,
                'department_display' => null,
                'department_inherited' => false,
                'entra_manager_department' => null,
                'manager_department' => null,
            ];
        }

        $own = $this->normalize($user->department ?? null);
        $entraManagerDepartment = null;
        if ($this->hasManagerDepartmentColumn()) {
            $entraManagerDepartment = $this->normalize($user->entra_manager_department ?? null);
        }

        $managerDepartment = null;
        if ($user->relationLoaded('manager') && $user->manager !== null) {
            $managerDepartment = $this->normalize($user->manager->department ?? null);
        }

        $userId = (string) $user->id;
        if (! array_key_exists($userId, $this->departmentById)) {
            $this->departmentById[$userId] = $own;
            if ($this->hasOrgColumns()) {
                $this->managerIdById[$userId] = $user->manager_id !== null ? (string) $user->manager_id : null;
            }
        }

        $display = $this->displayFromMaps($userId, $entraManagerDepartment);

        return [
            'department' => $own,
            'department_display' => $display,
            'department_inherited' => $own === null && $display !== null,
            'entra_manager_department' => $entraManagerDepartment,
            'manager_department' => $managerDepartment,
        ];
    }

    /**
     * @param  array<string, ?string>  $departmentById
     * @param  array<string, ?string>  $managerIdById
     */
    public function displayFromMaps(
        string $userId,
        ?string $entraManagerDepartment = null,
        ?array $departmentById = null,
        ?array $managerIdById = null,
    ): ?string {
        $departmentById ??= $this->departmentById;
        $managerIdById ??= $this->managerIdById;

        $seen = [];
        $current = $userId;
        for ($depth = 0; $depth < self::MAX_DEPTH; $depth++) {
            if (isset($seen[$current])) {
                break;
            }
            $seen[$current] = true;

            $dept = $this->normalize($departmentById[$current] ?? null);
            if ($dept !== null) {
                return $dept;
            }

            $managerId = $managerIdById[$current] ?? null;
            if ($managerId === null || $managerId === '') {
                break;
            }
            $current = (string) $managerId;
        }

        return $this->normalize($entraManagerDepartment);
    }

    /**
     * Restrict a users query to rows whose chain-resolved display department matches.
     *
     * @param  Builder<TenantUser>  $query
     */
    public function applyFilter(Builder $query, ?string $department): void
    {
        if ($department === null || $department === '' || ! $this->hasDepartmentColumn()) {
            return;
        }

        $sql = $this->resolvedDepartmentSubquerySql();
        $bindings = [];

        if ($department === TenantUserIndexFilters::NONE) {
            $query->whereRaw("users.id IN (SELECT id FROM ({$sql}) AS dept_resolved WHERE display_dept IS NULL)", $bindings);

            return;
        }

        $query->whereRaw(
            "users.id IN (SELECT id FROM ({$sql}) AS dept_resolved WHERE display_dept = ?)",
            [$department],
        );
    }

    /**
     * @return list<string>
     */
    public function distinctDisplayDepartments(): array
    {
        if (! $this->hasDepartmentColumn()) {
            return [];
        }

        $sql = $this->resolvedDepartmentSubquerySql();
        $rows = DB::connection('tenant')->select(
            "SELECT DISTINCT display_dept AS department FROM ({$sql}) AS dept_resolved WHERE display_dept IS NOT NULL ORDER BY display_dept ASC"
        );

        return collect($rows)
            ->map(static fn (object $row): string => trim((string) ($row->department ?? '')))
            ->filter(static fn (string $value): bool => $value !== '')
            ->values()
            ->all();
    }

    public function hasUnassignedDisplayDepartment(): bool
    {
        if (! $this->hasDepartmentColumn()) {
            return false;
        }

        $sql = $this->resolvedDepartmentSubquerySql();

        return DB::connection('tenant')->selectOne(
            "SELECT 1 AS present FROM ({$sql}) AS dept_resolved WHERE display_dept IS NULL LIMIT 1"
        ) !== null;
    }

    private function resolvedDepartmentSubquerySql(): string
    {
        $entraSelect = $this->hasManagerDepartmentColumn()
            ? 'NULLIF(TRIM(u.entra_manager_department), \'\')'
            : 'NULL';

        if (! $this->hasOrgColumns()) {
            return <<<SQL
SELECT
    u.id AS id,
    COALESCE(NULLIF(TRIM(u.department), ''), {$entraSelect}) AS display_dept
FROM users u
SQL;
        }

        return <<<SQL
WITH RECURSIVE dept_walk AS (
    SELECT
        u.id AS origin_id,
        NULLIF(TRIM(u.department), '') AS dept,
        u.manager_id AS manager_id,
        0 AS depth
    FROM users u
    UNION ALL
    SELECT
        dw.origin_id,
        NULLIF(TRIM(m.department), ''),
        m.manager_id,
        dw.depth + 1
    FROM dept_walk dw
    INNER JOIN users m ON m.id = dw.manager_id
    WHERE dw.dept IS NULL
      AND dw.manager_id IS NOT NULL
      AND dw.depth < {$this->maxDepthLiteral()}
),
chain_dept AS (
    SELECT origin_id, MAX(dept) AS from_chain
    FROM dept_walk
    GROUP BY origin_id
)
SELECT
    c.origin_id AS id,
    COALESCE(c.from_chain, {$entraSelect}) AS display_dept
FROM chain_dept c
INNER JOIN users u ON u.id = c.origin_id
SQL;
    }

    private function maxDepthLiteral(): int
    {
        return self::MAX_DEPTH;
    }

    private function normalize(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function hasDepartmentColumn(): bool
    {
        return $this->departmentColumn ??= Schema::connection('tenant')->hasColumn('users', 'department');
    }

    private function hasOrgColumns(): bool
    {
        return $this->orgColumns ??= Schema::connection('tenant')->hasColumn('users', 'manager_id');
    }

    private function hasManagerDepartmentColumn(): bool
    {
        return $this->managerDepartmentColumn ??= Schema::connection('tenant')->hasColumn('users', 'entra_manager_department');
    }
}
