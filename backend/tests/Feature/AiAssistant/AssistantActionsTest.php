<?php

declare(strict_types=1);

namespace Tests\Feature\AiAssistant;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Models\TicketingTicket;
use App\Modules\AiAssistant\Models\AiAssistantProposedAction;
use App\Modules\AiAssistant\Support\AssistantProposedActionStatus;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use App\Modules\Workspace\Models\TenantActivityLog;
use Tests\Support\AiAssistant\BindsDeterministicAssistantLlm;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class AssistantActionsTest extends TestCase
{
    use BindsDeterministicAssistantLlm;
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
                'ticketing',
                'e_approval',
                'documents',
                'sites',
            ],
            'ai_assistant.enabled' => true,
            'ai_assistant.embedding_provider' => 'local',
            'ai_assistant.vector_store' => 'database',
            'ai_assistant.llm_provider' => 'cursor',
            'ai_assistant.tools.enabled' => true,
            'ai_assistant.actions.enabled' => true,
            'queue.default' => 'sync',
        ]);

        $this->bindDeterministicAssistantLlm();
        $this->bootInMemoryTenantApi();

        $this->testTenant->plan_tier = 'enterprise';
        $this->testTenant->save();

        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        tenancy()->end();
    }

    public function test_ask_proposes_draft_ticket_without_creating_ticket(): void
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/ask', [
                'question' => 'Create a ticket for generator fuel low at site PH-NCR-001',
                'module_context' => 'ticketing',
                'page_path' => '/ticketing',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.proposed_action.action', 'draft_ticket');
        $this->assertStringContainsString('Nothing has been saved yet', (string) $response->json('data.answer'));

        tenancy()->initialize($this->testTenant);
        $this->assertSame(0, TicketingTicket::query()->count());
        $this->assertSame(1, AiAssistantProposedAction::query()->where('action', 'draft_ticket')->where('status', 'pending')->count());
        $this->assertTrue(
            TenantActivityLog::query()
                ->where('action', 'assistant.action.propose')
                ->exists(),
        );
        tenancy()->end();
    }

    public function test_confirm_creates_ticket_and_cancel_does_not(): void
    {
        $ask = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/ask', [
                'question' => 'Create a ticket for HVAC alarm on rooftop cabinet',
            ]);

        $ask->assertOk()
            ->assertJsonPath('data.proposed_action.action', 'draft_ticket');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/actions/confirm', [
                'proposal_id' => $ask->json('data.proposed_action.id'),
                'payload' => $ask->json('data.proposed_action.payload'),
            ])
            ->assertOk();

        tenancy()->initialize($this->testTenant);
        $this->assertSame(1, TicketingTicket::query()->count());
        $ticket = TicketingTicket::query()->firstOrFail();
        $this->assertSame('ai_assistant', $ticket->source_module);
        $this->assertTrue(
            TenantActivityLog::query()
                ->where('action', 'assistant.action.confirm')
                ->exists(),
        );
        tenancy()->end();
    }

    public function test_confirm_denied_without_actions_execute_permission(): void
    {
        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();

        $user = TenantUser::query()->create([
            'name' => 'Propose Only',
            'email' => 'propose-only@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->assignRole('viewer');
        $user->givePermissionTo([
            'ai_assistant:use',
            'ai_assistant:tools:use',
            'ticketing:tickets:create',
        ]);
        // Intentionally no ai_assistant:actions:execute

        $proposal = AiAssistantProposedAction::query()->create([
            'user_id' => $user->id,
            'action' => 'draft_ticket',
            'status' => AssistantProposedActionStatus::PENDING,
            'payload' => [
                'title' => 'Should not create',
                'description' => null,
                'category' => 'general',
                'source_module' => 'ai_assistant',
                'source_label' => 'Ask INFRA SUITE',
            ],
            'preview' => [
                'title' => 'Create ticket',
                'summary' => 'Test',
                'preview' => [],
                'editable_fields' => [],
                'confirm_label' => 'Create ticket',
                'module_key' => 'ticketing',
            ],
            'expires_at' => now()->addMinutes(30),
        ]);
        tenancy()->end();

        $response = $this->actingAs($user, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/actions/confirm', [
                'proposal_id' => (string) $proposal->id,
            ]);

        $response->assertForbidden();

        tenancy()->initialize($this->testTenant);
        $this->assertSame(0, TicketingTicket::query()->count());
        $this->assertSame(
            AssistantProposedActionStatus::PENDING,
            AiAssistantProposedAction::query()->findOrFail($proposal->id)->status,
        );
        tenancy()->end();
    }

    public function test_actions_feature_flag_skips_proposal(): void
    {
        config(['ai_assistant.actions.enabled' => false]);

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/ask', [
                'question' => 'Create a ticket for power outage',
            ]);

        $response->assertOk();
        $this->assertNull($response->json('data.proposed_action'));

        tenancy()->initialize($this->testTenant);
        $this->assertSame(0, AiAssistantProposedAction::query()->count());
        $this->assertSame(0, TicketingTicket::query()->count());
        tenancy()->end();
    }

    public function test_unregistered_action_cannot_be_confirmed(): void
    {
        tenancy()->initialize($this->testTenant);
        $proposal = AiAssistantProposedAction::query()->create([
            'user_id' => $this->testTenantAdmin->id,
            'action' => 'delete_everything',
            'status' => AssistantProposedActionStatus::PENDING,
            'payload' => ['title' => 'x'],
            'preview' => [],
            'expires_at' => now()->addMinutes(30),
        ]);
        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/actions/confirm', [
                'proposal_id' => (string) $proposal->id,
            ]);

        $response->assertStatus(422);

        tenancy()->initialize($this->testTenant);
        $this->assertSame(0, TicketingTicket::query()->count());
        tenancy()->end();
    }
}
