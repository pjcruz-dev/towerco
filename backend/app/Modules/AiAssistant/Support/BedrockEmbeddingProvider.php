<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Support;

use App\Modules\AiAssistant\Contracts\EmbeddingProviderInterface;
use RuntimeException;

/**
 * Amazon Bedrock Titan embeddings adapter.
 * Requires aws/aws-sdk-php and AWS credentials when selected.
 */
final class BedrockEmbeddingProvider implements EmbeddingProviderInterface
{
    public function __construct(
        private readonly string $region,
        private readonly string $modelId,
        private readonly int $dimensions,
    ) {}

    public function embed(string $text): array
    {
        $clientClass = 'Aws\\BedrockRuntime\\BedrockRuntimeClient';
        if (! class_exists($clientClass)) {
            throw new RuntimeException(
                'Bedrock embeddings require aws/aws-sdk-php. Install it or set AI_ASSISTANT_EMBEDDING_PROVIDER=local.',
            );
        }

        /** @var object $client */
        $client = new $clientClass([
            'version' => 'latest',
            'region' => $this->region,
        ]);

        $body = json_encode([
            'inputText' => mb_substr($text, 0, 8000),
            'dimensions' => $this->dimensions,
            'normalize' => true,
        ], JSON_THROW_ON_ERROR);

        $result = $client->invokeModel([
            'modelId' => $this->modelId,
            'contentType' => 'application/json',
            'accept' => 'application/json',
            'body' => $body,
        ]);

        $payload = json_decode((string) $result['body'], true, 512, JSON_THROW_ON_ERROR);
        $embedding = $payload['embedding'] ?? null;
        if (! is_array($embedding) || $embedding === []) {
            throw new RuntimeException('Bedrock embedding response did not include an embedding vector.');
        }

        return array_map(static fn (mixed $v): float => (float) $v, array_values($embedding));
    }

    public function embedMany(array $texts): array
    {
        $out = [];
        foreach ($texts as $text) {
            $out[] = $this->embed($text);
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
}
