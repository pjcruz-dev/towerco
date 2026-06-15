<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalInAppNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalNotificationUnreadCountController extends AbstractApiController
{
    public function __invoke(Request $request, EApprovalInAppNotificationService $service): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:view'), 403);

        return $this->ok(['count' => $service->unreadCount((string) $request->user()->id)]);
    }
}
