<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Support;

use App\Modules\AiAssistant\DTOs\RetrievedKnowledgeChunk;

/**
 * Combines vector similarity with lexical overlap so weak nearest-neighbor
 * hits are rejected instead of dumping the closest unrelated help article.
 */
final class AssistantRelevanceGate
{
    /**
     * @param  list<RetrievedKnowledgeChunk>  $chunks
     * @return list<RetrievedKnowledgeChunk>
     */
    public function filter(array $chunks, string $question): array
    {
        if ($chunks === []) {
            return [];
        }

        $minVector = (float) config('ai_assistant.retrieval.min_score', 0.05);
        $minLexical = (int) config('ai_assistant.retrieval.min_lexical_hits', 1);
        $minCombined = (float) config('ai_assistant.retrieval.min_combined_score', 0.12);

        $kept = [];
        foreach ($chunks as $chunk) {
            $lexicalHits = $this->lexicalHitCount($chunk, $question);
            $combined = $chunk->score + min(0.35, $lexicalHits * 0.08);

            if ($chunk->score < $minVector) {
                continue;
            }

            // Require at least one meaningful keyword overlap unless the vector
            // score is already strong (typical of real embeddings, not local hash).
            if ($lexicalHits < $minLexical && $chunk->score < 0.55) {
                continue;
            }

            if ($combined < $minCombined && $lexicalHits < $minLexical) {
                continue;
            }

            $kept[] = $chunk;
        }

        return $kept;
    }

    public function isRelevant(RetrievedKnowledgeChunk $chunk, string $question): bool
    {
        return $this->filter([$chunk], $question) !== [];
    }

    private function lexicalHitCount(RetrievedKnowledgeChunk $chunk, string $question): int
    {
        $haystack = mb_strtolower($chunk->title.' '.($chunk->slug ?? '').' '.$chunk->body());
        $hits = 0;

        foreach ($this->meaningfulTokens($question) as $token) {
            if (str_contains($haystack, $token)) {
                $hits++;
            }
        }

        return $hits;
    }

    /**
     * @return list<string>
     */
    private function meaningfulTokens(string $question): array
    {
        static $stopwords = [
            'the', 'and', 'for', 'are', 'was', 'were', 'have', 'has', 'had', 'you', 'your',
            'our', 'can', 'how', 'what', 'when', 'where', 'why', 'who', 'does', 'did', 'this',
            'that', 'with', 'from', 'about', 'into', 'they', 'them', 'there', 'here', 'will',
            'would', 'should', 'could', 'please', 'tell', 'give', 'want', 'need', 'know',
            'many', 'much', 'some', 'any', 'all', 'get', 'got', 'let', 'like', 'just',
            'status', 'of',
        ];

        $parts = preg_split('/[^a-z0-9]+/u', mb_strtolower($question)) ?: [];
        $tokens = [];
        foreach ($parts as $token) {
            if (mb_strlen($token) >= 4 && ! in_array($token, $stopwords, true)) {
                $tokens[] = $token;
            }
        }

        return array_values(array_unique($tokens));
    }
}
