<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Http\Controllers\V1;

use App\Core\Http\Concerns\ValidatesTenantListQuery;
use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AiAssistant\Services\TenantKnowledgeService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantKnowledgeIndexController extends AbstractApiController
{
    use ValidatesTenantListQuery;

    public function __invoke(Request $request, TenantKnowledgeService $knowledge): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof TenantUser && $user->can('ai_assistant:knowledge:manage'), 403);

        $listQuery = $this->validatedTenantListQuery($request);
        $status = $request->validate([
            'status' => ['sometimes', 'nullable', 'string', 'in:draft,published,archived'],
        ])['status'] ?? null;

        $paginator = $knowledge->paginate(
            $listQuery['page'],
            $listQuery['per_page'],
            $listQuery['search'],
            is_string($status) ? $status : null,
        );

        $data = collect($paginator->items())
            ->map(fn ($source) => $knowledge->asListRow($source))
            ->all();

        return $this->okWithMeta($data, [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ]);
    }
}
