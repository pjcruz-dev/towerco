<?php

declare(strict_types=1);

namespace Tests\Unit\AiAssistant;

use App\Modules\AiAssistant\Support\GeminiLlmProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

final class GeminiLlmProviderFallbackTest extends TestCase
{
    #[Test]
    public function candidate_models_put_requested_first_then_stable_fallbacks(): void
    {
        $provider = new GeminiLlmProvider(
            apiKey: 'test-key',
            baseUrl: 'https://generativelanguage.googleapis.com/v1beta',
            modelId: 'gemini-3.6-flash',
            allowedModels: [
                'gemini-3.7-flash',
                'gemini-3.6-flash',
                'gemini-flash-latest',
                'gemini-3.5-flash-lite',
            ],
        );

        $method = new ReflectionMethod(GeminiLlmProvider::class, 'candidateModels');
        $method->setAccessible(true);
        /** @var list<string> $models */
        $models = $method->invoke($provider, 'gemini-3.7-flash');

        $this->assertSame('gemini-3.7-flash', $models[0]);
        $this->assertContains('gemini-flash-latest', $models);
        $this->assertContains('gemini-3.6-flash', $models);
    }
}
