<?php

declare(strict_types=1);

namespace Tests\Unit\AiAssistant;

use App\Modules\AiAssistant\Support\OpenAiLlmProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OpenAiLlmProviderTest extends TestCase
{
    #[Test]
    public function allowed_models_includes_default_and_configured_list(): void
    {
        $provider = new OpenAiLlmProvider(
            apiKey: 'test-key',
            baseUrl: 'https://api.openai.com/v1',
            modelId: 'gpt-4o-mini',
            allowedModels: ['gpt-4o-mini', 'gpt-4o'],
        );

        $this->assertEqualsCanonicalizing(['gpt-4o-mini', 'gpt-4o'], $provider->allowedModels());
    }

    #[Test]
    public function resolve_model_rejects_unknown_override(): void
    {
        $provider = new OpenAiLlmProvider(
            apiKey: 'test-key',
            baseUrl: 'https://api.openai.com/v1',
            modelId: 'gpt-4o-mini',
            allowedModels: ['gpt-4o-mini', 'gpt-4o'],
        );

        $method = new \ReflectionMethod(OpenAiLlmProvider::class, 'resolveModel');
        $method->setAccessible(true);

        $this->assertSame('gpt-4o', $method->invoke($provider, 'gpt-4o'));
        $this->assertSame('gpt-4o-mini', $method->invoke($provider, 'unknown-model'));
    }
}
