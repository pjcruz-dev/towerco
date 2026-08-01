<?php

declare(strict_types=1);

namespace Tests\Feature\AiAssistant;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\AiAssistant\Models\AiKnowledgeSource;
use App\Modules\AiAssistant\Services\KnowledgeCatalogService;
use App\Modules\AiAssistant\Support\AssistantKnowledgeScope;
use App\Modules\AiAssistant\Support\AssistantKnowledgeStatus;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class SyncGlobalKnowledgeTest extends TestCase
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
        ]);

        $this->bootInMemoryTenantApi();
    }

    public function test_sync_command_upserts_global_sources_for_tenant(): void
    {
        $exit = Artisan::call('ai-assistant:sync-global-knowledge', [
            '--tenant' => $this->testTenant->id,
        ]);

        $this->assertSame(0, $exit);

        tenancy()->initialize($this->testTenant);

        $sources = AiKnowledgeSource::query()
            ->where('scope', AssistantKnowledgeScope::GLOBAL)
            ->where('status', AssistantKnowledgeStatus::PUBLISHED)
            ->get();

        $this->assertGreaterThanOrEqual(11, $sources->count());
        $this->assertTrue($sources->contains(fn (AiKnowledgeSource $s) => $s->slug === 'getting-started'));
        $this->assertTrue($sources->contains(fn (AiKnowledgeSource $s) => $s->slug === 'ticketing-basics'));

        $gettingStarted = $sources->firstWhere('slug', 'getting-started');
        $this->assertNotNull($gettingStarted);
        $this->assertSame('markdown', $gettingStarted->source_type);
        $this->assertSame('core', $gettingStarted->module_key);
        $this->assertSame('Knowledge/global/getting-started.md', $gettingStarted->source_path);
        $this->assertNotNull($gettingStarted->content_checksum);

        $firstCount = $sources->count();
        $result = app(KnowledgeCatalogService::class)->syncGlobalSourcesToCurrentTenant();
        $this->assertSame(0, $result['created']);
        $this->assertSame($firstCount, AiKnowledgeSource::query()->where('scope', AssistantKnowledgeScope::GLOBAL)->count());

        tenancy()->end();
    }
}
