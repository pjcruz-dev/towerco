<?php

declare(strict_types=1);

namespace Tests\Unit\AiAssistant;

use App\Modules\AiAssistant\Contracts\LlmProviderInterface;
use App\Modules\AiAssistant\DTOs\LlmPrompt;
use App\Modules\AiAssistant\Support\OpenAiLlmProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class OpenAiProvidersTest extends TestCase
{
    public function test_service_container_resolves_openai_providers(): void
    {
        config([
            'ai_assistant.llm_provider' => 'openai',
            'ai_assistant.openai.api_key' => 'sk-test',
            'ai_assistant.openai.chat_model' => 'gpt-4o-mini',
        ]);

        $this->app->forgetInstance(LlmProviderInterface::class);

        $this->assertInstanceOf(OpenAiLlmProvider::class, app(LlmProviderInterface::class));
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
}
