<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Support;

use App\Modules\AiAssistant\Contracts\LlmProviderInterface;
use App\Modules\AiAssistant\DTOs\LlmCompletionResult;
use App\Modules\AiAssistant\DTOs\LlmPrompt;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OpenAI Chat Completions adapter (ChatGPT API).
 * Requires OPENAI_API_KEY / AI_ASSISTANT_OPENAI_API_KEY when AI_ASSISTANT_LLM_PROVIDER=openai.
 */
final class OpenAiLlmProvider implements LlmProviderInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly string $modelId,
        private readonly int $maxTokens = 1024,
        private readonly float $temperature = 0.2,
        private readonly int $timeoutSeconds = 60,
    ) {}

    public function complete(LlmPrompt $prompt): LlmCompletionResult
    {
        $this->assertConfigured();

        $started = hrtime(true);
        $endpoint = rtrim($this->baseUrl, '/').'/chat/completions';

        $response = Http::withToken($this->apiKey)
            ->acceptJson()
            ->timeout($this->timeoutSeconds)
            ->post($endpoint, [
                'model' => $this->modelId,
                'temperature' => $this->temperature,
                'max_tokens' => $this->maxTokens,
                'messages' => [
                    ['role' => 'system', 'content' => $prompt->system],
                    ['role' => 'user', 'content' => $prompt->user],
                ],
            ]);

        if (! $response->successful()) {
            if ($response->status() === 429 && AssistantProviderErrorClassifier::openAiBodyIndicatesQuotaExceeded($response->body())) {
                throw new AssistantProviderQuotaExceededException(
                    provider: 'openai',
                    message: 'OpenAI chat completion failed (HTTP 429): '.$response->body(),
                );
            }

            throw new RuntimeException(
                'OpenAI chat completion failed (HTTP '.$response->status().'): '.$response->body(),
            );
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];
        $answer = $this->extractText($payload);
        $usage = is_array($payload['usage'] ?? null) ? $payload['usage'] : [];
        $insufficient = $prompt->chunks === []
            || str_contains(mb_strtolower($answer), 'do not have enough')
            || str_contains(mb_strtolower($answer), 'not enough approved');

        return new LlmCompletionResult(
            answer: $answer,
            modelName: $this->modelId,
            promptTokens: isset($usage['prompt_tokens']) ? (int) $usage['prompt_tokens'] : null,
            completionTokens: isset($usage['completion_tokens']) ? (int) $usage['completion_tokens'] : null,
            latencyMs: (int) max(0, (hrtime(true) - $started) / 1_000_000),
            insufficientContext: $insufficient,
            suggestedFollowups: [],
        );
    }

    public function modelName(): string
    {
        return $this->modelId;
    }

    private function assertConfigured(): void
    {
        if (trim($this->apiKey) === '') {
            throw new RuntimeException(
                'OpenAI LLM requires OPENAI_API_KEY (or AI_ASSISTANT_OPENAI_API_KEY). Set the key or use AI_ASSISTANT_LLM_PROVIDER=local.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractText(array $payload): string
    {
        $choices = $payload['choices'] ?? null;
        if (! is_array($choices) || $choices === []) {
            throw new RuntimeException('OpenAI chat response did not include choices.');
        }

        $first = $choices[0] ?? null;
        if (! is_array($first)) {
            throw new RuntimeException('OpenAI chat response choice was invalid.');
        }

        $message = $first['message'] ?? null;
        $content = is_array($message) ? ($message['content'] ?? null) : null;
        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('OpenAI chat response content was empty.');
        }

        return trim($content);
    }
}
