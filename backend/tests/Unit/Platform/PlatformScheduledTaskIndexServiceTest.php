<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use App\Modules\DynamicEntities\Support\DynScheduledTaskCatalog;
use App\Modules\Platform\Services\PlatformScheduledTaskIndexService;
use App\Modules\Platform\Support\TowerOsSchedule;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PlatformScheduledTaskIndexServiceTest extends TestCase
{
    #[Test]
    public function fleet_list_includes_document_expiry_and_saved_cron_runner(): void
    {
        $commands = array_column(TowerOsSchedule::definitions(), 'command');

        $this->assertContains('tenants:backup-schedule', $commands);
        $this->assertContains('dyn:run-scheduled-tasks', $commands);
        $this->assertNotContains('e-approval:sla-run', $commands);
        $this->assertNotContains('ticketing:sla-run', $commands);
        $this->assertNotContains('documents:expiry-notify', $commands);
        $this->assertFalse(DynScheduledTaskCatalog::isRunnable('ticketing_sla', ['e_approval']));
        $this->assertTrue(DynScheduledTaskCatalog::isRunnable('ticketing_sla', ['ticketing']));
        $this->assertTrue(DynScheduledTaskCatalog::isRunnable('audit_prune', []));
    }

    #[Test]
    public function each_environment_keeps_its_own_cron_rows(): void
    {
        $service = new PlatformScheduledTaskIndexService(new TenantEnabledModulesResolver);
        $now = Carbon::parse('2026-10-02 09:00:00');

        $staging = $service->presentTask($this->identity('staging-id', 'staging', 'staging.alliancetowers.com'), [
            'id' => 'task-staging',
            'name' => 'Staging reminder',
            'command_key' => 'remind',
            'cron_expression' => '0 * * * *',
            'is_active' => true,
            'next_run_at' => '2026-10-02 08:50:00',
            'last_run_at' => '2026-10-02 08:00:00',
            'last_status' => 'ok',
            'last_error' => null,
        ], $now);

        $production = $service->presentTask($this->identity('production-id', 'production', 'app.alliancetowers.com'), [
            'id' => 'task-production',
            'name' => 'Production reminder',
            'command_key' => 'remind',
            'cron_expression' => '0 * * * *',
            'is_active' => false,
            'next_run_at' => null,
            'last_run_at' => null,
            'last_status' => 'ok',
            'last_error' => null,
        ], $now);

        $failed = $service->presentTask($this->identity('test-id', 'test', 'test.alliancetowers.com'), [
            'id' => 'task-test',
            'name' => 'Test reminder',
            'command_key' => 'remind',
            'cron_expression' => '0 * * * *',
            'is_active' => true,
            'next_run_at' => '2026-10-02 10:00:00',
            'last_run_at' => '2026-10-02 08:00:00',
            'last_status' => 'failed',
            'last_error' => 'mailbox down',
        ], $now);

        $recent = $service->presentTask($this->identity('staging-id', 'staging', 'staging.alliancetowers.com'), [
            'id' => 'task-recent',
            'name' => 'Just due',
            'command_key' => 'remind',
            'cron_expression' => '* * * * *',
            'is_active' => true,
            'next_run_at' => '2026-10-02 08:56:00',
            'last_status' => 'ok',
        ], $now);

        $result = $service->assemble(TowerOsSchedule::definitions(), [
            [
                'tenant' => [
                    ...$this->identity('staging-id', 'staging', 'staging.alliancetowers.com'),
                    'has_schedule_table' => true,
                    'paused_after_copy' => false,
                    'unreachable' => false,
                ],
                'tasks' => [$staging, $recent],
            ],
            [
                'tenant' => [
                    ...$this->identity('production-id', 'production', 'app.alliancetowers.com'),
                    'has_schedule_table' => true,
                    'paused_after_copy' => true,
                    'unreachable' => false,
                ],
                'tasks' => [$production],
            ],
            [
                'tenant' => [
                    ...$this->identity('test-id', 'test', 'test.alliancetowers.com'),
                    'has_schedule_table' => true,
                    'paused_after_copy' => false,
                    'unreachable' => false,
                ],
                'tasks' => [$failed],
            ],
        ], $now);

        $byId = [];
        foreach ($result['tasks'] as $task) {
            $byId[$task['id']] = $task;
        }

        $this->assertSame('staging', $byId['task-staging']['environment']);
        $this->assertSame('staging-id', $byId['task-staging']['tenant_id']);
        $this->assertSame('staging.alliancetowers.com', $byId['task-staging']['domain']);
        $this->assertSame('overdue', $byId['task-staging']['status']);

        $this->assertSame('production', $byId['task-production']['environment']);
        $this->assertSame('production-id', $byId['task-production']['tenant_id']);
        $this->assertSame('paused', $byId['task-production']['status']);

        $this->assertSame('test', $byId['task-test']['environment']);
        $this->assertSame('failed', $byId['task-test']['status']);
        $this->assertSame('mailbox down', $byId['task-test']['last_error']);
        $this->assertSame('active', $byId['task-recent']['status']);

        $this->assertSame('task-test', $result['tasks'][0]['id']);
        $this->assertSame(1, $result['counts']['failed']);
        $this->assertSame(1, $result['counts']['overdue']);
        $this->assertSame(1, $result['counts']['paused']);
        $this->assertSame(1, $result['counts']['active']);
        $this->assertSame(3, $result['counts']['environments']);
        $this->assertTrue($result['tenants'][1]['paused_after_copy']);
        $this->assertFalse($result['tenants'][0]['paused_after_copy']);
    }

    /**
     * @return array{tenant_id: string, slug: string, environment: string, domain: string}
     */
    private function identity(string $id, string $environment, string $domain): array
    {
        return [
            'tenant_id' => $id,
            'slug' => 'atc',
            'environment' => $environment,
            'domain' => $domain,
        ];
    }
}
