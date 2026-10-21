<?php

declare(strict_types=1);

namespace Tests\Feature\AiAssistant;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\AiAssistant\Services\Actions\AssistantActionRouter;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Tests\Support\AiAssistant\BindsDeterministicAssistantLlm;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class AssistantSetUserActiveTest extends TestCase
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
            ],
            'ai_assistant.enabled' => true,
            'ai_assistant.embedding_provider' => 'local',
            'ai_assistant.vector_store' => 'database',
            'ai_assistant.llm_provider' => 'cursor',
            'ai_assistant.tools.enabled' => true,
            'ai_assistant.actions.enabled' => true,
        ]);

        $this->bindDeterministicAssistantLlm();
        $this->bootInMemoryTenantApi();
        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        TenantUser::query()->create([
            'name' => 'App Angandaman',
            'email' => 'appangandaman@alliancetowers.com',
            'password' => 'password',
            'is_active' => true,
        ])->assignRole('viewer');
        tenancy()->end();
    }

    public function test_inactive_sentence_matches_the_user_action(): void
    {
        $match = app(AssistantActionRouter::class)->match('set appangandaman@alliancetowers.com to inactive');

        $this->assertSame('set_user_active', $match['action'] ?? null);
    }

    public function test_admin_ask_sets_the_user_inactive(): void
    {
        $ask = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/ask', [
                'question' => 'set appangandaman@alliancetowers.com to inactive',
                'module_context' => 'team_access',
                'page_path' => '/admin/users',
            ]);

        $ask->assertOk()
            ->assertJsonPath('data.proposed_action.action', 'set_user_active');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/actions/confirm', [
                'proposal_id' => $ask->json('data.proposed_action.id'),
                'payload' => $ask->json('data.proposed_action.payload'),
            ])
            ->assertOk();

        tenancy()->initialize($this->testTenant);
        $user = TenantUser::query()->where('email', 'appangandaman@alliancetowers.com')->first();
        $this->assertInstanceOf(TenantUser::class, $user);
        $this->assertFalse($user->isActive());
        tenancy()->end();
    }
}
