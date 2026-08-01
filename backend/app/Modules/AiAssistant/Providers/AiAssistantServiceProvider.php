<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Providers;

use App\Modules\AiAssistant\Contracts\EmbeddingProviderInterface;
use App\Modules\AiAssistant\Contracts\LlmProviderInterface;
use App\Modules\AiAssistant\Contracts\VectorStoreInterface;
use App\Modules\AiAssistant\Support\BedrockEmbeddingProvider;
use App\Modules\AiAssistant\Support\BedrockLlmProvider;
use App\Modules\AiAssistant\Support\CursorLlmProvider;
use App\Modules\AiAssistant\Support\DatabaseVectorStore;
use App\Modules\AiAssistant\Support\KnowledgeTextChunker;
use App\Modules\AiAssistant\Support\LocalGroundedLlmProvider;
use App\Modules\AiAssistant\Support\LocalHashEmbeddingProvider;
use App\Modules\AiAssistant\Support\OpenAiEmbeddingProvider;
use App\Modules\AiAssistant\Support\OpenAiLlmProvider;
use App\Modules\AiAssistant\Support\OpenSearchVectorStore;
use App\Modules\AiAssistant\Services\Actions\AssistantActionRegistry;
use App\Modules\AiAssistant\Services\Actions\DraftEApprovalSubmissionAction;
use App\Modules\AiAssistant\Services\Actions\DraftTicketAction;
use App\Modules\AiAssistant\Services\Actions\SuggestDocumentMetadataAction;
use App\Modules\AiAssistant\Services\Tools\AssistantToolRegistry;
use App\Modules\AiAssistant\Services\Tools\GetControlledDocumentByCodeTool;
use App\Modules\AiAssistant\Services\Tools\GetEApprovalSubmissionByDocumentNoTool;
use App\Modules\AiAssistant\Services\Tools\GetSiteByCodeTool;
use App\Modules\AiAssistant\Services\Tools\GetTicketByNumberTool;
use App\Modules\AiAssistant\Services\Tools\ListExpiringDocumentsTool;
use App\Modules\AiAssistant\Services\Tools\ListMyEApprovalSubmissionsTool;
use App\Modules\AiAssistant\Services\Tools\ListMyOpenTicketsTool;
use App\Modules\AiAssistant\Services\Tools\ListMyPendingApprovalsTool;
use App\Modules\AiAssistant\Services\Tools\SearchWorkspaceEntitiesTool;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

final class AiAssistantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(KnowledgeTextChunker::class, static function (): KnowledgeTextChunker {
            return new KnowledgeTextChunker(
                size: (int) config('ai_assistant.chunking.size', 800),
                overlap: (int) config('ai_assistant.chunking.overlap', 120),
            );
        });

        $this->app->singleton(EmbeddingProviderInterface::class, static function ($app): EmbeddingProviderInterface {
            $driver = strtolower((string) config('ai_assistant.embedding_provider', 'local'));

            return match ($driver) {
                'local', 'fake' => new LocalHashEmbeddingProvider(
                    dimensions: (int) config('ai_assistant.local_embedding.dimensions', 256),
                ),
                'bedrock' => new BedrockEmbeddingProvider(
                    region: (string) config('ai_assistant.bedrock.region'),
                    modelId: (string) config('ai_assistant.bedrock.embedding_model_id', config('ai_assistant.bedrock.model_id')),
                    dimensions: (int) config('ai_assistant.bedrock.dimensions', 1024),
                ),
                'openai', 'chatgpt' => new OpenAiEmbeddingProvider(
                    apiKey: (string) config('ai_assistant.openai.api_key', ''),
                    baseUrl: (string) config('ai_assistant.openai.base_url', 'https://api.openai.com/v1'),
                    modelId: (string) config('ai_assistant.openai.embedding_model', 'text-embedding-3-small'),
                    dimensions: (int) config('ai_assistant.openai.dimensions', 1536),
                    timeoutSeconds: (int) config('ai_assistant.openai.timeout', 60),
                ),
                default => throw new InvalidArgumentException("Unsupported AI embedding provider [{$driver}]."),
            };
        });

        $this->app->singleton(VectorStoreInterface::class, static function ($app): VectorStoreInterface {
            $driver = strtolower((string) config('ai_assistant.vector_store', 'database'));

            return match ($driver) {
                'database', 'memory', 'local' => $app->make(DatabaseVectorStore::class),
                'opensearch' => new OpenSearchVectorStore(
                    endpoint: (string) config('ai_assistant.opensearch.endpoint', ''),
                    index: (string) config('ai_assistant.opensearch.index', 'toweros-ai-knowledge'),
                ),
                default => throw new InvalidArgumentException("Unsupported AI vector store [{$driver}]."),
            };
        });

        $this->app->singleton(LlmProviderInterface::class, static function ($app): LlmProviderInterface {
            $driver = strtolower((string) config('ai_assistant.llm_provider', 'local'));

            return match ($driver) {
                'local', 'fake' => $app->make(LocalGroundedLlmProvider::class),
                'bedrock' => new BedrockLlmProvider(
                    region: (string) config('ai_assistant.bedrock.region'),
                    modelId: (string) config('ai_assistant.bedrock.chat_model_id'),
                    maxTokens: (int) config('ai_assistant.bedrock.max_tokens', 1024),
                    temperature: (float) config('ai_assistant.bedrock.temperature', 0.2),
                ),
                'openai', 'chatgpt' => new OpenAiLlmProvider(
                    apiKey: (string) config('ai_assistant.openai.api_key', ''),
                    baseUrl: (string) config('ai_assistant.openai.base_url', 'https://api.openai.com/v1'),
                    modelId: (string) config('ai_assistant.openai.chat_model', 'gpt-4o-mini'),
                    maxTokens: (int) config('ai_assistant.openai.max_tokens', 1024),
                    temperature: (float) config('ai_assistant.openai.temperature', 0.2),
                    timeoutSeconds: (int) config('ai_assistant.openai.timeout', 60),
                ),
                'cursor', 'cursor_ai' => new CursorLlmProvider(
                    apiKey: (string) config('ai_assistant.cursor.api_key', ''),
                    baseUrl: (string) config('ai_assistant.cursor.base_url', 'https://api.cursor.com/v1'),
                    modelId: (string) config('ai_assistant.cursor.model', 'composer-2'),
                    maxWaitSeconds: (int) config('ai_assistant.cursor.max_wait_seconds', 120),
                    pollIntervalMs: (int) config('ai_assistant.cursor.poll_interval_ms', 1500),
                    requestTimeoutSeconds: (int) config('ai_assistant.cursor.timeout', 30),
                ),
                default => throw new InvalidArgumentException("Unsupported AI LLM provider [{$driver}]."),
            };
        });
        $this->app->singleton(AssistantToolRegistry::class, static function ($app): AssistantToolRegistry {
            return new AssistantToolRegistry([
                $app->make(ListMyPendingApprovalsTool::class),
                $app->make(ListMyEApprovalSubmissionsTool::class),
                $app->make(GetEApprovalSubmissionByDocumentNoTool::class),
                $app->make(GetTicketByNumberTool::class),
                $app->make(GetControlledDocumentByCodeTool::class),
                $app->make(GetSiteByCodeTool::class),
                $app->make(ListMyOpenTicketsTool::class),
                $app->make(ListExpiringDocumentsTool::class),
                $app->make(SearchWorkspaceEntitiesTool::class),
            ]);
        });

        $this->app->singleton(AssistantActionRegistry::class, static function ($app): AssistantActionRegistry {
            return new AssistantActionRegistry([
                $app->make(DraftTicketAction::class),
                $app->make(DraftEApprovalSubmissionAction::class),
                $app->make(SuggestDocumentMetadataAction::class),
            ]);
        });
    }
}
