<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Support;

use App\Modules\AiAssistant\Contracts\LlmProviderInterface;
use App\Modules\AiAssistant\DTOs\LlmCompletionResult;
use App\Modules\AiAssistant\DTOs\LlmPrompt;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Google AI Studio / Gemini Generative Language API adapter.
 * Requires GOOGLE_AI_API_KEY (or AI_ASSISTANT_GOOGLE_AI_API_KEY) when AI_ASSISTANT_LLM_PROVIDER=gemini.
 */
final class GeminiLlmProvider implements LlmProviderInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly string $modelId,
        private readonly int $maxTokens = 2048,
        private readonly float $temperature = 0.2,
        private readonly int $timeoutSeconds = 60,
        /** @var list<string> */
        private readonly array $allowedModels = [],
    ) {}

    public function complete(LlmPrompt $prompt): LlmCompletionResult
    {
        $this->assertConfigured();

        $started = hrtime(true);
        $models = $this->candidateModels($prompt->modelOverride);
        $lastError = null;

        foreach ($models as $index => $model) {
            $response = $this->generateContent($model, $prompt);

            if ($response->successful()) {
                /** @var array<string, mixed> $payload */
                $payload = $response->json() ?? [];
                $answer = $this->extractText($payload);
                $usage = is_array($payload['usageMetadata'] ?? null) ? $payload['usageMetadata'] : [];
                $insufficient = $prompt->chunks === []
                    || str_contains(mb_strtolower($answer), 'do not have enough')
                    || str_contains(mb_strtolower($answer), 'not enough approved');

                if ($index > 0) {
                    Log::info('ai_assistant.gemini.fallback_model_used', [
                        'requested' => $this->resolveModel($prompt->modelOverride),
                        'used' => $model,
                    ]);
                }

                return new LlmCompletionResult(
                    answer: $answer,
                    modelName: $model,
                    promptTokens: isset($usage['promptTokenCount']) ? (int) $usage['promptTokenCount'] : null,
                    completionTokens: isset($usage['candidatesTokenCount'])
                        ? (int) $usage['candidatesTokenCount']
                        : (isset($usage['outputTokenCount']) ? (int) $usage['outputTokenCount'] : null),
                    latencyMs: (int) max(0, (hrtime(true) - $started) / 1_000_000),
                    insufficientContext: $insufficient,
                    suggestedFollowups: [],
                );
            }

            $status = $response->status();
            $body = $response->body();
            $lastError = 'Gemini generateContent failed (HTTP '.$status.'): '.$body;

            if ($status === 429) {
                throw new AssistantProviderQuotaExceededException(
                    provider: 'gemini',
                    message: $lastError,
                );
            }

            // Retry with another model when the selected one is retired or temporarily unavailable.
            if (! in_array($status, [404, 503], true) || $index === count($models) - 1) {
                throw new RuntimeException($lastError);
            }

            Log::warning('ai_assistant.gemini.model_retry', [
                'model' => $model,
                'status' => $status,
                'next' => $models[$index + 1] ?? null,
            ]);
        }

        throw new RuntimeException($lastError ?? 'Gemini generateContent failed.');
    }

    public function modelName(): string
    {
        return $this->modelId;
    }

    /**
     * @return list<string>
     */
    public function allowedModels(): array
    {
        $models = $this->allowedModels !== [] ? $this->allowedModels : [$this->modelId];
        if (! in_array($this->modelId, $models, true)) {
            array_unshift($models, $this->modelId);
        }

        return array_values(array_unique($models));
    }

    /**
     * @return list<string>
     */
    private function candidateModels(?string $override): array
    {
        $primary = $this->resolveModel($override);
        $fallbacks = [
            'gemini-flash-latest',
            'gemini-3.6-flash',
            'gemini-3.5-flash',
            'gemini-3.5-flash-lite',
            'gemini-3-flash-preview',
            $this->modelId,
        ];

        $ordered = [$primary];
        foreach ($fallbacks as $model) {
            if ($model !== '' && ! in_array($model, $ordered, true) && in_array($model, $this->allowedModels(), true)) {
                $ordered[] = $model;
            }
        }

        // Always allow at least the primary, even if not in allowlist (resolveModel already validated override).
        return $ordered !== [] ? $ordered : [$this->modelId];
    }

    private function generateContent(string $model, LlmPrompt $prompt): Response
    {
        $endpoint = rtrim($this->baseUrl, '/').'/models/'.$model.':generateContent';

        return Http::withHeaders([
            'x-goog-api-key' => $this->apiKey,
        ])
            ->acceptJson()
            ->timeout($this->timeoutSeconds)
            ->post($endpoint, [
                'systemInstruction' => [
                    'parts' => [
                        ['text' => $prompt->system],
                    ],
                ],
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $prompt->user],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => $this->temperature,
                    'maxOutputTokens' => $this->maxTokens,
                ],
            ]);
    }

    private function resolveModel(?string $override): string
    {
        $candidate = trim((string) $override);
        if ($candidate === '') {
            return $this->modelId;
        }

        $allowed = $this->allowedModels();
        if (! in_array($candidate, $allowed, true)) {
            return $this->modelId;
        }

        return $candidate;
    }

    private function assertConfigured(): void
    {
        if (trim($this->apiKey) === '') {
            throw new RuntimeException(
                'Gemini LLM requires GOOGLE_AI_API_KEY (or AI_ASSISTANT_GOOGLE_AI_API_KEY). Set the key or use AI_ASSISTANT_LLM_PROVIDER=local.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractText(array $payload): string
    {
        $candidates = $payload['candidates'] ?? null;
        if (! is_array($candidates) || $candidates === []) {
            throw new RuntimeException('Gemini response did not include candidates.');
        }

        $first = $candidates[0] ?? null;
        if (! is_array($first)) {
            throw new RuntimeException('Gemini response candidate was invalid.');
        }

        $content = $first['content'] ?? null;
        $parts = is_array($content) ? ($content['parts'] ?? null) : null;
        if (! is_array($parts) || $parts === []) {
            throw new RuntimeException('Gemini response content parts were empty.');
        }

        $texts = [];
        foreach ($parts as $part) {
            if (! is_array($part)) {
                continue;
            }
            $text = $part['text'] ?? null;
            if (is_string($text) && trim($text) !== '') {
                $texts[] = trim($text);
            }
        }

        if ($texts === []) {
            throw new RuntimeException('Gemini response text was empty.');
        }

        return implode("\n", $texts);
    }
}
