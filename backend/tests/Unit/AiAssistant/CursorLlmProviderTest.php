<?php

declare(strict_types=1);

namespace Tests\Unit\AiAssistant;

use App\Modules\AiAssistant\Support\CursorLlmProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CursorLlmProviderTest extends TestCase
{
    #[Test]
    public function allowed_models_includes_default_and_configured_list(): void
    {
        $provider = new CursorLlmProvider(
            apiKey: 'test-key',
            baseUrl: 'https://api.cursor.com/v1',
            modelId: 'composer-2',
            allowedModels: ['composer-1', 'composer-2'],
        );

        $this->assertEqualsCanonicalizing(['composer-2', 'composer-1'], $provider->allowedModels());
    }

    #[Test]
    public function resolve_model_applies_aliases(): void
    {
        config([
            'ai_assistant.cursor.model_aliases' => [
                'auto-smart' => 'default',
                'composer-2' => 'composer-2.5',
            ],
        ]);

        $provider = new CursorLlmProvider(
            apiKey: 'test-key',
            baseUrl: 'https://api.cursor.com/v1',
            modelId: 'composer-2.5',
            allowedModels: ['composer-2.5', 'default', 'grok-4.5'],
        );

        $method = new \ReflectionMethod(CursorLlmProvider::class, 'resolveModel');
        $method->setAccessible(true);

        $this->assertSame('default', $method->invoke($provider, 'auto-smart'));
        $this->assertSame('composer-2.5', $method->invoke($provider, 'composer-2'));
    }
}
