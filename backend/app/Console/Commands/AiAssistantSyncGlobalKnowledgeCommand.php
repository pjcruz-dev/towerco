<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Modules\AiAssistant\Services\KnowledgeCatalogService;
use Illuminate\Console\Command;

final class AiAssistantSyncGlobalKnowledgeCommand extends Command
{
    protected $signature = 'ai-assistant:sync-global-knowledge
                            {--tenant= : Tenant UUID to sync}
                            {--all : Sync every tenant}
                            {--prune : Archive global sources missing from the help pack}';

    protected $description = 'Upsert codebase global AI Assistant help articles into tenant ai_knowledge_sources';

    public function handle(KnowledgeCatalogService $catalog): int
    {
        $tenantId = $this->option('tenant');
        $all = (bool) $this->option('all');
        $prune = (bool) $this->option('prune');

        if (! $tenantId && ! $all) {
            $this->error('Pass --tenant={uuid} or --all');

            return self::FAILURE;
        }

        $articles = $catalog->discoverGlobalArticles();
        $this->info('Discovered '.$articles->count().' global help article(s).');

        if ($articles->isEmpty()) {
            $this->warn('No markdown articles found under '.$catalog->globalKnowledgePath());

            return self::SUCCESS;
        }

        $tenants = $all
            ? Tenant::query()->orderBy('created_at')->get()
            : Tenant::query()->whereKey($tenantId)->get();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants matched.');

            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            $domain = (string) ($tenant->domains()->first()?->domain ?? $tenant->id);

            $result = $tenant->run(
                static fn (): array => $catalog->syncGlobalSourcesToCurrentTenant($prune),
            );

            $this->info(sprintf(
                '[%s] %s — created=%d updated=%d skipped=%d',
                $tenant->id,
                $domain,
                $result['created'],
                $result['updated'],
                $result['skipped'],
            ));
        }

        return self::SUCCESS;
    }
}
