<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Notifications\Services\TenantNotificationService;
use App\Modules\Notifications\Support\TenantNotificationAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantNotificationUnreadCountController extends AbstractApiController
{
    public function __invoke(Request $request, TenantNotificationService $service): JsonResponse
    {
        $user = $request->user();
        TenantNotificationAccess::abortUnlessCanAccessAny($user);

        $modules = TenantNotificationAccess::allowedModulesFor($user);

        return $this->ok([
            'count' => $service->unreadCount((string) $user->id, $modules),
        ]);
    }
}
