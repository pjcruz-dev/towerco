<?php

declare(strict_types=1);

namespace Tests\Feature\AiAssistant;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\AiAssistant\Models\AiKnowledgeChunk;
use App\Modules\AiAssistant\Services\KnowledgeCatalogService;
use App\Modules\AiAssistant\Services\KnowledgeIngestionService;
use App\Modules\AiAssistant\Services\KnowledgeRetrievalService;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class KnowledgeRagTest extends TestCase
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
            'ai_assistant.retrieval.min_score' => 0.01,
            'ai_assistant.retrieval.top_k' => 5,
        ]);

        $this->bootInMemoryTenantApi();
    }

    public function test_ingest_and_retrieve_respects_permissions(): void
    {
        Artisan::call('ai-assistant:sync-global-knowledge', [
            '--tenant' => $this->testTenant->id,
        ]);

        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();

        $results = app(KnowledgeIngestionService::class)->ingestPublishedSources();
        $this->assertNotEmpty($results);
        $this->assertGreaterThan(0, AiKnowledgeChunk::query()->whereNotNull('embedding')->count());

        $adminHits = app(KnowledgeRetrievalService::class)->retrieve(
            $this->testTenantAdmin,
            'How do I create an e-approval request?',
            5,
        );
        $this->assertNotEmpty($adminHits);
        $this->assertTrue(
            collect($adminHits)->contains(
                fn ($chunk) => str_contains(mb_strtolower($chunk->title.' '.$chunk->content), 'e-approval')
                    || $chunk->slug === 'e-approval-create-request',
            ),
        );

        $viewer = TenantUser::query()->create([
            'name' => 'Sites Viewer Only',
            'email' => 'sites-viewer@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $viewer->assignRole('sites_viewer');
        $viewer->givePermissionTo('ai_assistant:use');

        $viewerHits = app(KnowledgeRetrievalService::class)->retrieve(
            $viewer,
            'How do I create an e-approval request?',
            5,
        );

        foreach ($viewerHits as $hit) {
            $this->assertNotSame('e-approval-create-request', $hit->slug);
            $this->assertFalse(in_array('e_approval:submissions:create', $hit->permissions, true));
        }

        tenancy()->end();
    }

    public function test_retrieve_api_returns_citations(): void
    {
        Artisan::call('ai-assistant:sync-global-knowledge', [
            '--tenant' => $this->testTenant->id,
        ]);
        Artisan::call('ai-assistant:ingest-knowledge', [
            '--tenant' => $this->testTenant->id,
            '--sync' => true,
        ]);

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/retrieve', [
                'query' => 'command palette navigation keyboard',
                'top_k' => 3,
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'query',
                    'chunks',
                    'citations',
                ],
            ]);

        $this->assertNotEmpty($response->json('data.citations'));
    }

    public function test_disabled_module_content_is_not_returned(): void
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
        app(KnowledgeCatalogService::class)->syncGlobalSourcesToCurrentTenant();
        app(KnowledgeIngestionService::class)->ingestPublishedSources();

        $hits = app(KnowledgeRetrievalService::class)->retrieve(
            $this->testTenantAdmin,
            'How do I create an e-approval request?',
            8,
        );

        foreach ($hits as $hit) {
            $this->assertNotSame('e_approval', $hit->moduleKey);
        }

        tenancy()->end();
    }
}
