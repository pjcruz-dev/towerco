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
        $seen = [];
        $provider->listenWhileWaiting(function (string $status) use (&$seen): void {
            $seen[] = $status;
        });

        $result = $provider->complete(new LlmPrompt(
            system: 'Answer from approved context only.',
            user: 'How do I create a request?',
            chunks: [],
        ));

        $this->assertSame('Use Document Approval → New Request to submit.', $result->answer);
        $this->assertSame('composer-2', $result->modelName);
        $this->assertSame(['CREATING', 'RUNNING', 'FINISHED'], $seen);
    }

    public function test_cursor_llm_streams_thinking_and_the_reply(): void
    {
        $sse = implode("\n\n", [
            "event: thinking\ndata: {\"text\":\"Counting published rows.\"}",
            "event: assistant\ndata: {\"text\":\"There are \"}",
            "event: assistant\ndata: {\"text\":\"4 published.\"}",
            "event: result\ndata: {\"status\":\"FINISHED\",\"text\":\"There are 4 published.\"}",
            "event: done\ndata: {}",
        ])."\n\n";

        Http::fake([
            'api.cursor.com/v1/agents' => Http::response([
                'agent' => ['id' => 'bc-stream-agent'],
                'run' => ['id' => 'run-stream', 'status' => 'CREATING'],
            ], 200),
            'api.cursor.com/v1/agents/bc-stream-agent/runs/run-stream/stream' => Http::response($sse, 200, [
                'Content-Type' => 'text/event-stream',
            ]),
            'api.cursor.com/v1/agents/bc-stream-agent/archive' => Http::response([], 200),
        ]);

        $provider = new CursorLlmProvider(
            apiKey: 'crsr_test',
            baseUrl: 'https://api.cursor.com/v1',
            modelId: 'grok-4.7',
            maxWaitSeconds: 10,
            pollIntervalMs: 1,
        );
        $thought = '';
        $reply = '';
        $provider->listenToDeltas(function (array $delta) use (&$thought, &$reply): void {
            if ($delta['kind'] === 'thinking') {
                $thought .= $delta['text'];
            }
            if ($delta['kind'] === 'assistant') {
                $reply .= $delta['text'];
            }
        });

        $result = $provider->complete(new LlmPrompt(
            system: 'Answer from the rows.',
            user: 'How many published?',
        ));

        $this->assertSame('There are 4 published.', $result->answer);
        $this->assertSame('Counting published rows.', $thought);
        $this->assertSame('There are 4 published.', $reply);
    }

    public function test_allowed_models_use_the_cursor_catalog(): void
    {
        Http::fake([
            'api.cursor.com/v1/models' => Http::response([
                'items' => [
                    ['id' => 'default', 'displayName' => 'Auto'],
                    ['id' => 'grok-4.7', 'displayName' => 'Grok 4.7'],
                    ['id' => 'claude-opus-5', 'displayName' => 'Claude Opus 5'],
                ],
            ], 200),
        ]);

        $provider = new CursorLlmProvider(
            apiKey: 'crsr_catalog_test',
            baseUrl: 'https://api.cursor.com/v1',
            modelId: 'composer-2.5',
            allowedModels: ['composer-2.5', 'grok-4.5'],
        );

        $this->assertSame(['composer-2.5', 'default', 'grok-4.7', 'claude-opus-5'], $provider->allowedModels());
        $this->assertSame('Auto', $provider->modelLabels()['default']);
        $this->assertSame('Grok 4.7', $provider->modelLabels()['grok-4.7']);
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
