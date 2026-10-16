<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AiAssistant\Contracts\LlmProviderInterface;
use App\Modules\AiAssistant\Support\AssistantAudience;
use App\Modules\AiAssistant\Support\AssistantModelPricing;
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

        $provider = strtolower((string) config('ai_assistant.llm_provider', 'cursor'));
        $models = AssistantModelPricing::sortHighestFirst($this->selectableModels($llm));
        $adminBuilder = AssistantAudience::isTenantAdmin($request->user());

        return $this->ok([
            'enabled' => true,
            'assistant_mode' => $adminBuilder ? 'admin_builder' : 'help',
            'llm_provider' => $provider,
            'model_name' => $llm->modelName(),
            'models' => $models,
            'model_labels' => $llm instanceof CursorLlmProvider ? $llm->modelLabels() : [],
            'model_estimates' => AssistantModelPricing::previews($models),
            'supports_model_select' => count($models) > 1,
            'retrieval_enabled' => false,
            'greeting' => $adminBuilder
                ? 'What should we change in this workspace?'
                : 'Hey! What are we building or fixing today?',
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
