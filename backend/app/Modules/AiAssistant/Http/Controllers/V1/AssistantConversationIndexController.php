<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Http\Controllers\V1;

use App\Core\Http\Concerns\ValidatesTenantListQuery;
use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AiAssistant\Services\ConversationService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantConversationIndexController extends AbstractApiController
{
    use ValidatesTenantListQuery;

    public function __invoke(Request $request, ConversationService $conversations): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof TenantUser && $user->can('ai_assistant:use'), 403);

        $listQuery = $this->validatedTenantListQuery($request);
        $paginator = $conversations->paginateForViewer(
            $user,
            $listQuery['page'],
            $listQuery['per_page'],
            $listQuery['search'],
        );

        $data = collect($paginator->items())
            ->map(fn ($conversation) => $conversations->asListRow($conversation))
            ->all();

        return $this->okWithMeta($data, [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ]);
    }
}
