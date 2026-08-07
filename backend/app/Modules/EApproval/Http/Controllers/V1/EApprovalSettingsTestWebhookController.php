<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalSettingsTestWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalSettingsTestWebhookController extends AbstractApiController
{
    public function __invoke(Request $request, EApprovalSettingsTestWebhookService $service): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:settings:manage'), 403);

        $result = $service->send();

        return $this->ok($result);
    }
}
