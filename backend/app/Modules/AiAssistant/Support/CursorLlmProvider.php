<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Support;

use App\Modules\AiAssistant\Contracts\LlmProviderInterface;
use App\Modules\AiAssistant\DTOs\LlmCompletionResult;
use App\Modules\AiAssistant\DTOs\LlmPrompt;
use App\Modules\AiAssistant\Services\TenantLlmCredentialService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
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
        /** @var list<string> */
        private readonly array $allowedModels = [],
        private readonly int $maxWaitSeconds = 120,
        private readonly int $pollIntervalMs = 1500,
        private readonly int $requestTimeoutSeconds = 30,
    ) {}

    private ?string $resolvedKey = null;

    private ?string $resolvedForTenant = null;

    /** @var list<array{id: string, label: string}>|null */
    private ?array $catalog = null;

    /** @var (callable(string): void)|null */
    private $waitListener = null;

    /** @var (callable(array{kind: string, text: string, name?: string, status?: string}): void)|null */
    private $deltaListener = null;

    /**
     * Called with the Cursor run status while a reply is still in progress.
     *
     * @param  (callable(string): void)|null  $listener
     */
    public function listenWhileWaiting(?callable $listener): void
    {
        $this->waitListener = $listener;
    }

    /**
     * @param  (callable(array{kind: string, text: string, name?: string, status?: string}): void)|null  $listener
     */
    public function listenToDeltas(?callable $listener): void
    {
        $this->deltaListener = $listener;
    }

    public function complete(LlmPrompt $prompt): LlmCompletionResult
    {
        $this->assertConfigured();
        $this->notifyWaiting('CREATING');

        $model = $this->resolveModel($prompt->modelOverride);
        $started = hrtime(true);
        $endpoint = rtrim($this->baseUrl, '/').'/agents';

        $createResponse = $this->cursorHttp($this->requestTimeoutSeconds)
            ->post($endpoint, [
                'prompt' => [
                    'text' => $this->buildPromptText($prompt),
                ],
                'model' => [
                    'id' => $model,
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
            $answer = $this->streamRunResult($agentId, $runId) ?? $this->pollRunResult($agentId, $runId);
        } finally {
            $this->archiveAgentQuietly($agentId);
        }

        $insufficient = $prompt->chunks === []
            || str_contains(mb_strtolower($answer), 'do not have enough')
            || str_contains(mb_strtolower($answer), 'not enough approved');

        return new LlmCompletionResult(
            answer: $answer,
            modelName: $model,
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

    /**
     * @return list<string>
     */
    public function allowedModels(): array
    {
        $live = array_column($this->catalog(), 'id');
        $models = $live !== [] ? $live : ($this->allowedModels !== [] ? $this->allowedModels : [$this->modelId]);
        if (! in_array($this->modelId, $models, true)) {
            array_unshift($models, $this->modelId);
        }

        return array_values(array_unique($models));
    }

    /**
     * @return array<string, string>
     */
    public function modelLabels(): array
    {
        $labels = [];
        foreach ($this->catalog() as $row) {
            $labels[$row['id']] = $row['label'];
        }

        return $labels;
    }

    private function resolveModel(?string $override): string
    {
        $candidate = trim((string) $override);
        if ($candidate === '') {
            return $this->modelId;
        }

        $aliases = (array) config('ai_assistant.cursor.model_aliases', []);
        $mapped = $aliases[$candidate] ?? null;
        if (is_string($mapped) && trim($mapped) !== '') {
            $candidate = trim($mapped);
        }

        $allowed = $this->allowedModels();
        if (! in_array($candidate, $allowed, true)) {
            return $this->modelId;
        }

        return $candidate;
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    private function catalog(): array
    {
        if ($this->catalog !== null) {
            return $this->catalog;
        }

        $apiKey = $this->resolvedApiKey();
        if ($apiKey === '') {
            return $this->catalog = [];
        }

        $tenantId = tenancy()->initialized ? (string) tenant('id') : 'central';
        $cacheKey = 'ai_assistant.cursor.models.'.$tenantId.'.'.hash('sha256', $apiKey.'|'.$this->baseUrl);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && $cached !== []) {
            return $this->catalog = $this->normalizeCatalog($cached);
        }

        try {
            $response = $this->cursorHttp(min(20, $this->requestTimeoutSeconds), retry: false)
                ->get(rtrim($this->baseUrl, '/').'/models');
        } catch (\Throwable) {
            return $this->catalog = [];
        }

        if (! $response->successful()) {
            return $this->catalog = [];
        }

        $rows = $this->normalizeCatalog($response->json('items'));
        if ($rows !== []) {
            Cache::put($cacheKey, $rows, 600);
        }

        return $this->catalog = $rows;
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    private function normalizeCatalog(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $rows = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $id = trim((string) ($item['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $label = trim((string) ($item['label'] ?? $item['displayName'] ?? ''));
            $rows[] = ['id' => $id, 'label' => $label !== '' ? $label : $id];
        }

        return $rows;
    }

    private function resolvedApiKey(): string
    {
        $tenantId = tenancy()->initialized ? (string) tenant('id') : '';
        if ($this->resolvedKey !== null && $this->resolvedForTenant === $tenantId) {
            return $this->resolvedKey;
        }

        $key = trim(app(TenantLlmCredentialService::class)->apiKeyFor('cursor'));
        $this->resolvedForTenant = $tenantId;

        return $this->resolvedKey = $key !== '' ? $key : trim($this->apiKey);
    }

    private function assertConfigured(): void
    {
        if ($this->resolvedApiKey() === '') {
            throw new RuntimeException(
                'Cursor LLM requires a workspace Cursor token in Settings, or CURSOR_API_KEY / AI_ASSISTANT_CURSOR_API_KEY.',
            );
        }
    }

    private function buildPromptText(LlmPrompt $prompt): string
    {
        return trim($prompt->system)."\n\n---\n\n".trim($prompt->user);
    }

    private function streamRunResult(string $agentId, string $runId): ?string
    {
        $url = rtrim($this->baseUrl, '/').'/agents/'.$agentId.'/runs/'.$runId.'/stream';

        try {
            $response = $this->cursorHttp(max(10, $this->maxWaitSeconds), retry: false)
                ->accept('text/event-stream')
                ->withOptions(['stream' => true])
                ->get($url);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $contentType = strtolower((string) $response->header('Content-Type'));
        if (! str_contains($contentType, 'text/event-stream')) {
            return null;
        }

        $body = $response->toPsrResponse()->getBody();
        $buffer = '';
        $event = 'message';
        $dataLines = [];
        $assistant = '';
        $final = null;

        $flush = function () use (&$event, &$dataLines, &$assistant, &$final): void {
            $parsed = $this->consumeStreamEvent($event, implode("\n", $dataLines), $assistant);
            $event = 'message';
            $dataLines = [];
            if ($parsed !== null) {
                $final = $parsed;
            }
        };

        while (! $body->eof()) {
            $chunk = $body->read(2048);
            if ($chunk === '') {
                break;
            }
            $buffer .= $chunk;
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = rtrim(substr($buffer, 0, $pos), "\r");
                $buffer = substr($buffer, $pos + 1);
                if ($line === '') {
                    $flush();
                    if (is_string($final) && $final !== '') {
                        return $final;
                    }

                    continue;
                }
                if (str_starts_with($line, ':')) {
                    continue;
                }
                if (str_starts_with($line, 'event:')) {
                    $event = trim(substr($line, 6));
                } elseif (str_starts_with($line, 'data:')) {
                    $dataLines[] = ltrim(substr($line, 5));
                }
            }
        }

        if ($dataLines !== []) {
            $flush();
        }

        if (is_string($final) && $final !== '') {
            return $final;
        }

        $assistant = trim($assistant);

        return $assistant !== '' ? $assistant : null;
    }

    private function consumeStreamEvent(string $event, string $data, string &$assistant): ?string
    {
        $payload = json_decode($data, true);
        if (! is_array($payload)) {
            $payload = [];
        }

        if ($event === 'status') {
            $status = is_string($payload['status'] ?? null) ? strtoupper($payload['status']) : '';
            if ($status !== '') {
                $this->notifyWaiting($status);
            }
            if (in_array($status, ['ERROR', 'CANCELLED', 'EXPIRED'], true)) {
                throw new RuntimeException('Cursor agent run ended with status '.$status.'.');
            }

            return null;
        }

        if ($event === 'thinking') {
            $text = is_string($payload['text'] ?? null) ? $payload['text'] : '';
            if ($text !== '') {
                $this->emitDelta(['kind' => 'thinking', 'text' => $text]);
            }

            return null;
        }

        if ($event === 'assistant') {
            $text = is_string($payload['text'] ?? null) ? $payload['text'] : '';
            if ($text !== '') {
                $assistant .= $text;
                $this->emitDelta(['kind' => 'assistant', 'text' => $text]);
            }

            return null;
        }

        if ($event === 'tool_call') {
            $name = is_string($payload['name'] ?? null) ? $payload['name'] : 'tool';
            $status = is_string($payload['status'] ?? null) ? $payload['status'] : 'running';
            $detail = is_string($payload['result'] ?? null) ? mb_substr($payload['result'], 0, 500) : '';
            $this->emitDelta([
                'kind' => 'tool',
                'text' => $detail,
                'name' => $name,
                'status' => $status,
            ]);

            return null;
        }

        if ($event === 'error') {
            $message = is_string($payload['message'] ?? null) ? trim($payload['message']) : '';
            throw new RuntimeException($message !== '' ? $message : 'Cursor agent stream failed.');
        }

        if ($event === 'result') {
            $status = is_string($payload['status'] ?? null) ? strtoupper($payload['status']) : '';
            if (in_array($status, ['ERROR', 'CANCELLED', 'EXPIRED'], true)) {
                throw new RuntimeException('Cursor agent run ended with status '.$status.'.');
            }
            $text = trim((string) ($payload['text'] ?? ''));
            if ($text === '') {
                $text = trim($assistant);
            }
            if ($text === '') {
                throw new RuntimeException('Cursor agent run finished without a result message.');
            }
            $this->notifyWaiting('FINISHED');

            return $text;
        }

        return null;
    }

    /**
     * @param  array{kind: string, text: string, name?: string, status?: string}  $delta
     */
    private function emitDelta(array $delta): void
    {
        if ($this->deltaListener === null) {
            return;
        }

        ($this->deltaListener)($delta);
    }

    private function pollRunResult(string $agentId, string $runId): string
    {
        $deadline = time() + max(10, $this->maxWaitSeconds);
        $url = rtrim($this->baseUrl, '/').'/agents/'.$agentId.'/runs/'.$runId;

        while (time() < $deadline) {
            $response = $this->cursorHttp($this->requestTimeoutSeconds, retry: false)
                ->get($url);

            if (! $response->successful()) {
                $this->throwForFailedResponse('Cursor agent run poll', $response->status(), $response->body());
            }

            /** @var array<string, mixed> $run */
            $run = $response->json() ?? [];
            $status = is_string($run['status'] ?? null) ? strtoupper($run['status']) : '';
            $this->notifyWaiting($status !== '' ? $status : 'RUNNING');

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

    private function notifyWaiting(string $status): void
    {
        if ($this->waitListener === null) {
            return;
        }

        ($this->waitListener)($status);
    }

    private function archiveAgentQuietly(string $agentId): void
    {
        try {
            $this->cursorHttp($this->requestTimeoutSeconds, retry: false)
                ->post(rtrim($this->baseUrl, '/').'/agents/'.$agentId.'/archive');
        } catch (\Throwable) {
            // Best-effort cleanup; do not fail the user answer.
        }
    }

    private function cursorHttp(int $timeoutSeconds, bool $retry = true): PendingRequest
    {
        $curl = [];
        if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
            $curl[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }

        $pending = Http::withToken($this->resolvedApiKey())
            ->acceptJson()
            ->connectTimeout(25)
            ->timeout(max(1, $timeoutSeconds))
            ->withOptions($curl === [] ? [] : ['curl' => $curl]);

        if ($retry) {
            $pending = $pending->retry(2, 1000);
        }

        return $pending;
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
