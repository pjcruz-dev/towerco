<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Jobs;

use App\Core\Jobs\AbstractQueuedJob;
use App\Models\Tenant;
use App\Modules\AiAssistant\Models\AiKnowledgeSource;
use App\Modules\AiAssistant\Services\KnowledgeIngestionService;
use App\Modules\AiAssistant\Support\AssistantKnowledgeStatus;
use Illuminate\Support\Facades\Log;

final class ReindexKnowledgeJob extends AbstractQueuedJob
{
    public function __construct(
        public readonly string $tenantId,
        public readonly ?string $sourceId = null,
    ) {
        parent::__construct();
        $this->onQueue(config('ai_assistant.queue', config('toweros.queues.integrations')));
    }

    public function handle(KnowledgeIngestionService $ingestion): void
    {
        $tenant = Tenant::query()->find($this->tenantId);
        if ($tenant === null) {
            Log::warning('ai_assistant.reindex.tenant_missing', ['tenant_id' => $this->tenantId]);

            return;
        }

        $tenant->run(function () use ($ingestion): void {
            $query = AiKnowledgeSource::query()
                ->where('status', AssistantKnowledgeStatus::PUBLISHED);

            if ($this->sourceId !== null && $this->sourceId !== '') {
                $query->where('id', $this->sourceId);
            }

            foreach ($query->cursor() as $source) {
                $result = $ingestion->ingestSource($source);
                Log::info('ai_assistant.reindex.source_completed', [
                    'tenant_id' => $this->tenantId,
                    ...$result,
                ]);
            }
        });
    }
}
