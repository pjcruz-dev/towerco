<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Notifications\Services\TenantNotificationService;
use App\Modules\Notifications\Support\TenantNotificationAccess;
use App\Modules\Notifications\Support\TenantNotificationModule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantNotificationMarkAllReadController extends AbstractApiController
{
    public function __invoke(Request $request, TenantNotificationService $service): JsonResponse
    {
        $user = $request->user();
        TenantNotificationAccess::abortUnlessCanAccessAny($user);

        $allowedModules = TenantNotificationAccess::allowedModulesFor($user);

        $validated = $request->validate([
            'category' => ['sometimes', 'string', Rule::in(['action', 'update'])],
            'module' => ['sometimes', 'string', Rule::in(TenantNotificationModule::all())],
        ]);

        $category = isset($validated['category']) ? (string) $validated['category'] : null;
        $module = isset($validated['module']) ? (string) $validated['module'] : null;

        if ($module !== null) {
            abort_unless(TenantNotificationAccess::canAccessModule($user, $module), 403);
        }

        $service->markAllRead((string) $user->id, $allowedModules, $category, $module);

        return $this->ok(['ok' => true]);
    }
}
