<?php

declare(strict_types=1);

namespace Tests\Feature\AiAssistant;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\AiAssistant\Models\AiConversation;
use App\Modules\AiAssistant\Models\AiMessage;
use App\Modules\AiAssistant\Support\AssistantConversationStatus;
use App\Modules\AiAssistant\Support\AssistantMessageRole;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Str;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class AssistantConversationCrudTest extends TestCase
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
        ]);

        $this->bootInMemoryTenantApi();
    }

    public function test_owner_can_rename_archive_and_export_conversation(): void
    {
        tenancy()->initialize($this->testTenant);

        $conversation = AiConversation::query()->create([
            'user_id' => $this->testTenantAdmin->id,
            'title' => 'Original title',
            'status' => AssistantConversationStatus::ACTIVE,
            'last_message_at' => now(),
        ]);

        AiMessage::query()->create([
            'conversation_id' => $conversation->id,
            'role' => AssistantMessageRole::USER,
            'content' => 'Hello',
            'status' => 'completed',
        ]);

        tenancy()->end();

        $rename = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->patchJson('/api/v1/assistant/conversations/'.$conversation->id, [
                'title' => 'Renamed chat',
            ]);

        $rename->assertOk()
            ->assertJsonPath('data.title', 'Renamed chat');

        $export = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/assistant/conversations/'.$conversation->id.'/export?format=json');

        $export->assertOk()
            ->assertJsonPath('data.conversation.title', 'Renamed chat')
            ->assertJsonPath('data.conversation.messages.0.content', 'Hello');

        $delete = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->deleteJson('/api/v1/assistant/conversations/'.$conversation->id);

        $delete->assertNoContent();

        tenancy()->initialize($this->testTenant);
        $conversation->refresh();
        $this->assertSame(AssistantConversationStatus::ARCHIVED, $conversation->status);
        tenancy()->end();

        $list = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/assistant/conversations?status=active');

        $list->assertOk();
        $this->assertSame([], $list->json('data'));
    }

    public function test_search_filters_conversations_by_title(): void
    {
        tenancy()->initialize($this->testTenant);

        AiConversation::query()->create([
            'user_id' => $this->testTenantAdmin->id,
            'title' => 'Tower inventory report',
            'status' => AssistantConversationStatus::ACTIVE,
            'last_message_at' => now(),
        ]);
        AiConversation::query()->create([
            'user_id' => $this->testTenantAdmin->id,
            'title' => 'Unrelated topic',
            'status' => AssistantConversationStatus::ACTIVE,
            'last_message_at' => now()->subMinute(),
        ]);

        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/assistant/conversations?search=inventory');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Tower inventory report', $response->json('data.0.title'));
    }

    public function test_audit_user_sees_other_users_conversations(): void
    {
        tenancy()->initialize($this->testTenant);

        $otherUser = TenantUser::query()->create([
            'name' => 'Other User',
            'email' => 'other-'.Str::lower(Str::random(6)).'@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);

        AiConversation::query()->create([
            'user_id' => $otherUser->id,
            'title' => 'Other user chat',
            'status' => AssistantConversationStatus::ACTIVE,
            'last_message_at' => now(),
        ]);

        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/assistant/conversations?search=Other');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Other User', $response->json('data.0.user_name'));
    }
}
