<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers\V1;

use App\Core\Http\Concerns\ValidatesTenantListQuery;
use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Notifications\Models\TenantNotification;
use App\Modules\Notifications\Services\TenantNotificationIndexService;
use App\Modules\Notifications\Support\TenantNotificationAccess;
use App\Modules\Notifications\Support\TenantNotificationModule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantNotificationIndexController extends AbstractApiController
{
    use ValidatesTenantListQuery;

    public function __invoke(Request $request, TenantNotificationIndexService $index): JsonResponse
    {
        $user = $request->user();
        TenantNotificationAccess::abortUnlessCanAccessAny($user);

        $allowedModules = TenantNotificationAccess::allowedModulesFor($user);

        $query = $this->validatedTenantListQuery($request);

        $validated = $request->validate([
            'category' => ['sometimes', 'string', Rule::in(['action', 'update'])],
            'module' => ['sometimes', 'string', Rule::in(TenantNotificationModule::all())],
            'unread_only' => ['sometimes', 'boolean'],
        ]);

        $module = isset($validated['module']) ? (string) $validated['module'] : null;
        if ($module !== null) {
            abort_unless(TenantNotificationAccess::canAccessModule($user, $module), 403);
        }

        $category = isset($validated['category']) ? (string) $validated['category'] : null;
        $unreadOnly = filter_var($validated['unread_only'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $paginator = $index->paginate(
            (string) $user->id,
            $allowedModules,
            $query['page'],
            $query['per_page'],
            $category,
            $unreadOnly,
            $module,
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
