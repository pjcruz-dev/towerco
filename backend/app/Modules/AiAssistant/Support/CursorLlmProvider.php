<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Support;

use App\Modules\AiAssistant\Contracts\LlmProviderInterface;
use App\Modules\AiAssistant\DTOs\LlmCompletionResult;
use App\Modules\AiAssistant\DTOs\LlmPrompt;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cursor Cloud Agents API adapter for grounded chat answers (no-repo agent).
 * Requires CURSOR_API_KEY when AI_ASSISTANT_LLM_PROVIDER=cursor.
 *
 * Note: Cursor does not expose embeddings — keep AI_ASSISTANT_EMBEDDING_PROVIDER on local or openai.
 */
final class CursorLlmProvider implements LlmProviderInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly string $modelId,
        private readonly int $maxWaitSeconds = 120,
        private readonly int $pollIntervalMs = 1500,
        private readonly int $requestTimeoutSeconds = 30,
    ) {}

    public function complete(LlmPrompt $prompt): LlmCompletionResult
    {
        $this->assertConfigured();

        $started = hrtime(true);
        $endpoint = rtrim($this->baseUrl, '/').'/agents';

        $createResponse = Http::withToken($this->apiKey)
            ->acceptJson()
            ->timeout($this->requestTimeoutSeconds)
            ->post($endpoint, [
                'prompt' => [
                    'text' => $this->buildPromptText($prompt),
                ],
                'model' => [
                    'id' => $this->modelId,
                ],
            ]);

        if (! $createResponse->successful()) {
            $this->throwForFailedResponse('Cursor agent create', $createResponse->status(), $createResponse->body());
        }

        /** @var array<string, mixed> $payload */
        $payload = $createResponse->json() ?? [];
        $agentId = is_array($payload['agent'] ?? null) ? ($payload['agent']['id'] ?? null) : null;
        $runId = is_array($payload['run'] ?? null) ? ($payload['run']['id'] ?? null) : null;

        if (! is_string($agentId) || $agentId === '' || ! is_string($runId) || $runId === '') {
            throw new RuntimeException('Cursor agent create response did not include agent/run identifiers.');
        }

        try {
            $answer = $this->pollRunResult($agentId, $runId);
        } finally {
            $this->archiveAgentQuietly($agentId);
        }

        $insufficient = $prompt->chunks === []
            || str_contains(mb_strtolower($answer), 'do not have enough')
            || str_contains(mb_strtolower($answer), 'not enough approved');

        return new LlmCompletionResult(
            answer: $answer,
            modelName: $this->modelId,
            promptTokens: null,
            completionTokens: null,
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
                'Cursor LLM requires CURSOR_API_KEY (or AI_ASSISTANT_CURSOR_API_KEY). Set the key or use AI_ASSISTANT_LLM_PROVIDER=local.',
            );
        }
    }

    private function buildPromptText(LlmPrompt $prompt): string
    {
        return trim($prompt->system)."\n\n---\n\n".trim($prompt->user);
    }

    private function pollRunResult(string $agentId, string $runId): string
    {
        $deadline = time() + max(10, $this->maxWaitSeconds);
        $url = rtrim($this->baseUrl, '/').'/agents/'.$agentId.'/runs/'.$runId;

        while (time() < $deadline) {
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->timeout($this->requestTimeoutSeconds)
                ->get($url);

            if (! $response->successful()) {
                $this->throwForFailedResponse('Cursor agent run poll', $response->status(), $response->body());
            }

            /** @var array<string, mixed> $run */
            $run = $response->json() ?? [];
            $status = is_string($run['status'] ?? null) ? strtoupper($run['status']) : '';

            if ($status === 'FINISHED') {
                $result = trim((string) ($run['result'] ?? ''));
                if ($result === '') {
                    throw new RuntimeException('Cursor agent run finished without a result message.');
                }

                return $result;
            }

            if (in_array($status, ['ERROR', 'CANCELLED', 'EXPIRED'], true)) {
                throw new RuntimeException('Cursor agent run ended with status '.$status.'.');
            }

            usleep(max(250, $this->pollIntervalMs) * 1000);
        }

        throw new RuntimeException('Cursor agent run timed out after '.$this->maxWaitSeconds.' seconds.');
    }

    private function archiveAgentQuietly(string $agentId): void
    {
        try {
            Http::withToken($this->apiKey)
                ->acceptJson()
                ->timeout($this->requestTimeoutSeconds)
                ->post(rtrim($this->baseUrl, '/').'/agents/'.$agentId.'/archive');
        } catch (\Throwable) {
            // Best-effort cleanup; do not fail the user answer.
        }
    }

    private function throwForFailedResponse(string $operation, int $status, string $body): void
    {
        if ($status === 429) {
            throw new AssistantProviderQuotaExceededException(
                provider: 'cursor',
                message: $operation.' failed (HTTP 429): '.$body,
            );
        }

        throw new RuntimeException($operation.' failed (HTTP '.$status.'): '.$body);
    }
}
