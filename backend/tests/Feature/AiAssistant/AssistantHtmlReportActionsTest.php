<?php

declare(strict_types=1);

namespace Tests\Feature\AiAssistant;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\AiAssistant\Models\AiAssistantProposedAction;
use App\Modules\AiAssistant\Support\AssistantProposedActionStatus;
use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynHtmlReport;
use App\Modules\Identity\Models\UserUiPreference;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class AssistantHtmlReportActionsTest extends TestCase
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
                'dynamic_entities',
            ],
            'ai_assistant.enabled' => true,
            'ai_assistant.embedding_provider' => 'local',
            'ai_assistant.vector_store' => 'database',
            'ai_assistant.llm_provider' => 'local',
            'ai_assistant.tools.enabled' => true,
            'ai_assistant.actions.enabled' => true,
            'ai_assistant.tools.fallback_planner_enabled' => false,
            'queue.default' => 'sync',
        ]);

        $this->bootInMemoryTenantApi();

        $this->testTenant->plan_tier = 'enterprise';
        $this->testTenant->save();

        tenancy()->initialize($this->testTenant);
        DynEntity::query()->create([
            'slug' => 'general_ledger',
            'name' => 'General Ledger',
            'module_pack' => 'finance',
            'storage_mode' => 'json',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        tenancy()->end();
    }

    public function test_ask_proposes_html_report_without_saving(): void
    {
        // Avoid read-only tools that gate on dashboard:view during this ask path.
        config(['ai_assistant.tools.enabled' => false]);

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/ask', [
                'question' => 'Build a report of general ledger by status as a bar chart',
                'module_context' => 'dynamic_entities',
                'page_path' => '/dynamic-entities/report-builder',
                'plan_mode' => false,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.proposed_action.action', 'create_html_report_from_prompt')
            ->assertJsonPath('data.proposed_action.status', 'pending')
            ->assertJsonPath('data.proposed_action.requires_confirmation', true);

        tenancy()->initialize($this->testTenant);
        $this->assertSame(0, DynHtmlReport::query()->count());
        tenancy()->end();
    }

    public function test_confirm_creates_html_report_and_can_pin(): void
    {
        config(['ai_assistant.tools.enabled' => false]);

        $ask = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/ask', [
                'question' => 'Create a dashboard for general ledger with filter',
                'module_context' => 'dynamic_entities',
                'page_path' => '/dashboard',
            ]);

        $ask->assertOk();
        $proposalId = (string) $ask->json('data.proposed_action.id');
        $this->assertNotSame('', $proposalId);

        $payload = $ask->json('data.proposed_action.payload');
        $this->assertIsArray($payload);
        $payload['pin_to_dashboard'] = true;
        $payload['title'] = 'GL Dashboard';

        $confirm = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/actions/confirm', [
                'proposal_id' => $proposalId,
                'payload' => $payload,
            ]);

        $confirm->assertOk()
            ->assertJsonPath('data.result.ok', true)
            ->assertJsonPath('data.result.entity_type', 'dyn_html_report');

        $href = (string) $confirm->json('data.result.href');
        $this->assertStringContainsString('/dynamic-entities/html-reports/', $href);

        tenancy()->initialize($this->testTenant);
        $report = DynHtmlReport::query()->first();
        $this->assertNotNull($report);
        $this->assertSame('GL Dashboard', $report->name);
        $this->assertIsArray($report->builder_json);

        $proposal = AiAssistantProposedAction::query()->find($proposalId);
        $this->assertNotNull($proposal);
        $this->assertSame(AssistantProposedActionStatus::CONFIRMED, $proposal->status);

        $pref = UserUiPreference::query()
            ->where('preference_key', 'dashboard-layout.toweros.workspace.dashboard.layout')
            ->first();
        $this->assertNotNull($pref);
        $value = is_array($pref->value_json) ? $pref->value_json : [];
        $widgetId = 'dyn_html_report~'.(string) $report->slug;
        $this->assertContains($widgetId, $value['enabledWidgetIds'] ?? []);
        tenancy()->end();
    }

    public function test_confirm_denied_without_html_reports_permission(): void
    {
        config(['ai_assistant.tools.enabled' => false]);

        tenancy()->initialize($this->testTenant);
        $viewer = \App\Modules\Identity\Models\TenantUser::query()->create([
            'name' => 'Viewer',
            'email' => 'viewer@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $viewer->assignRole('viewer');
        tenancy()->end();

        $ask = $this->actingAs($viewer, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/ask', [
                'question' => 'Build a report of general ledger totals',
                'module_context' => 'dynamic_entities',
            ]);

        // Viewer may lack tools:use / html_reports — proposal should be absent.
        $ask->assertOk();
        $this->assertNull($ask->json('data.proposed_action'));
    }
}
