<?php

declare(strict_types=1);

namespace Tests\Feature\AiAssistant;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\AiAssistant\Support\AssistantAskStatus;
use App\Modules\AiAssistant\Support\PromptSecurityService;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class AssistantAskLlmTest extends TestCase
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
                'e_approval',
                'sites',
            ],
            'ai_assistant.enabled' => true,
            'ai_assistant.embedding_provider' => 'local',
            'ai_assistant.vector_store' => 'database',
            'ai_assistant.llm_provider' => 'local',
            'ai_assistant.retrieval.min_score' => 0.01,
            'ai_assistant.retrieval.top_k' => 5,
        ]);

        $this->bootInMemoryTenantApi();
    }

    public function test_ask_returns_grounded_answer_with_citations_after_ingest(): void
    {
        $this->syncAndIngest();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/ask', [
                'question' => 'How do I create an e-approval request?',
                'module_context' => 'e_approval',
                'page_path' => '/e-approval/submissions/new',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', AssistantAskStatus::COMPLETED)
            ->assertJsonStructure([
                'data' => [
                    'answer',
                    'citations',
                    'suggested_followups',
                    'related_links',
                    'model_name',
                ],
            ]);

        $this->assertNotSame('', $response->json('data.answer'));
        $this->assertNotEmpty($response->json('data.citations'));
        $this->assertTrue(
            collect($response->json('data.citations'))->contains(
                fn (array $citation): bool => ($citation['slug'] ?? null) === 'e-approval-create-request'
                    || str_contains(mb_strtolower((string) ($citation['title'] ?? '')), 'e-approval'),
            ),
        );
        $this->assertNotEmpty($response->json('data.related_links'));
    }

    public function test_ask_excludes_content_without_permission(): void
    {
        $this->syncAndIngest();

        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        $viewer = TenantUser::query()->create([
            'name' => 'Sites Only Assistant User',
            'email' => 'sites-ask@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $viewer->assignRole('sites_viewer');
        $viewer->givePermissionTo('ai_assistant:use');
        tenancy()->end();

        $response = $this->actingAs($viewer, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/ask', [
                'question' => 'How do I create an e-approval request?',
            ])
            ->assertOk();

        $slugs = collect($response->json('data.citations'))->pluck('slug')->filter()->all();
        $this->assertNotContains('e-approval-create-request', $slugs);
        $this->assertNotContains('e-approval-approve-request', $slugs);
    }

    public function test_ask_excludes_disabled_module_content(): void
    {
        config([
            'toweros.tenant_modules.enabled' => [
                'core',
                'team_access',
                'ai_assistant',
            ],
        ]);

        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        tenancy()->end();

        $this->syncAndIngest();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/ask', [
                'question' => 'How do I create an e-approval request?',
            ])
            ->assertOk();

        $modules = collect($response->json('data.citations'))->pluck('module')->filter()->all();
        $this->assertNotContains('e_approval', $modules);
    }

    public function test_ask_returns_insufficient_context_when_no_knowledge_indexed(): void
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/ask', [
                'question' => 'How do I teleport between galaxies?',
            ])
            ->assertOk();

        $response->assertJsonPath('data.status', AssistantAskStatus::INSUFFICIENT_CONTEXT)
            ->assertJsonPath('data.citations', []);
        $this->assertStringContainsString('enough approved help content', $response->json('data.answer'));
        $this->assertNotEmpty($response->json('data.suggested_followups'));
    }

    public function test_prompt_security_redacts_secrets(): void
    {
        $service = app(PromptSecurityService::class);
        $redacted = $service->sanitizeUserText('password=SuperSecret123 and Bearer abcdefghijklmnop');

        $this->assertStringContainsString('[REDACTED]', $redacted);
        $this->assertStringNotContainsString('SuperSecret123', $redacted);
        $this->assertStringNotContainsString('abcdefghijklmnop', $redacted);
    }

    private function syncAndIngest(): void
    {
        Artisan::call('ai-assistant:sync-global-knowledge', [
            '--tenant' => $this->testTenant->id,
        ]);
        Artisan::call('ai-assistant:ingest-knowledge', [
            '--tenant' => $this->testTenant->id,
            '--sync' => true,
        ]);
    }
}
