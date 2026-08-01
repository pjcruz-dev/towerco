<?php

declare(strict_types=1);

namespace Tests\Unit\AiAssistant;

use App\Modules\AiAssistant\Contracts\LlmProviderInterface;
use App\Modules\AiAssistant\DTOs\LlmPrompt;
use App\Modules\AiAssistant\Support\AssistantProviderQuotaExceededException;
use App\Modules\AiAssistant\Support\CursorLlmProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class CursorLlmProviderTest extends TestCase
{
    public function test_service_container_resolves_cursor_provider(): void
    {
        config([
            'ai_assistant.llm_provider' => 'cursor',
            'ai_assistant.cursor.api_key' => 'crsr_test',
            'ai_assistant.cursor.model' => 'composer-2',
        ]);

        $this->app->forgetInstance(LlmProviderInterface::class);

        $this->assertInstanceOf(CursorLlmProvider::class, app(LlmProviderInterface::class));
    }

    public function test_cursor_llm_polls_run_until_finished(): void
    {
        Http::fake([
            'api.cursor.com/v1/agents' => Http::response([
                'agent' => ['id' => 'bc-test-agent'],
                'run' => ['id' => 'run-test', 'status' => 'CREATING'],
            ], 200),
            'api.cursor.com/v1/agents/bc-test-agent/runs/run-test' => Http::sequence()
                ->push(['id' => 'run-test', 'status' => 'RUNNING'])
                ->push([
                    'id' => 'run-test',
                    'status' => 'FINISHED',
                    'result' => 'Use Document Approval → New Request to submit.',
                ]),
            'api.cursor.com/v1/agents/bc-test-agent/archive' => Http::response([], 200),
        ]);

        $provider = new CursorLlmProvider(
            apiKey: 'crsr_test',
            baseUrl: 'https://api.cursor.com/v1',
            modelId: 'composer-2',
            maxWaitSeconds: 10,
            pollIntervalMs: 1,
        );

        $result = $provider->complete(new LlmPrompt(
            system: 'Answer from approved context only.',
            user: 'How do I create a request?',
            chunks: [],
        ));

        $this->assertSame('Use Document Approval → New Request to submit.', $result->answer);
        $this->assertSame('composer-2', $result->modelName);
    }

    public function test_cursor_llm_throws_quota_exception_on_429(): void
    {
        Http::fake([
            'api.cursor.com/v1/agents' => Http::response([
                'error' => 'Too Many Requests',
                'message' => 'Rate limit exceeded. Please try again later.',
            ], 429),
        ]);

        $provider = new CursorLlmProvider(
            apiKey: 'crsr_test',
            baseUrl: 'https://api.cursor.com/v1',
            modelId: 'composer-2',
        );

        $this->expectException(AssistantProviderQuotaExceededException::class);
        $provider->complete(new LlmPrompt(system: 'sys', user: 'user'));
    }
}
