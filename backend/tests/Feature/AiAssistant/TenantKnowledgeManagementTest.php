<?php

declare(strict_types=1);

namespace Tests\Feature\AiAssistant;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\AiAssistant\Jobs\IngestKnowledgeSourceJob;
use App\Modules\AiAssistant\Models\AiKnowledgeChunk;
use App\Modules\AiAssistant\Models\AiKnowledgeSource;
use App\Modules\AiAssistant\Services\KnowledgeRetrievalService;
use App\Modules\AiAssistant\Support\AssistantKnowledgeScope;
use App\Modules\AiAssistant\Support\AssistantKnowledgeStatus;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use App\Modules\Workspace\Models\TenantActivityLog;
use Illuminate\Support\Facades\Bus;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class TenantKnowledgeManagementTest extends TestCase
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
            'ai_assistant.retrieval.min_score' => 0.01,
            'ai_assistant.retrieval.top_k' => 5,
            'queue.default' => 'sync',
        ]);

        $this->bootInMemoryTenantApi();
    }

    public function test_knowledge_manage_requires_permission(): void
    {
        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();

        $viewer = TenantUser::query()->create([
            'name' => 'Viewer',
            'email' => 'viewer-knowledge@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $viewer->assignRole('viewer');
        $viewer->givePermissionTo('ai_assistant:use');
        tenancy()->end();

        $this->actingAs($viewer, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/assistant/knowledge')
            ->assertForbidden();
    }

    public function test_create_publish_ingest_and_retrieve_tenant_sop(): void
    {
        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/knowledge', [
                'title' => 'Night shift escalation SOP',
                'body' => "When a tower alarm is critical after 22:00, page the NOC lead within 5 minutes and open a P1 ticket.\n\nDo not wait for morning handoff.",
                'slug' => 'night-shift-escalation',
                'related_routes' => ['/ticketing'],
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.scope', 'tenant')
            ->assertJsonPath('data.slug', 'night-shift-escalation');

        $sourceId = (string) $create->json('data.id');

        $publish = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/knowledge/'.$sourceId.'/publish?sync=1');

        $publish->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.version', 1);

        tenancy()->initialize($this->testTenant);
        $this->assertSame(AssistantKnowledgeStatus::PUBLISHED, AiKnowledgeSource::query()->findOrFail($sourceId)->status);
        $this->assertGreaterThan(0, AiKnowledgeChunk::query()->where('knowledge_source_id', $sourceId)->count());
        $this->assertNotNull(AiKnowledgeSource::query()->findOrFail($sourceId)->last_indexed_at);

        $hits = app(KnowledgeRetrievalService::class)->retrieve(
            $this->testTenantAdmin,
            'night shift escalation NOC lead P1 ticket after 22:00',
            5,
        );

        $this->assertTrue(
            TenantActivityLog::query()
                ->where('action', 'assistant.knowledge.publish')
                ->where('entity_id', $sourceId)
                ->exists()
        );
        tenancy()->end();

        $this->assertNotEmpty($hits);
        $this->assertTrue(
            collect($hits)->contains(
                fn ($chunk) => $chunk->slug === 'night-shift-escalation'
                    || str_contains(mb_strtolower($chunk->content), 'noc lead'),
            ),
        );
    }

    public function test_archive_removes_chunks_from_retrieval(): void
    {
        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/knowledge', [
                'title' => 'Temporary SOP',
                'body' => 'Unique phrase zxqy-tenant-sop-archive-test for retrieval.',
            ])
            ->assertCreated();

        $sourceId = (string) $create->json('data.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/knowledge/'.$sourceId.'/publish?sync=1')
            ->assertOk();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/knowledge/'.$sourceId.'/archive')
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        tenancy()->initialize($this->testTenant);
        $this->assertSame(0, AiKnowledgeChunk::query()->where('knowledge_source_id', $sourceId)->count());
        $hits = app(KnowledgeRetrievalService::class)->retrieve(
            $this->testTenantAdmin,
            'zxqy-tenant-sop-archive-test',
            5,
        );
        tenancy()->end();

        foreach ($hits as $hit) {
            $this->assertNotSame($sourceId, $hit->sourceId);
        }
    }

    public function test_update_published_returns_to_draft_and_reindex_dispatches_job(): void
    {
        Bus::fake([IngestKnowledgeSourceJob::class]);

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/knowledge', [
                'title' => 'Draft SOP',
                'body' => 'Initial body content for tenant knowledge.',
            ])
            ->assertCreated();

        $sourceId = (string) $create->json('data.id');

        // Publish without sync so job is dispatched (Bus fake).
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/knowledge/'.$sourceId.'/publish')
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        Bus::assertDispatched(IngestKnowledgeSourceJob::class);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson('/api/v1/assistant/knowledge/'.$sourceId, [
                'body' => 'Updated body content for tenant knowledge.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        tenancy()->initialize($this->testTenant);
        $source = AiKnowledgeSource::query()->findOrFail($sourceId);
        $this->assertSame(AssistantKnowledgeScope::TENANT, $source->scope);
        $this->assertSame(AssistantKnowledgeStatus::DRAFT, $source->status);
        tenancy()->end();

        Bus::fake([IngestKnowledgeSourceJob::class]);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/knowledge/'.$sourceId.'/publish')
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.version', 2);

        Bus::assertDispatched(IngestKnowledgeSourceJob::class);
    }

    public function test_list_only_returns_tenant_scope_sources(): void
    {
        tenancy()->initialize($this->testTenant);
        AiKnowledgeSource::query()->create([
            'slug' => 'global-only',
            'scope' => AssistantKnowledgeScope::GLOBAL,
            'title' => 'Global article',
            'source_type' => 'markdown',
            'body' => 'Should not appear in tenant knowledge list.',
            'status' => AssistantKnowledgeStatus::PUBLISHED,
            'version' => 1,
            'audience' => 'tenant_user',
        ]);
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/knowledge', [
                'title' => 'Tenant article',
                'body' => 'Tenant managed SOP content.',
            ])
            ->assertCreated();

        $list = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/assistant/knowledge')
            ->assertOk();

        $rows = $list->json('data');
        $this->assertIsArray($rows);
        foreach ($rows as $row) {
            $this->assertSame('tenant', $row['scope']);
            $this->assertNotSame('global-only', $row['slug']);
        }
    }
}
