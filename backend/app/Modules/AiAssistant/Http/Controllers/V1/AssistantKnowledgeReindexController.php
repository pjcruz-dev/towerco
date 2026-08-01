<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AiAssistant\Services\TenantKnowledgeService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AssistantKnowledgeReindexController extends AbstractApiController
{
    public function __invoke(Request $request, string $source, TenantKnowledgeService $knowledge): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof TenantUser && $user->can('ai_assistant:knowledge:manage'), 403);

        $sync = (bool) $request->boolean('sync');
        $model = $knowledge->findTenantSourceOrFail($source);

        try {
            $result = $knowledge->reindex($user, $model, $sync);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        $fresh = $knowledge->findTenantSourceOrFail($source);

        return $this->ok([
            'ingest' => $result,
            'source' => $knowledge->asDetail($fresh),
        ]);
    }
}
