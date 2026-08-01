<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Jobs;

use App\Core\Jobs\AbstractQueuedJob;
use App\Models\Tenant;
use App\Modules\AiAssistant\Models\AiKnowledgeSource;
use App\Modules\AiAssistant\Services\KnowledgeIngestionService;
use App\Modules\Workspace\Services\TenantActivityLogger;
use Illuminate\Support\Facades\Log;

final class IngestKnowledgeSourceJob extends AbstractQueuedJob
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $sourceId,
    ) {
        parent::__construct();
        $this->onQueue(config('ai_assistant.queue', config('toweros.queues.integrations')));
    }

    public function handle(KnowledgeIngestionService $ingestion, TenantActivityLogger $activity): void
    {
        $tenant = Tenant::query()->find($this->tenantId);
        if ($tenant === null) {
            Log::warning('ai_assistant.ingest.tenant_missing', ['tenant_id' => $this->tenantId]);

            return;
        }

        $tenant->run(function () use ($ingestion, $activity): void {
            $source = AiKnowledgeSource::query()->find($this->sourceId);
            if ($source === null) {
                Log::warning('ai_assistant.ingest.source_missing', [
                    'tenant_id' => $this->tenantId,
                    'source_id' => $this->sourceId,
                ]);

                return;
            }

            $result = $ingestion->ingestSource($source);
            Log::info('ai_assistant.ingest.completed', [
                'tenant_id' => $this->tenantId,
                ...$result,
            ]);

            $activity->record(
                module: 'ai_assistant',
                action: 'assistant.knowledge.ingest',
                summary: 'Knowledge source ingested',
                entityType: 'ai_knowledge_source',
                entityId: (string) $source->id,
                entityLabel: $source->title,
                actor: null,
                metadata: [
                    'slug' => $source->slug,
                    'scope' => $source->scope,
                    'version' => $source->version,
                    'chunks' => $result['chunks'],
                    'deleted' => $result['deleted'],
                ],
            );
        });
    }
}
