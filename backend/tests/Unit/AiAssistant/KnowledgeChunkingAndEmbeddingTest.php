<?php

declare(strict_types=1);

namespace Tests\Unit\AiAssistant;

use App\Modules\AiAssistant\Support\KnowledgeTextChunker;
use App\Modules\AiAssistant\Support\LocalHashEmbeddingProvider;
use App\Modules\AiAssistant\Support\VectorMath;
use Tests\TestCase;

final class KnowledgeChunkingAndEmbeddingTest extends TestCase
{
    public function test_chunker_splits_with_overlap(): void
    {
        $chunker = new KnowledgeTextChunker(size: 40, overlap: 10);
        $text = str_repeat('abcdefghij ', 20);
        $chunks = $chunker->chunk($text);

        $this->assertGreaterThan(1, count($chunks));
        $this->assertNotSame('', $chunks[0]);
    }

    public function test_local_embedding_is_deterministic_and_normalized(): void
    {
        $provider = new LocalHashEmbeddingProvider(dimensions: 64);
        $a = $provider->embed('How do I create an e-approval request?');
        $b = $provider->embed('How do I create an e-approval request?');
        $c = $provider->embed('Completely unrelated fiber route geometry');

        $this->assertSame($a, $b);
        $this->assertCount(64, $a);

        $norm = 0.0;
        foreach ($a as $value) {
            $norm += $value * $value;
        }
        $this->assertEqualsWithDelta(1.0, sqrt($norm), 0.0001);

        $similar = VectorMath::cosineSimilarity($a, $b);
        $different = VectorMath::cosineSimilarity($a, $c);
        $this->assertEqualsWithDelta(1.0, $similar, 0.0001);
        $this->assertLessThan($similar, $different);
    }
}
