<?php

declare(strict_types=1);

namespace App\Console\Commands\Tenants;

use App\Console\Commands\Tenants\Concerns\ResolvesTenantFromConsoleOptions;
use App\Models\Tenant;
use App\Modules\AdminOne\Services\TenantUserAdminService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Console\Command;

final class TenantUsersBulkAssignRolesCommand extends Command
{
    use ResolvesTenantFromConsoleOptions;

    protected $signature = 'tenant:users-bulk-assign-roles
        {--tenant= : Tenant UUID}
        {--domain= : Tenant domain hostname}
        {--all : Run for every tenant}
        {--roles= : Comma-separated roles to assign (default: e_approval_requestor,ticketing_contributor)}
        {--remove-roles= : Comma-separated roles to remove after assignment}
        {--mode=add : add (keep existing roles) or replace (sync to roles list only)}
        {--having-role= : Only users who currently have this role}
        {--without-role= : Exclude users who have this role}
        {--only-role= : Only users whose role set is exactly this single role}
        {--active-only : Skip deactivated users}
        {--dry-run : Preview matches without writing}
        {--force : Apply changes without confirmation}
    ';

    protected $description = 'Bulk assign or replace roles for tenant users (e.g. migrate viewer SSO users to requestor + ticketing contributor).';

    public function handle(TenantUserAdminService $adminUsers): int
    {
        $tenants = $this->resolveTenants();
        if ($tenants === []) {
            $this->error('No tenants matched. Pass --tenant=UUID, --domain=hostname, or --all.');

            return self::FAILURE;
        }

        $roles = $this->parseList((string) $this->option('roles'));
        if ($roles === []) {
            $roles = ['e_approval_requestor', 'ticketing_contributor'];
        }

        $removeRoles = $this->parseList((string) $this->option('remove-roles'));
        $mode = strtolower(trim((string) $this->option('mode')));
        if (! in_array($mode, ['add', 'replace'], true)) {
            $this->error('Invalid --mode. Use add or replace.');

            return self::FAILURE;
        }

        $havingRole = trim((string) $this->option('having-role'));
        $withoutRole = trim((string) $this->option('without-role'));
        $onlyRole = trim((string) $this->option('only-role'));
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if ($dryRun) {
            $this->warn('Dry run — no role changes will be written.');
        } elseif (! $force && $this->input->isInteractive() && ! $this->confirm('Apply role changes to matched users?', false)) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            $domain = (string) ($tenant->domains()->value('domain') ?? $tenant->id);
            $this->line("Tenant: {$domain}");

            $summary = $tenant->run(function () use (
                $adminUsers,
                $roles,
                $removeRoles,
                $mode,
                $havingRole,
                $withoutRole,
                $onlyRole,
                $dryRun,
            ): array {
                $userIds = $this->matchingUserIds($havingRole, $withoutRole, $onlyRole, (bool) $this->option('active-only'));
                if ($userIds === []) {
                    return ['matched' => 0, 'processed' => 0, 'skipped' => 0, 'errors' => []];
                }

                $this->table(
                    ['Email', 'Name', 'Current roles'],
                    TenantUser::query()
                        ->whereIn('id', $userIds)
                        ->orderBy('email')
                        ->get()
                        ->map(static fn (TenantUser $user): array => [
                            (string) $user->email,
                            (string) $user->name,
                            implode(', ', $user->getRoleNames()->sort()->values()->all()),
                        ])
                        ->all(),
                );

                if ($dryRun) {
                    return ['matched' => count($userIds), 'processed' => 0, 'skipped' => 0, 'errors' => []];
                }

                return array_merge(
                    ['matched' => count($userIds)],
                    $adminUsers->bulkAssignRoles($userIds, $roles, $mode, $removeRoles),
                );
            });

            $this->line(sprintf(
                '  matched=%d processed=%d skipped=%d errors=%d',
                (int) ($summary['matched'] ?? 0),
                (int) ($summary['processed'] ?? 0),
                (int) ($summary['skipped'] ?? 0),
                count($summary['errors'] ?? []),
            ));

            foreach ($summary['errors'] ?? [] as $error) {
                $this->warn(sprintf('  - %s: %s', $error['user_id'] ?? '?', $error['message'] ?? 'error'));
            }
        }

        $this->info($dryRun ? 'Dry run complete.' : 'Bulk role assignment complete.');

        return self::SUCCESS;
    }

    /** @return list<Tenant> */
    private function resolveTenants(): array
    {
        if ($this->option('all')) {
            return Tenant::query()->orderBy('id')->get()->all();
        }

        $tenant = $this->resolveTenantFromOptions();

        return $tenant instanceof Tenant ? [$tenant] : [];
    }

    /**
     * @return list<string>
     */
    private function matchingUserIds(string $havingRole, string $withoutRole, string $onlyRole, bool $activeOnly): array
    {
        $query = TenantUser::query()->with('roles');

        if ($activeOnly) {
            $query->where('is_active', true)->whereNull('deactivated_at');
        }

        if ($havingRole !== '') {
            $query->role($havingRole);
        }

        $users = $query->orderBy('email')->get();

        if ($withoutRole !== '') {
            $users = $users->reject(
                static fn (TenantUser $user): bool => $user->hasRole($withoutRole),
            );
        }

        if ($onlyRole !== '') {
            $users = $users->filter(
                static fn (TenantUser $user): bool => $user->getRoleNames()->sort()->values()->all() === [$onlyRole],
            );
        }

        return $users->pluck('id')->map(static fn ($id): string => (string) $id)->values()->all();
    }

    /**
     * @return list<string>
     */
    private function parseList(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('trim', explode(',', $value)))));
    }
}
