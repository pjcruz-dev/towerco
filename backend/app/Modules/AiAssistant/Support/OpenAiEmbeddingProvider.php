<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Support;

use App\Modules\AiAssistant\Contracts\EmbeddingProviderInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OpenAI embeddings adapter (e.g. text-embedding-3-small).
 * Requires OPENAI_API_KEY when AI_ASSISTANT_EMBEDDING_PROVIDER=openai.
 */
final class OpenAiEmbeddingProvider implements EmbeddingProviderInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly string $modelId,
        private readonly int $dimensions,
        private readonly int $timeoutSeconds = 60,
    ) {}

    public function embed(string $text): array
    {
        $vectors = $this->embedMany([mb_substr($text, 0, 8000)]);

        return $vectors[0] ?? throw new RuntimeException('OpenAI embedding response was empty.');
    }

    public function embedMany(array $texts): array
    {
        $this->assertConfigured();

        if ($texts === []) {
            return [];
        }

        $endpoint = rtrim($this->baseUrl, '/').'/embeddings';
        $inputs = array_map(
            static fn (string $text): string => mb_substr($text, 0, 8000),
            array_values($texts),
        );

        $body = [
            'model' => $this->modelId,
            'input' => $inputs,
        ];
        if ($this->dimensions > 0) {
            $body['dimensions'] = $this->dimensions;
        }

        $response = Http::withToken($this->apiKey)
            ->acceptJson()
            ->timeout($this->timeoutSeconds)
            ->post($endpoint, $body);

        if (! $response->successful()) {
            if ($response->status() === 429 && AssistantProviderErrorClassifier::openAiBodyIndicatesQuotaExceeded($response->body())) {
                throw new AssistantProviderQuotaExceededException(
                    provider: 'openai',
                    message: 'OpenAI embeddings failed (HTTP 429): '.$response->body(),
                );
            }

            throw new RuntimeException(
                'OpenAI embeddings failed (HTTP '.$response->status().'): '.$response->body(),
            );
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];
        $data = $payload['data'] ?? null;
        if (! is_array($data) || $data === []) {
            throw new RuntimeException('OpenAI embeddings response did not include data.');
        }

        usort($data, static function (mixed $a, mixed $b): int {
            $ai = is_array($a) ? (int) ($a['index'] ?? 0) : 0;
            $bi = is_array($b) ? (int) ($b['index'] ?? 0) : 0;

            return $ai <=> $bi;
        });

        $out = [];
        foreach ($data as $row) {
            if (! is_array($row)) {
                continue;
            }
            $embedding = $row['embedding'] ?? null;
            if (! is_array($embedding) || $embedding === []) {
                throw new RuntimeException('OpenAI embeddings response row missing embedding vector.');
            }
            $out[] = array_map(static fn (mixed $v): float => (float) $v, array_values($embedding));
        }

        if (count($out) !== count($inputs)) {
            throw new RuntimeException('OpenAI embeddings count did not match input count.');
        }

        return $out;
    }

    public function modelName(): string
    {
        return $this->modelId;
    }

    public function dimensions(): int
    {
        return $this->dimensions;
    }

    private function assertConfigured(): void
    {
        if (trim($this->apiKey) === '') {
            throw new RuntimeException(
                'OpenAI embeddings require OPENAI_API_KEY (or AI_ASSISTANT_OPENAI_API_KEY). Set the key or use AI_ASSISTANT_EMBEDDING_PROVIDER=local.',
            );
        }
    }
}
