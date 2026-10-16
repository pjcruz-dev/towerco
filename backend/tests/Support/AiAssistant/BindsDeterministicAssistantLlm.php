<?php

declare(strict_types=1);

namespace Tests\Support\AiAssistant;

use App\Modules\AiAssistant\Contracts\LlmProviderInterface;
use App\Modules\AiAssistant\Services\Actions\AssistantActionRouter;
use App\Modules\AiAssistant\Services\Tools\AssistantToolFallbackPlanner;
use App\Modules\AiAssistant\Services\Tools\AssistantToolRouter;

trait BindsDeterministicAssistantLlm
{
    protected function bindDeterministicAssistantLlm(): void
    {
        $this->app->singleton(LlmProviderInterface::class, function ($app): LlmProviderInterface {
            return new DeterministicCatalogLlm(
                $app->make(AssistantActionRouter::class),
                $app->make(AssistantToolRouter::class),
                $app->make(AssistantToolFallbackPlanner::class),
            );
        });
    }
}
