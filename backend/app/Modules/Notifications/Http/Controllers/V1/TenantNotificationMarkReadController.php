<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Notifications\Models\TenantNotification;
use App\Modules\Notifications\Services\TenantNotificationService;
use App\Modules\Notifications\Support\TenantNotificationAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantNotificationMarkReadController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        TenantNotification $notification,
        TenantNotificationService $service,
    ): JsonResponse {
        $user = $request->user();
        TenantNotificationAccess::abortUnlessCanAccessAny($user);
        abort_unless((string) $notification->user_id === (string) $user->id, 404);
        abort_unless(
            TenantNotificationAccess::canAccessModule($user, (string) $notification->module),
            404,
        );

        $service->markRead((string) $user->id, (string) $notification->id);

        return $this->ok(['ok' => true]);
    }
}
