<?php

declare(strict_types=1);

namespace Tests\Feature\AiAssistant;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\AiAssistant\Models\AiAssistantFeedback;
use App\Modules\AiAssistant\Models\AiConversation;
use App\Modules\AiAssistant\Models\AiMessage;
use App\Modules\AiAssistant\Support\AssistantAskStatus;
use App\Modules\AiAssistant\Support\AssistantMessageRole;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use App\Modules\Workspace\Models\TenantActivityLog;
use Illuminate\Support\Str;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class AssistantAskTest extends TestCase
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
            'ai_assistant.llm_provider' => 'local',
            'ai_assistant.embedding_provider' => 'local',
            'ai_assistant.vector_store' => 'database',
        ]);

        $this->bootInMemoryTenantApi();
    }

    public function test_ask_persists_conversation_and_messages(): void
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/ask', [
                'question' => 'How do I create an e-approval request?',
                'module_context' => 'e_approval',
                'page_path' => '/e-approval/submissions',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'insufficient_context')
            ->assertJsonPath('data.citations', [])
            ->assertJsonStructure([
                'data' => [
                    'conversation_id',
                    'message_id',
                    'answer',
                    'citations',
                    'suggested_followups',
                    'related_links',
                    'status',
                    'model_name',
                ],
            ]);

        $this->assertStringContainsString('enough approved help content', $response->json('data.answer'));

        $conversationId = $response->json('data.conversation_id');
        $messageId = $response->json('data.message_id');

        $this->assertTrue(Str::isUuid($conversationId));
        $this->assertTrue(Str::isUuid($messageId));

        tenancy()->initialize($this->testTenant);

        $conversation = AiConversation::query()->findOrFail($conversationId);
        $this->assertSame((string) $this->testTenantAdmin->id, (string) $conversation->user_id);
        $this->assertSame('e_approval', $conversation->module_context);
        $this->assertSame('/e-approval/submissions', $conversation->page_path);
        $this->assertSame(2, $conversation->messages()->count());

        $assistant = AiMessage::query()->findOrFail($messageId);
        $this->assertSame(AssistantMessageRole::ASSISTANT, $assistant->role);
        $this->assertSame(AssistantAskStatus::INSUFFICIENT_CONTEXT, $assistant->status);

        $this->assertTrue(
            TenantActivityLog::query()
                ->where('module', 'ai_assistant')
                ->where('action', 'assistant.ask')
                ->where('entity_id', $conversationId)
                ->exists(),
        );

        tenancy()->end();
    }

    public function test_ask_reuses_existing_conversation_id(): void
    {
        $first = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/ask', [
                'question' => 'What is ticketing?',
            ])
            ->assertOk();

        $conversationId = $first->json('data.conversation_id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/ask', [
                'question' => 'How do I create a ticket?',
                'conversation_id' => $conversationId,
            ])
            ->assertOk()
            ->assertJsonPath('data.conversation_id', $conversationId);

        tenancy()->initialize($this->testTenant);
        $this->assertSame(4, AiMessage::query()->where('conversation_id', $conversationId)->count());
        tenancy()->end();
    }

    public function test_ask_rejects_unknown_conversation_id(): void
    {
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/ask', [
                'question' => 'What is ticketing?',
                'conversation_id' => (string) Str::uuid(),
            ])
            ->assertNotFound();
    }

    public function test_ask_requires_question(): void
    {
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/ask', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['question']);
    }

    public function test_ask_forbidden_without_permission(): void
    {
        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();

        $user = TenantUser::query()->create([
            'name' => 'No Assistant Access',
            'email' => 'no-assistant@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->assignRole('billing');
        tenancy()->end();

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/ask', [
                'question' => 'How do I create a site?',
            ])
            ->assertForbidden();
    }

    public function test_ask_forbidden_when_module_disabled(): void
    {
        config([
            'toweros.tenant_modules.enabled' => [
                'core',
                'team_access',
            ],
        ]);

        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/ask', [
                'question' => 'How do I create a site?',
            ])
            ->assertForbidden();
    }

    public function test_conversations_index_and_show_enforce_ownership(): void
    {
        $ask = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/ask', [
                'question' => 'How do I open documents?',
            ])
            ->assertOk();

        $conversationId = $ask->json('data.conversation_id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/assistant/conversations')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $conversationId);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/assistant/conversations/'.$conversationId)
            ->assertOk()
            ->assertJsonPath('data.id', $conversationId)
            ->assertJsonCount(2, 'data.messages');

        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        $other = TenantUser::query()->create([
            'name' => 'Other User',
            'email' => 'other-assistant@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $other->assignRole('ai_assistant_user');
        tenancy()->end();

        $this->actingAs($other, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/assistant/conversations/'.$conversationId)
            ->assertForbidden();

        $this->actingAs($other, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/assistant/conversations')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_feedback_can_be_submitted_for_assistant_message(): void
    {
        $ask = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/ask', [
                'question' => 'How do I approve a request?',
            ])
            ->assertOk();

        $messageId = $ask->json('data.message_id');
        $conversationId = $ask->json('data.conversation_id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/feedback', [
                'message_id' => $messageId,
                'rating' => 'up',
                'comment' => 'Helpful placeholder',
            ])
            ->assertOk()
            ->assertJsonPath('data.rating', 'up')
            ->assertJsonPath('data.message_id', $messageId);

        tenancy()->initialize($this->testTenant);
        $this->assertTrue(
            AiAssistantFeedback::query()
                ->where('message_id', $messageId)
                ->where('conversation_id', $conversationId)
                ->where('rating', 'up')
                ->exists(),
        );
        $this->assertTrue(
            TenantActivityLog::query()
                ->where('module', 'ai_assistant')
                ->where('action', 'assistant.feedback')
                ->where('entity_id', $messageId)
                ->exists(),
        );
        tenancy()->end();
    }
}
