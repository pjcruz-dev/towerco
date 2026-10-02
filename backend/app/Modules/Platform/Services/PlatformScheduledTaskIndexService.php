<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services;

use App\Models\Tenant;
use App\Models\TenantEnvironmentClone;
use App\Modules\DynamicEntities\Support\DynScheduledTaskCatalog;
use App\Modules\DynamicEntities\Support\DynScheduledTaskStatus;
use App\Modules\Platform\Support\TowerOsSchedule;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Read-only view of fleet jobs and each tenant's saved cron rows.
 * Task rows are loaded inside that tenant's database only.
 */
final class PlatformScheduledTaskIndexService
{
    public function __construct(
        private readonly TenantEnabledModulesResolver $modules,
    ) {}

    public function index(): array
    {
        $blocks = [];
        foreach (Tenant::query()->orderBy('slug')->orderBy('environment')->cursor() as $tenant) {
            $blocks[] = $this->readTenant($tenant);
        }

        return $this->assemble(TowerOsSchedule::definitions(), $blocks, Carbon::now());
    }

    /**
     * @param  list<array{command: string, module: string, label: string, cadence: string}>  $fleet
     * @param  list<array<string, mixed>>  $blocks
     * @return array<string, mixed>
     */
    public function assemble(array $fleet, array $blocks, CarbonInterface $now): array
    {
        $tenants = [];
        $tasks = [];

        foreach ($blocks as $block) {
            $tenants[] = $block['tenant'];
            foreach ($block['tasks'] as $task) {
                $tasks[] = $task;
            }
        }

        usort($tasks, function (array $left, array $right): int {
            $rank = ['failed' => 0, 'overdue' => 1, 'paused' => 2, 'active' => 3];
            $byStatus = ($rank[$left['status']] ?? 9) <=> ($rank[$right['status']] ?? 9);
            if ($byStatus !== 0) {
                return $byStatus;
            }

            return [$left['slug'], $left['environment'], $left['name']]
                <=> [$right['slug'], $right['environment'], $right['name']];
        });

        return [
            'fleet' => $fleet,
            'tenants' => $tenants,
            'tasks' => $tasks,
            'counts' => [
                'environments' => count($tenants),
                'active' => count(array_filter($tasks, fn (array $task): bool => $task['status'] === 'active')),
                'paused' => count(array_filter($tasks, fn (array $task): bool => $task['status'] === 'paused')),
                'failed' => count(array_filter($tasks, fn (array $task): bool => $task['status'] === 'failed')),
                'overdue' => count(array_filter($tasks, fn (array $task): bool => $task['status'] === 'overdue')),
            ],
            'timezone' => (string) config('app.timezone'),
        ];
    }

    /**
     * @param  array{tenant_id: string, slug: string, environment: string, domain: ?string}  $identity
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function presentTask(array $identity, array $row, CarbonInterface $now): array
    {
        $active = (bool) ($row['is_active'] ?? false);
        $status = DynScheduledTaskStatus::resolve($active, $row['next_run_at'] ?? null, $row['last_status'] ?? null, $now);
        $failed = $status === 'failed';

        return [
            ...$identity,
            'id' => (string) ($row['id'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'command_key' => (string) ($row['command_key'] ?? ''),
            'cron_expression' => (string) ($row['cron_expression'] ?? ''),
            'is_active' => $active,
            'status' => $status,
            'last_run_at' => $this->iso($row['last_run_at'] ?? null),
            'next_run_at' => $this->iso($row['next_run_at'] ?? null),
            'last_status' => array_key_exists('last_status', $row) && $row['last_status'] !== null
                ? (string) $row['last_status']
                : null,
            'last_error' => $failed ? $this->nullableString($row['last_error'] ?? null) : null,
        ];
    }

    /**
     * @return array{tenant: array<string, mixed>, tasks: list<array<string, mixed>>}
     */
    private function readTenant(Tenant $tenant): array
    {
        $identity = [
            'tenant_id' => (string) $tenant->getTenantKey(),
            'slug' => (string) ($tenant->slug ?? ''),
            'environment' => (string) ($tenant->environment ?? ''),
            'domain' => $this->primaryDomain($tenant),
        ];

        try {
            /** @var array{missing: bool, rows: list<array<string, mixed>>} $payload */
            $payload = $tenant->run(function (): array {
                if (! Schema::hasTable('dyn_scheduled_tasks')) {
                    return ['missing' => true, 'rows' => []];
                }

                $rows = DB::table('dyn_scheduled_tasks')
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                        'command_key',
                        'cron_expression',
                        'is_active',
                        'last_run_at',
                        'next_run_at',
                        'last_status',
                        'last_error',
                    ])
                    ->map(fn ($row): array => (array) $row)
                    ->all();

                return ['missing' => false, 'rows' => $rows];
            });
        } catch (Throwable $exception) {
            Log::warning('platform.scheduled_tasks.tenant_unreadable', [
                'tenant_id' => $identity['tenant_id'],
                'environment' => $identity['environment'],
                'error' => $exception->getMessage(),
            ]);

            return [
                'tenant' => [
                    ...$identity,
                    'has_schedule_table' => false,
                    'paused_after_copy' => false,
                    'unreachable' => true,
                ],
                'tasks' => [],
            ];
        }

        $enabled = $this->modules->resolveForTenant($tenant);
        $tasks = array_map(
            fn (array $row): array => $this->presentTask($identity, $row, Carbon::now()),
            array_values(array_filter(
                $payload['rows'],
                fn (array $row): bool => DynScheduledTaskCatalog::isRunnable((string) ($row['command_key'] ?? ''), $enabled),
            )),
        );

        return [
            'tenant' => [
                ...$identity,
                'has_schedule_table' => ! $payload['missing'],
                'paused_after_copy' => $this->pausedAfterCopy($identity['tenant_id'], $tasks, $payload['missing']),
                'unreachable' => false,
            ],
            'tasks' => $tasks,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $tasks
     */
    private function pausedAfterCopy(string $tenantId, array $tasks, bool $tableMissing): bool
    {
        if ($tableMissing || $tasks === []) {
            return false;
        }

        foreach ($tasks as $task) {
            if ($task['is_active'] === true) {
                return false;
            }
        }

        try {
            if (! Schema::connection('central')->hasTable('tenant_environment_clones')) {
                return false;
            }
        } catch (Throwable) {
            return false;
        }

        return TenantEnvironmentClone::query()
            ->where('target_tenant_id', $tenantId)
            ->where('status', TenantEnvironmentClone::STATUS_READY)
            ->exists();
    }

    private function primaryDomain(Tenant $tenant): ?string
    {
        try {
            $domain = $tenant->domains()->orderBy('id')->value('domain');
        } catch (Throwable) {
            return null;
        }

        return is_string($domain) && $domain !== '' ? $domain : null;
    }

    private function moment(mixed $value): ?CarbonInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function iso(mixed $value): ?string
    {
        return $this->moment($value)?->toIso8601String();
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : null;
    }
}
