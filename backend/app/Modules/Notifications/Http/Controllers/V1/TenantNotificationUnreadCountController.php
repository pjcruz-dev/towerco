<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Notifications\Services\TenantNotificationService;
use App\Modules\Notifications\Support\TenantNotificationAccess;
use App\Modules\Notifications\Support\TenantNotificationShell;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantNotificationUnreadCountController extends AbstractApiController
{
    public function __invoke(Request $request, TenantNotificationService $service): JsonResponse
    {
        $user = $request->user();
        TenantNotificationAccess::abortUnlessCanAccessAny($user);

        $modules = TenantNotificationAccess::allowedModulesFor($user);
        $shell = $request->validate([
            'shell' => ['sometimes', 'string', Rule::in(TenantNotificationShell::all())],
        ])['shell'] ?? null;

        return $this->ok([
            'count' => $service->unreadCount((string) $user->id, $modules, is_string($shell) ? $shell : null),
        ]);
    }
}
