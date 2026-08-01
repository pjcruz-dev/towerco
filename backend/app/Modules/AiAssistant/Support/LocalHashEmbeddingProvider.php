<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Support;

use App\Modules\AiAssistant\Contracts\EmbeddingProviderInterface;

/**
 * Deterministic local embeddings for development and tests (no external API).
 */
final class LocalHashEmbeddingProvider implements EmbeddingProviderInterface
{
    public function __construct(
        private readonly int $dimensions = 256,
    ) {}

    public function embed(string $text): array
    {
        $dims = max(32, $this->dimensions);
        $vector = array_fill(0, $dims, 0.0);
        $tokens = $this->tokenize($text);

        if ($tokens === []) {
            $vector[0] = 1.0;

            return $vector;
        }

        foreach ($tokens as $token) {
            $bucket = $this->bucket($token, $dims);
            $sign = (crc32('sign:'.$token) % 2 === 0) ? 1.0 : -1.0;
            $vector[$bucket] += $sign;
        }

        return $this->normalize($vector);
    }

    public function embedMany(array $texts): array
    {
        return array_map(fn (string $text): array => $this->embed($text), array_values($texts));
    }

    public function modelName(): string
    {
        return 'local-hash-v1';
    }

    public function dimensions(): int
    {
        return max(32, $this->dimensions);
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $text): array
    {
        $normalized = mb_strtolower($text);
        $normalized = preg_replace('/[^a-z0-9\s:_-]+/u', ' ', $normalized) ?? $normalized;
        $parts = preg_split('/\s+/', trim($normalized)) ?: [];

        return array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    private function bucket(string $token, int $dims): int
    {
        return (int) (crc32($token) % $dims);
    }

    /**
     * @param  list<float>  $vector
     * @return list<float>
     */
    private function normalize(array $vector): array
    {
        $sumSquares = 0.0;
        foreach ($vector as $value) {
            $sumSquares += $value * $value;
        }

        if ($sumSquares <= 0.0) {
            return $vector;
        }

        $norm = sqrt($sumSquares);

        return array_map(static fn (float $value): float => $value / $norm, $vector);
    }
}
