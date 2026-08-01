<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Support;

final class KnowledgeTextChunker
{
    public function __construct(
        private readonly int $size = 800,
        private readonly int $overlap = 120,
    ) {}

    /**
     * @return list<string>
     */
    public function chunk(string $text): array
    {
        $normalized = trim(preg_replace("/\n{3,}/", "\n\n", str_replace("\r\n", "\n", $text)) ?? $text);
        if ($normalized === '') {
            return [];
        }

        $size = max(100, $this->size);
        $overlap = max(0, min($this->overlap, $size - 1));

        if (mb_strlen($normalized) <= $size) {
            return [$normalized];
        }

        $chunks = [];
        $offset = 0;
        $length = mb_strlen($normalized);

        while ($offset < $length) {
            $slice = mb_substr($normalized, $offset, $size);
            $trimmed = trim($slice);
            if ($trimmed !== '') {
                $chunks[] = $trimmed;
            }

            if ($offset + $size >= $length) {
                break;
            }

            $offset += $size - $overlap;
        }

        return $chunks;
    }
}
