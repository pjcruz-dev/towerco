<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Concerns\ValidatesTenantListQuery;
use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Notifications\Models\TenantNotification;
use App\Modules\EApproval\Services\EApprovalNotificationIndexService;
use App\Modules\EApproval\Support\EApprovalNotificationCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EApprovalNotificationIndexController extends AbstractApiController
{
    use ValidatesTenantListQuery;

    public function __invoke(Request $request, EApprovalNotificationIndexService $index): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:view'), 403);

        $query = $this->validatedTenantListQuery($request);

        $validated = $request->validate([
            'category' => ['sometimes', 'string', Rule::in([
                EApprovalNotificationCategory::ACTION,
                EApprovalNotificationCategory::UPDATE,
            ])],
            'unread_only' => ['sometimes', 'boolean'],
        ]);

        $category = isset($validated['category']) ? (string) $validated['category'] : null;
        $unreadOnly = filter_var($validated['unread_only'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $paginator = $index->paginate(
            (string) $request->user()->id,
            $query['page'],
            $query['per_page'],
            $category,
            $unreadOnly,
        );

        return $this->okWithMeta(
            $paginator->getCollection()
                ->map(static fn (TenantNotification $n) => $n->toPayload())
                ->values()
                ->all(),
            [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }
}
