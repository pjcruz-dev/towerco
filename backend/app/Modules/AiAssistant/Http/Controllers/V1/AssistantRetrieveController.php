<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AiAssistant\Http\Requests\RetrieveAssistantKnowledgeRequest;
use App\Modules\AiAssistant\Services\KnowledgeRetrievalService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;

class AssistantRetrieveController extends AbstractApiController
{
    public function __invoke(
        RetrieveAssistantKnowledgeRequest $request,
        KnowledgeRetrievalService $retrieval,
    ): JsonResponse {
        abort_unless((bool) config('ai_assistant.enabled', true), 503, __('AI Assistant is disabled.'));

        $user = $request->user();
        abort_unless($user instanceof TenantUser && $user->can('ai_assistant:use'), 403);

        $payload = $request->validatedPayload();
        $chunks = $retrieval->retrieve(
            $user,
            $payload['query'],
            isset($payload['top_k']) ? (int) $payload['top_k'] : null,
        );

        return $this->ok([
            'query' => $payload['query'],
            'chunks' => array_map(
                static fn ($chunk): array => $chunk->toArray(),
                $chunks,
            ),
            'citations' => array_map(
                static fn ($chunk): array => $chunk->toCitationArray(),
                $chunks,
            ),
        ]);
    }
}
