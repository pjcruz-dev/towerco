<?php

declare(strict_types=1);

namespace Tests\Unit\AiAssistant;

use App\Modules\AiAssistant\Contracts\EmbeddingProviderInterface;
use App\Modules\AiAssistant\Contracts\LlmProviderInterface;
use App\Modules\AiAssistant\DTOs\LlmPrompt;
use App\Modules\AiAssistant\Support\OpenAiEmbeddingProvider;
use App\Modules\AiAssistant\Support\OpenAiLlmProvider;
use App\Modules\AiAssistant\Support\AssistantProviderQuotaExceededException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class OpenAiProvidersTest extends TestCase
{
    public function test_service_container_resolves_openai_providers(): void
    {
        config([
            'ai_assistant.llm_provider' => 'openai',
            'ai_assistant.embedding_provider' => 'openai',
            'ai_assistant.openai.api_key' => 'sk-test',
            'ai_assistant.openai.chat_model' => 'gpt-4o-mini',
            'ai_assistant.openai.embedding_model' => 'text-embedding-3-small',
            'ai_assistant.openai.dimensions' => 8,
        ]);

        $this->app->forgetInstance(LlmProviderInterface::class);
        $this->app->forgetInstance(EmbeddingProviderInterface::class);

        $this->assertInstanceOf(OpenAiLlmProvider::class, app(LlmProviderInterface::class));
        $this->assertInstanceOf(OpenAiEmbeddingProvider::class, app(EmbeddingProviderInterface::class));
    }

    public function test_openai_llm_complete_parses_chat_response(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Use Document Approval → New Request.',
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 12,
                    'completion_tokens' => 8,
                ],
            ], 200),
        ]);

        $provider = new OpenAiLlmProvider(
            apiKey: 'sk-test',
            baseUrl: 'https://api.openai.com/v1',
            modelId: 'gpt-4o-mini',
        );

        $result = $provider->complete(new LlmPrompt(
            system: 'Answer from approved context only.',
            user: 'How do I create a request?',
            chunks: [],
        ));

        $this->assertSame('Use Document Approval → New Request.', $result->answer);
        $this->assertSame('gpt-4o-mini', $result->modelName);
        $this->assertSame(12, $result->promptTokens);
        $this->assertSame(8, $result->completionTokens);
        $this->assertTrue($result->insufficientContext);
    }

    public function test_openai_embedding_parses_vectors(): void
    {
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    ['index' => 0, 'embedding' => [0.1, 0.2, 0.3]],
                    ['index' => 1, 'embedding' => [0.4, 0.5, 0.6]],
                ],
            ], 200),
        ]);

        $provider = new OpenAiEmbeddingProvider(
            apiKey: 'sk-test',
            baseUrl: 'https://api.openai.com/v1',
            modelId: 'text-embedding-3-small',
            dimensions: 3,
        );

        $vectors = $provider->embedMany(['alpha', 'beta']);

        $this->assertCount(2, $vectors);
        $this->assertSame([0.1, 0.2, 0.3], $vectors[0]);
        $this->assertSame([0.4, 0.5, 0.6], $vectors[1]);
        $this->assertSame(3, $provider->dimensions());
    }

    public function test_openai_embedding_throws_quota_exception_on_429(): void
    {
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'error' => [
                    'message' => 'You exceeded your current quota, please check your plan and billing details.',
                    'type' => 'insufficient_quota',
                    'code' => 'insufficient_quota',
                ],
            ], 429),
        ]);

        $provider = new OpenAiEmbeddingProvider(
            apiKey: 'sk-test',
            baseUrl: 'https://api.openai.com/v1',
            modelId: 'text-embedding-3-small',
            dimensions: 3,
        );

        $this->expectException(AssistantProviderQuotaExceededException::class);
        $provider->embed('quota test');
    }
}
