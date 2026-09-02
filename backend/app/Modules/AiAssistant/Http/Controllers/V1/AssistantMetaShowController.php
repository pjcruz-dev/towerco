<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AiAssistant\Contracts\LlmProviderInterface;
use App\Modules\AiAssistant\Support\GeminiLlmProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantMetaShowController extends AbstractApiController
{
    public function __invoke(Request $request, LlmProviderInterface $llm): JsonResponse
    {
        abort_unless($request->user()?->can('ai_assistant:use'), 403);
        abort_unless((bool) config('ai_assistant.enabled', true), 503, __('AI Assistant is disabled.'));

        $provider = strtolower((string) config('ai_assistant.llm_provider', 'local'));
        $models = [$llm->modelName()];
        if ($llm instanceof GeminiLlmProvider) {
            $models = $llm->allowedModels();
        }

        return $this->ok([
            'enabled' => true,
            'llm_provider' => $provider,
            'model_name' => $llm->modelName(),
            'models' => $models,
            'supports_model_select' => $llm instanceof GeminiLlmProvider,
            'greeting' => 'Hey! What are we building or fixing today?',
        ]);
    }
}
