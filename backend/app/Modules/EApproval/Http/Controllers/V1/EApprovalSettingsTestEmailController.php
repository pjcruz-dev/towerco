<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalSettingsTestEmailService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalSettingsTestEmailController extends AbstractApiController
{
    public function __invoke(Request $request, EApprovalSettingsTestEmailService $service): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:settings:manage'), 403);

        /** @var TenantUser $user */
        $user = $request->user();

        $result = $service->sendToUser($user);

        return $this->ok([
            'message' => __('Test email sent. Check your inbox (and spam) for the TowerOS E-Approval test message.'),
            'sent_to' => $result['sent_to'],
            'mailer' => $result['mailer'],
        ]);
    }
}
