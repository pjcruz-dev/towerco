<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Contracts;

interface EmbeddingProviderInterface
{
    /**
     * @return list<float>
     */
    public function embed(string $text): array;

    /**
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embedMany(array $texts): array;

    public function modelName(): string;

    public function dimensions(): int;
}
