<?php

declare(strict_types=1);

namespace Tests\Feature\AiAssistant;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\AiAssistant\Contracts\EmbeddingProviderInterface;
use App\Modules\AiAssistant\Contracts\LlmProviderInterface;
use App\Modules\AiAssistant\DTOs\LlmCompletionResult;
use App\Modules\AiAssistant\DTOs\LlmPrompt;
use App\Modules\AiAssistant\Support\AssistantAskStatus;
use App\Modules\AiAssistant\Support\AssistantProviderErrorClassifier;
use App\Modules\AiAssistant\Support\AssistantProviderQuotaExceededException;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class AssistantProviderQuotaTest extends TestCase
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
            'toweros.tenant_modules.enabled' => ['core', 'team_access', 'ai_assistant'],
            'ai_assistant.enabled' => true,
            'ai_assistant.llm_provider' => 'openai',
            'ai_assistant.embedding_provider' => 'openai',
        ]);

        $this->bootInMemoryTenantApi();
    }

    public function test_ask_returns_quota_exceeded_notice_when_openai_llm_fails(): void
    {
        $this->app->instance(LlmProviderInterface::class, new readonly class implements LlmProviderInterface
        {
            public function complete(LlmPrompt $prompt): LlmCompletionResult
            {
                throw new AssistantProviderQuotaExceededException(
                    provider: 'openai',
                    message: 'OpenAI chat completion failed (HTTP 429): insufficient_quota',
                );
            }

            public function modelName(): string
            {
                return 'gpt-4o-mini';
            }
        });

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/ask', [
                'question' => 'How do I create an E-Approval request?',
            ])
            ->assertOk();

        $response
            ->assertJsonPath('data.status', AssistantAskStatus::PROVIDER_QUOTA_EXCEEDED)
            ->assertJsonPath('data.error_code', AssistantProviderErrorClassifier::OPENAI_QUOTA_EXCEEDED)
            ->assertJsonPath('data.provider_notice.provider', 'openai')
            ->assertJsonPath('data.provider_notice.title', 'OpenAI quota exceeded');

        $this->assertStringContainsString('OpenAI quota exceeded', (string) $response->json('data.answer'));
    }

    public function test_ask_returns_quota_exceeded_notice_when_openai_embeddings_fail(): void
    {
        $this->app->instance(EmbeddingProviderInterface::class, new readonly class implements EmbeddingProviderInterface
        {
            public function embed(string $text): array
            {
                throw new AssistantProviderQuotaExceededException(
                    provider: 'openai',
                    message: 'OpenAI embeddings failed (HTTP 429): insufficient_quota',
                );
            }

            public function embedMany(array $texts): array
            {
                return array_map(fn (): array => $this->embed('x'), $texts);
            }

            public function modelName(): string
            {
                return 'text-embedding-3-small';
            }

            public function dimensions(): int
            {
                return 1536;
            }
        });

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/ask', [
                'question' => 'How do I create an E-Approval request?',
            ])
            ->assertOk();

        $response
            ->assertJsonPath('data.status', AssistantAskStatus::PROVIDER_QUOTA_EXCEEDED)
            ->assertJsonPath('data.error_code', AssistantProviderErrorClassifier::OPENAI_QUOTA_EXCEEDED);

        $this->assertStringContainsString('billing limit', (string) $response->json('data.answer'));
    }
}
