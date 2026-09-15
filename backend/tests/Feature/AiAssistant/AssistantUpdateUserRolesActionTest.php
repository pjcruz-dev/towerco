<?php

declare(strict_types=1);

namespace Tests\Feature\AiAssistant;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\AiAssistant\Models\AiAssistantProposedAction;
use App\Modules\AiAssistant\Support\AssistantProposedActionStatus;
use App\Modules\Identity\Models\TenantUser;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class AssistantUpdateUserRolesActionTest extends TestCase
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
            'ai_assistant.embedding_provider' => 'local',
            'ai_assistant.vector_store' => 'database',
            'ai_assistant.llm_provider' => 'local',
            'ai_assistant.tools.enabled' => false,
            'ai_assistant.actions.enabled' => true,
            'ai_assistant.tools.fallback_planner_enabled' => false,
            'ai_assistant.retrieval.enabled' => false,
            'queue.default' => 'sync',
        ]);

        $this->bootInMemoryTenantApi();

        $this->testTenant->plan_tier = 'enterprise';
        $this->testTenant->save();

        tenancy()->initialize($this->testTenant);
        TenantUser::query()->create([
            'name' => 'Dearquiza',
            'email' => 'dearquiza@alliancetowers.com',
            'password' => 'password',
            'is_active' => true,
        ])->assignRole('manager');
        tenancy()->end();
    }

    public function test_ask_proposes_role_update_without_saving(): void
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/ask', [
                'question' => 'dearquiza@alliancetowers.com change the role to viewer only',
                'module_context' => 'team_access',
                'page_path' => '/users',
                'plan_mode' => false,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.proposed_action.action', 'update_user_roles')
            ->assertJsonPath('data.proposed_action.status', 'pending')
            ->assertJsonPath('data.proposed_action.requires_confirmation', true);

        tenancy()->initialize($this->testTenant);
        $target = TenantUser::query()->where('email', 'dearquiza@alliancetowers.com')->firstOrFail();
        $this->assertTrue($target->hasRole('manager'));
        $this->assertFalse($target->hasRole('viewer'));
        tenancy()->end();
    }

    public function test_confirm_replaces_roles_with_viewer_only(): void
    {
        $ask = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/ask', [
                'question' => 'dearquiza@alliancetowers.com change the role to viewer only',
                'module_context' => 'team_access',
            ]);

        $ask->assertOk();
        $proposalId = (string) $ask->json('data.proposed_action.id');
        $this->assertNotSame('', $proposalId);

        $confirm = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/actions/confirm', [
                'proposal_id' => $proposalId,
            ]);

        $confirm->assertOk()
            ->assertJsonPath('data.result.entity_type', 'tenant_user')
            ->assertJsonPath('data.result.entity_label', 'dearquiza@alliancetowers.com');

        tenancy()->initialize($this->testTenant);
        $target = TenantUser::query()->where('email', 'dearquiza@alliancetowers.com')->firstOrFail();
        $this->assertSame(['viewer'], $target->getRoleNames()->sort()->values()->all());
        $this->assertSame(
            AssistantProposedActionStatus::CONFIRMED,
            AiAssistantProposedAction::query()->findOrFail($proposalId)->status,
        );
        tenancy()->end();
    }

    public function test_proceed_resurfaces_pending_role_proposal(): void
    {
        $ask = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/ask', [
                'question' => 'dearquiza@alliancetowers.com change the role to viewer only',
                'module_context' => 'team_access',
            ]);

        $ask->assertOk();
        $conversationId = (string) $ask->json('data.conversation_id');
        $proposalId = (string) $ask->json('data.proposed_action.id');

        $proceed = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/ask', [
                'question' => 'proceed',
                'conversation_id' => $conversationId,
                'module_context' => 'team_access',
            ]);

        $proceed->assertOk()
            ->assertJsonPath('data.proposed_action.id', $proposalId)
            ->assertJsonPath('data.proposed_action.action', 'update_user_roles');
    }
}
