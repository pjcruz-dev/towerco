<?php

declare(strict_types=1);

namespace Tests\Feature\AiAssistant;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\AiAssistant\Models\AiKnowledgeSource;
use App\Modules\AiAssistant\Services\HelpPackDiscoveryService;
use App\Modules\AiAssistant\Services\KnowledgeRetrievalService;
use App\Modules\AiAssistant\Support\AssistantAskStatus;
use App\Modules\AiAssistant\Support\AssistantKnowledgeScope;
use App\Modules\AiAssistant\Support\AssistantKnowledgeStatus;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class ModuleHelpPackDiscoveryTest extends TestCase
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
                'procurement_one',
                'finance_one',
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

    public function test_module_help_pack_is_discovered_and_synced_as_module_tagged_global_source(): void
    {
        $discovery = app(HelpPackDiscoveryService::class);
        $packs = $discovery->discoverModuleHelpPacks();

        $this->assertTrue(
            $packs->contains(fn ($a) => $a->slug === 'procurement-one-overview' && $a->moduleKey === 'procurement_one'),
            'Expected the ProcurementOne help pack to be discovered.',
        );

        Artisan::call('ai-assistant:sync-global-knowledge', [
            '--tenant' => $this->testTenant->id,
        ]);

        tenancy()->initialize($this->testTenant);
        $source = AiKnowledgeSource::query()
            ->where('scope', AssistantKnowledgeScope::GLOBAL)
            ->where('slug', 'procurement-one-overview')
            ->first();
        tenancy()->end();

        $this->assertNotNull($source);
        $this->assertSame('procurement_one', $source->module_key);
        $this->assertSame(AssistantKnowledgeStatus::PUBLISHED, $source->status);
        $this->assertSame('ProcurementOne/Knowledge/help/procurement-one-overview.md', $source->source_path);
    }

    public function test_enabling_and_disabling_module_changes_retrieval_availability(): void
    {
        Artisan::call('ai-assistant:sync-global-knowledge', ['--tenant' => $this->testTenant->id]);
        Artisan::call('ai-assistant:ingest-knowledge', [
            '--tenant' => $this->testTenant->id,
            '--sync' => true,
        ]);

        $question = 'How does Procurement-One handle vendors RFQ and purchase orders?';

        // Module enabled → doc is retrievable.
        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        $enabledHits = app(KnowledgeRetrievalService::class)->retrieve($this->testTenantAdmin, $question, 5);
        tenancy()->end();

        $this->assertTrue(
            collect($enabledHits)->contains(fn ($chunk) => $chunk->slug === 'procurement-one-overview'),
            'Procurement doc should be retrievable while the module is enabled.',
        );

        // Disable the module at the platform level.
        config([
            'toweros.tenant_modules.enabled' => [
                'core',
                'team_access',
                'ai_assistant',
                'finance_one',
            ],
        ]);

        tenancy()->initialize($this->testTenant);
        $disabledHits = app(KnowledgeRetrievalService::class)->retrieve($this->testTenantAdmin, $question, 5);
        tenancy()->end();

        $this->assertFalse(
            collect($disabledHits)->contains(fn ($chunk) => $chunk->slug === 'procurement-one-overview'),
            'Procurement doc must NOT be retrievable once the module is disabled.',
        );
    }

    public function test_validate_help_packs_command_passes(): void
    {
        $exit = Artisan::call('ai-assistant:validate-help-packs');
        $this->assertSame(0, $exit, Artisan::output());
    }

    public function test_enabled_module_without_published_guide_answers_honestly(): void
    {
        // finance_one is enabled but ships no help pack and has no global doc.
        // Nothing is ingested, so retrieval returns empty and the assistant must be honest.
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/ask', [
                'question' => 'How do I use Finance-One?',
                'module_context' => 'finance_one',
                'page_path' => '/finance',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', AssistantAskStatus::INSUFFICIENT_CONTEXT);

        $answer = (string) $response->json('data.answer');
        $this->assertStringContainsStringIgnoringCase('not been published yet', $answer);
        $this->assertStringContainsStringIgnoringCase('finance_one', $answer);
    }
}
