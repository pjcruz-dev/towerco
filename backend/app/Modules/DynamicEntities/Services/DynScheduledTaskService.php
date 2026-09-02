<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\DynamicEntities\Models\DynScheduledTask;
use App\Modules\DynamicEntities\Support\DynScheduledTaskCatalog;
use App\Modules\Identity\Models\TenantUser;
use Cron\CronExpression;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

final class DynScheduledTaskService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        $this->ensureSeeded();

        return DynScheduledTask::query()
            ->orderBy('number')
            ->get()
            ->map(fn (DynScheduledTask $task): array => $this->present($task))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function show(DynScheduledTask $task): array
    {
        return $this->present($task);
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return [
            'runner' => [
                'title' => 'System Cron Runner',
                'hint' => 'Ensure the host crontab (or ECS scheduler) runs Laravel’s scheduler every minute.',
                'command' => '* * * * * cd /opt/toweros && docker compose --env-file .env.docker exec -T api php artisan schedule:run',
                'local_command' => 'docker exec toweros-api php artisan schedule:run',
            ],
            'commands' => array_map(static fn (array $def): array => [
                'key' => $def['key'],
                'name' => $def['name'],
                'description' => $def['description'],
                'default_schedule' => $def['schedule'],
                'execution_label' => $def['execution_label'],
            ], DynScheduledTaskCatalog::allowlistedCommands()),
            'schedule_presets' => DynScheduledTaskCatalog::schedulePresets(),
        ];
    }

    /**
     * @param  array{
     *   name: string,
     *   description?: string|null,
     *   command_key: string,
     *   schedule: string,
     *   cron_expression?: string|null,
     *   is_active?: bool|null
     * }  $data
     * @return array<string, mixed>
     */
    public function create(array $data, TenantUser $actor): array
    {
        $commandKey = trim((string) $data['command_key']);
        $def = DynScheduledTaskCatalog::find($commandKey);
        if ($def === null) {
            throw ValidationException::withMessages(['command_key' => [__('Unknown or disallowed command.')]]);
        }

        $schedule = trim((string) ($data['schedule'] ?? $def['schedule']));
        $cron = DynScheduledTaskCatalog::resolveCron($schedule, $data['cron_expression'] ?? null);
        $this->assertValidCron($cron);

        $task = DynScheduledTask::query()->create([
            'number' => $this->nextNumber(),
            'name' => trim((string) $data['name']) !== '' ? trim((string) $data['name']) : $def['name'],
            'description' => $this->nullableTrim($data['description'] ?? $def['description']),
            'command_key' => $commandKey,
            'schedule' => $schedule,
            'cron_expression' => $cron,
            'is_system' => false,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            'next_run_at' => $this->computeNextRun($cron),
            'sort_order' => $this->nextNumber(),
            'created_by' => (string) $actor->id,
            'updated_by' => (string) $actor->id,
        ]);

        return $this->present($task);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(DynScheduledTask $task, array $data, TenantUser $actor): array
    {
        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ($name !== '') {
                $task->name = $name;
            }
        }
        if (array_key_exists('description', $data)) {
            $task->description = $this->nullableTrim($data['description'] ?? null);
        }
        if (array_key_exists('command_key', $data) && ! $task->is_system) {
            $commandKey = trim((string) $data['command_key']);
            if (DynScheduledTaskCatalog::find($commandKey) === null) {
                throw ValidationException::withMessages(['command_key' => [__('Unknown or disallowed command.')]]);
            }
            $task->command_key = $commandKey;
        }
        if (array_key_exists('schedule', $data) || array_key_exists('cron_expression', $data)) {
            $schedule = array_key_exists('schedule', $data)
                ? trim((string) $data['schedule'])
                : (string) $task->schedule;
            $cron = DynScheduledTaskCatalog::resolveCron(
                $schedule,
                array_key_exists('cron_expression', $data)
                    ? ($data['cron_expression'] ?? null)
                    : $task->cron_expression,
            );
            $this->assertValidCron($cron);
            $task->schedule = $schedule;
            $task->cron_expression = $cron;
            $task->next_run_at = $this->computeNextRun($cron);
        }
        if (array_key_exists('is_active', $data)) {
            $task->is_active = (bool) $data['is_active'];
            if ($task->is_active) {
                $task->next_run_at = $this->computeNextRun((string) $task->cron_expression);
            }
        }

        $task->updated_by = (string) $actor->id;
        $task->save();

        return $this->present($task);
    }

    public function destroy(DynScheduledTask $task): void
    {
        if ($task->is_system) {
            throw ValidationException::withMessages([
                'task' => [__('System scheduled tasks cannot be deleted. Pause them instead.')],
            ]);
        }

        $task->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function toggle(DynScheduledTask $task, TenantUser $actor): array
    {
        $task->is_active = ! $task->is_active;
        if ($task->is_active) {
            $task->next_run_at = $this->computeNextRun((string) $task->cron_expression);
        }
        $task->updated_by = (string) $actor->id;
        $task->save();

        return $this->present($task);
    }

    /**
     * Recompute next_run_at for all tasks.
     *
     * @return array{synced: int}
     */
    public function sync(): array
    {
        $this->ensureSeeded();
        $count = 0;
        DynScheduledTask::query()->orderBy('number')->each(function (DynScheduledTask $task) use (&$count): void {
            $task->next_run_at = $task->is_active
                ? $this->computeNextRun((string) $task->cron_expression)
                : null;
            $task->save();
            $count++;
        });

        return ['synced' => $count];
    }

    /**
     * @return array<string, mixed>
     */
    public function runNow(DynScheduledTask $task, TenantUser $actor): array
    {
        $this->executeTask($task);
        $task->updated_by = (string) $actor->id;
        $task->save();

        return $this->present($task->fresh() ?? $task);
    }

    /**
     * Run due active tasks for the current tenant (called from schedule).
     */
    public function runDue(): int
    {
        if (! $this->tableReady()) {
            return 0;
        }

        $this->ensureSeeded();
        $ran = 0;
        $now = now();

        DynScheduledTask::query()
            ->where('is_active', true)
            ->where(function ($q) use ($now): void {
                $q->whereNull('next_run_at')->orWhere('next_run_at', '<=', $now);
            })
            ->orderBy('number')
            ->get()
            ->each(function (DynScheduledTask $task) use (&$ran): void {
                $this->executeTask($task);
                $ran++;
            });

        return $ran;
    }

    public function ensureSeeded(): void
    {
        if (! $this->tableReady()) {
            return;
        }

        if (DynScheduledTask::query()->exists()) {
            return;
        }

        $n = 1;
        foreach (DynScheduledTaskCatalog::builtins() as $def) {
            DynScheduledTask::query()->create([
                'number' => $n,
                'name' => $def['name'],
                'description' => $def['description'],
                'command_key' => $def['key'],
                'schedule' => $def['schedule'],
                'cron_expression' => $def['cron_expression'],
                'is_system' => true,
                'is_active' => true,
                'next_run_at' => $this->computeNextRun($def['cron_expression']),
                'sort_order' => $n,
            ]);
            $n++;
        }
    }

    private function executeTask(DynScheduledTask $task): void
    {
        $def = DynScheduledTaskCatalog::find((string) $task->command_key);
        if ($def === null) {
            $task->forceFill([
                'last_run_at' => now(),
                'last_status' => 'failed',
                'last_error' => 'Unknown command key.',
                'next_run_at' => $this->computeNextRun((string) $task->cron_expression),
            ])->save();

            return;
        }

        try {
            $params = [];
            if ($def['tenant_scoped']) {
                $domain = $this->currentTenantDomain();
                if ($domain !== null && $domain !== '') {
                    $params['--domain'] = $domain;
                }
            }

            // Commands that only work in tenant context (no --domain) run as-is.
            if ($def['artisan'] === 'dyn:search-index-repair') {
                $params = [];
            }

            $exit = Artisan::call($def['artisan'], $params);
            $output = trim(Artisan::output());
            $task->forceFill([
                'last_run_at' => now(),
                'last_status' => $exit === 0 ? 'ok' : 'failed',
                'last_error' => $exit === 0
                    ? null
                    : ($output !== '' ? $output : 'Command exited with code '.$exit),
                'next_run_at' => $this->computeNextRun((string) $task->cron_expression),
            ])->save();
        } catch (Throwable $e) {
            $task->forceFill([
                'last_run_at' => now(),
                'last_status' => 'failed',
                'last_error' => $e->getMessage(),
                'next_run_at' => $this->computeNextRun((string) $task->cron_expression),
            ])->save();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function present(DynScheduledTask $task): array
    {
        $def = DynScheduledTaskCatalog::find((string) $task->command_key);

        return [
            'id' => (string) $task->id,
            'number' => (int) $task->number,
            'name' => (string) $task->name,
            'description' => $task->description,
            'command_key' => (string) $task->command_key,
            'schedule' => (string) $task->schedule,
            'schedule_display' => DynScheduledTaskCatalog::displaySchedule(
                (string) $task->schedule,
                (string) $task->cron_expression,
            ),
            'cron_expression' => (string) $task->cron_expression,
            'execution_label' => $def['execution_label'] ?? (string) $task->command_key,
            'is_system' => (bool) $task->is_system,
            'is_active' => (bool) $task->is_active,
            'last_run_at' => optional($task->last_run_at)?->toIso8601String(),
            'next_run_at' => optional($task->next_run_at)?->toIso8601String(),
            'last_status' => $task->last_status,
            'last_error' => $task->last_error,
            'created_at' => optional($task->created_at)?->toIso8601String(),
            'updated_at' => optional($task->updated_at)?->toIso8601String(),
        ];
    }

    private function nextNumber(): int
    {
        return ((int) DynScheduledTask::query()->max('number')) + 1;
    }

    private function computeNextRun(string $cronExpression): ?\Carbon\CarbonInterface
    {
        try {
            $cron = new CronExpression($cronExpression);

            return \Illuminate\Support\Carbon::instance($cron->getNextRunDate());
        } catch (Throwable) {
            return now()->addHour();
        }
    }

    private function assertValidCron(string $cron): void
    {
        try {
            new CronExpression($cron);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'cron_expression' => [__('Invalid cron expression.')],
            ]);
        }
    }

    private function currentTenantDomain(): ?string
    {
        $tenant = tenant();
        if ($tenant === null) {
            return null;
        }

        $domain = $tenant->domains()->orderBy('id')->value('domain');

        return is_string($domain) ? $domain : null;
    }

    private function tableReady(): bool
    {
        try {
            return Schema::connection('tenant')->hasTable('dyn_scheduled_tasks');
        } catch (Throwable) {
            return false;
        }
    }

    private function nullableTrim(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : null;
    }
}
