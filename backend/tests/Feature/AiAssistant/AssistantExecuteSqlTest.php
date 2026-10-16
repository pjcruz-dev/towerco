<?php

declare(strict_types=1);

namespace Tests\Feature\AiAssistant;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\AiAssistant\Services\AssistantOperationCatalog;
use App\Modules\AiAssistant\Services\Tools\ExecuteSqlTool;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use App\Modules\Workspace\Models\TenantActivityLog;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class AssistantExecuteSqlTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureMfaVerified::class,
            EnsureActiveSession::class,
        ]);

        config([
            'toweros.tenant_modules.enabled' => [
                'core',
                'team_access',
                'ai_assistant',
            ],
            'ai_assistant.enabled' => true,
            'ai_assistant.actions.enabled' => true,
            'ai_assistant.tools.enabled' => true,
        ]);

        $this->bootInMemoryTenantApi();
        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
    }

    public function test_admin_can_run_sql_on_the_tenant_database(): void
    {
        $names = array_column(app(AssistantOperationCatalog::class)->forViewer($this->testTenantAdmin), 'name');
        $this->assertContains('execute_sql', $names);

        $tool = app(ExecuteSqlTool::class);
        $created = $tool->execute($this->testTenantAdmin, [
            'sql' => 'CREATE TABLE assistant_sql_probe (id integer primary key, label varchar(20))',
        ], 10);
        $this->assertTrue($created->ok, (string) $created->error);

        $inserted = $tool->execute($this->testTenantAdmin, [
            'sql' => "INSERT INTO assistant_sql_probe (label) VALUES ('sample')",
        ], 10);
        $this->assertTrue($inserted->ok, (string) $inserted->error);
        $this->assertSame(1, $inserted->data['affected'] ?? null);

        $selected = $tool->execute($this->testTenantAdmin, [
            'sql' => 'SELECT label FROM assistant_sql_probe',
        ], 10);
        $this->assertTrue($selected->ok, (string) $selected->error);
        $this->assertSame('sample', $selected->data['rows'][0]['label'] ?? null);

        $otherDatabase = $tool->execute($this->testTenantAdmin, [
            'sql' => 'SELECT * FROM mysql.user',
        ], 10);
        $this->assertFalse($otherDatabase->ok);

        $batch = $tool->execute($this->testTenantAdmin, [
            'sql' => "UPDATE assistant_sql_probe SET label = 'batch' WHERE label = 'sample'; SELECT label FROM assistant_sql_probe",
        ], 10);
        $this->assertTrue($batch->ok, (string) $batch->error);
        $this->assertSame('batch', $batch->data['rows'][0]['label'] ?? null);

        $otherInBatch = $tool->execute($this->testTenantAdmin, [
            'sql' => 'SELECT 1; SELECT * FROM mysql.user',
        ], 10);
        $this->assertFalse($otherInBatch->ok);

        $wipe = $tool->execute($this->testTenantAdmin, [
            'sql' => 'DELETE FROM assistant_sql_probe',
        ], 10);
        $this->assertFalse($wipe->ok);
        $this->assertStringContainsString('WHERE', (string) $wipe->error);

        $this->assertTrue(
            TenantActivityLog::query()
                ->where('action', 'assistant.sql.write')
                ->where('actor_user_id', (string) $this->testTenantAdmin->id)
                ->exists(),
        );
        $this->assertTrue(
            TenantActivityLog::query()
                ->where('action', 'assistant.sql.rejected')
                ->where('actor_user_id', (string) $this->testTenantAdmin->id)
                ->exists(),
        );
    }
}
