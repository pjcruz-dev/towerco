<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Modules\AiAssistant\Jobs\ReindexKnowledgeJob;
use App\Modules\AiAssistant\Services\KnowledgeIngestionService;
use Illuminate\Console\Command;

final class AiAssistantReindexKnowledgeCommand extends Command
{
    protected $signature = 'ai-assistant:reindex-knowledge
                            {source? : Optional knowledge source UUID}
                            {--tenant= : Tenant UUID}
                            {--all : Reindex every tenant}
                            {--sync : Run inline instead of queueing jobs}';

    protected $description = 'Rechunk and re-embed published AI Assistant knowledge (optionally one source)';

    public function handle(KnowledgeIngestionService $ingestion): int
    {
        if (! (bool) config('ai_assistant.enabled', true)) {
            $this->warn('AI Assistant is disabled (AI_ASSISTANT_ENABLED=false).');

            return self::SUCCESS;
        }

        $tenantId = $this->option('tenant');
        $all = (bool) $this->option('all');
        $sourceId = $this->argument('source');
        $sync = (bool) $this->option('sync');

        if (! $tenantId && ! $all) {
            $this->error('Pass --tenant={uuid} or --all');

            return self::FAILURE;
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
            $source = is_string($sourceId) && $sourceId !== '' ? $sourceId : null;

            if ($sync) {
                $results = $tenant->run(
                    static fn (): array => $ingestion->ingestPublishedSources($source),
                );
                $this->info("[{$tenant->id}] {$domain} — reindexed sources=".count($results));

                continue;
            }

            ReindexKnowledgeJob::dispatch($tenant->id, $source);
            $this->info("[{$tenant->id}] {$domain} — queued reindex job");
        }

        return self::SUCCESS;
    }
}
