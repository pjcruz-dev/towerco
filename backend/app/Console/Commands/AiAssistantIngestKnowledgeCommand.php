<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Modules\AiAssistant\Jobs\IngestKnowledgeSourceJob;
use App\Modules\AiAssistant\Models\AiKnowledgeSource;
use App\Modules\AiAssistant\Services\KnowledgeIngestionService;
use App\Modules\AiAssistant\Support\AssistantKnowledgeStatus;
use Illuminate\Console\Command;

final class AiAssistantIngestKnowledgeCommand extends Command
{
    protected $signature = 'ai-assistant:ingest-knowledge
                            {--tenant= : Tenant UUID}
                            {--all : Ingest for every tenant}
                            {--source= : Optional knowledge source UUID}
                            {--sync : Run inline instead of queueing jobs}';

    protected $description = 'Chunk, embed, and index published AI Assistant knowledge sources';

    public function handle(KnowledgeIngestionService $ingestion): int
    {
        if (! (bool) config('ai_assistant.enabled', true)) {
            $this->warn('AI Assistant is disabled (AI_ASSISTANT_ENABLED=false).');

            return self::SUCCESS;
        }

        $tenantId = $this->option('tenant');
        $all = (bool) $this->option('all');
        $sourceId = $this->option('source');
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

            if ($sync) {
                $results = $tenant->run(
                    static function () use ($ingestion, $sourceId): array {
                        return $ingestion->ingestPublishedSources(
                            is_string($sourceId) && $sourceId !== '' ? $sourceId : null,
                        );
                    },
                );

                $chunkTotal = array_sum(array_column($results, 'chunks'));
                $this->info("[{$tenant->id}] {$domain} — synced sources=".count($results)." chunks={$chunkTotal}");

                continue;
            }

            $sourceIds = $tenant->run(static function () use ($sourceId): array {
                $query = AiKnowledgeSource::query()
                    ->where('status', AssistantKnowledgeStatus::PUBLISHED);

                if (is_string($sourceId) && $sourceId !== '') {
                    $query->where('id', $sourceId);
                }

                return $query->pluck('id')->map(static fn ($id): string => (string) $id)->all();
            });

            foreach ($sourceIds as $id) {
                IngestKnowledgeSourceJob::dispatch($tenant->id, $id);
            }

            $this->info('['.$tenant->id."] {$domain} — queued ".count($sourceIds).' ingest job(s)');
        }

        return self::SUCCESS;
    }
}
