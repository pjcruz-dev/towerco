<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AiAssistant\Contracts\LlmProviderInterface;
use App\Modules\AiAssistant\Support\AssistantRateLimitReporter;
use App\Modules\AiAssistant\Support\CursorLlmProvider;
use App\Modules\AiAssistant\Support\GeminiLlmProvider;
use App\Modules\AiAssistant\Support\OpenAiLlmProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantMetaShowController extends AbstractApiController
{
    public function __invoke(Request $request, LlmProviderInterface $llm): JsonResponse
    {
        abort_unless($request->user()?->can('ai_assistant:use'), 403);
        abort_unless((bool) config('ai_assistant.enabled', true), 503, __('AI Assistant is disabled.'));

        $provider = strtolower((string) config('ai_assistant.llm_provider', 'local'));
        $models = $this->selectableModels($llm);

        return $this->ok([
            'enabled' => true,
            'llm_provider' => $provider,
            'model_name' => $llm->modelName(),
            'models' => $models,
            'supports_model_select' => count($models) > 1,
            'retrieval_enabled' => (bool) config('ai_assistant.retrieval.enabled', false),
            'greeting' => 'Hey! What are we building or fixing today?',
            'rate_limit' => AssistantRateLimitReporter::forRequest($request),
        ]);
    }

    /**
     * @return list<string>
     */
    private function selectableModels(LlmProviderInterface $llm): array
    {
        if ($llm instanceof GeminiLlmProvider || $llm instanceof CursorLlmProvider || $llm instanceof OpenAiLlmProvider) {
            return $llm->allowedModels();
        }

        return [$llm->modelName()];
    }
}
