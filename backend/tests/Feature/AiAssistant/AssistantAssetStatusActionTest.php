<?php

declare(strict_types=1);

namespace Tests\Feature\AiAssistant;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\AiAssistant\Models\AiConversation;
use App\Modules\AiAssistant\Models\AiMessage;
use App\Modules\AiAssistant\Services\Actions\AssistantActionRouter;
use App\Modules\AssetManagement\Models\Asset;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Tests\Support\AiAssistant\BindsDeterministicAssistantLlm;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class AssistantAssetStatusActionTest extends TestCase
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
                'asset_management',
                'dynamic_entities',
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
        tenancy()->end();
    }

    public function test_status_question_does_not_propose_a_write(): void
    {
        $router = app(AssistantActionRouter::class);

        $this->assertNull($router->match('SN-DETAIL-1001 What is the status of this?'));
        $this->assertSame(
            'change_it_asset_status',
            $router->match('Can you change the status from AVAILABLE to LOST?')['action'] ?? null,
        );
        $this->assertSame(
            'seed_sample_it_assets',
            $router->match('Can you create a dummy 50 data for assets with different scenario?')['action'] ?? null,
        );
    }

    public function test_follow_up_proposes_lost_and_confirm_saves_it(): void
    {
        $created = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assets', [
                'serial_number' => 'SN-DETAIL-1001',
                'asset_type' => 'laptop',
                'manufacturer' => 'Dell',
                'product_name' => 'Latitude 5520',
                'status' => 'AVAILABLE',
            ]);
        $created->assertCreated();
        $this->assertSame('ATC-LAP-000001', $created->json('data.asset_tag'));

        tenancy()->initialize($this->testTenant);
        $conversation = AiConversation::query()->create([
            'user_id' => $this->testTenantAdmin->id,
            'title' => 'Asset status',
            'module_context' => 'asset_management',
            'page_path' => '/assets',
            'status' => 'active',
        ]);
        AiMessage::query()->create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'SN-DETAIL-1001 What is the status of this?',
            'status' => 'completed',
        ]);
        AiMessage::query()->create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'SN-DETAIL-1001 is AVAILABLE. It is laptop tag ATC-LAP-000001.',
            'status' => 'completed',
        ]);
        tenancy()->end();

        $ask = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/ask', [
                'question' => 'Can you change the status from AVAILABLE to LOST?',
                'conversation_id' => $conversation->id,
                'module_context' => 'asset_management',
                'page_path' => '/assets',
            ]);

        $ask->assertOk()
            ->assertJsonPath('data.answer', 'ATC-LAP-000001 is now LOST')
            ->assertJsonPath('data.proposed_action', null);

        tenancy()->initialize($this->testTenant);
        $this->assertSame('LOST', Asset::query()->where('asset_tag', 'ATC-LAP-000001')->value('status'));
        tenancy()->end();
    }

    public function test_dummy_asset_request_creates_a_mixed_sample_batch(): void
    {
        $ask = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/ask', [
                'question' => 'Can you create a dummy 3 data for assets with different scenario?',
                'module_context' => 'asset_management',
                'page_path' => '/assets',
            ]);

        $ask->assertOk()
            ->assertJsonPath('data.proposed_action', null);

        $answer = (string) $ask->json('data.answer');
        $this->assertStringContainsString('Created 3 sample assets', $answer);

        tenancy()->initialize($this->testTenant);
        $assets = Asset::query()->where('serial_number', 'like', 'SN-SEED-%')->get();
        $this->assertCount(3, $assets);
        $this->assertSame(
            ['ASSIGNED', 'AVAILABLE', 'NEW'],
            $assets->pluck('status')->sort()->values()->all(),
        );
        tenancy()->end();
    }
}
