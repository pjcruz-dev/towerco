<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Notifications\Models\TenantNotification;
use App\Modules\Notifications\Support\TenantNotificationModule;
use App\Modules\EApproval\Services\EApprovalInAppNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalNotificationMarkReadController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        TenantNotification $notification,
        EApprovalInAppNotificationService $service,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:view'), 403);
        abort_unless((string) $notification->module === TenantNotificationModule::E_APPROVAL, 404);
        abort_unless((string) $notification->user_id === (string) $request->user()->id, 404);

        $service->markRead((string) $request->user()->id, (string) $notification->id);

        return $this->ok(['ok' => true]);
    }
}
