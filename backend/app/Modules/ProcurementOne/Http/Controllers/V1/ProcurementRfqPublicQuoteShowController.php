<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementRfqPublicBidService;
use App\Modules\ProcurementOne\Services\ProcurementRfqVendorInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementRfqPublicQuoteShowController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $token,
        ProcurementRfqVendorInvitationService $invitations,
        ProcurementRfqPublicBidService $quotes,
    ): JsonResponse {
        $invitation = $invitations->resolveActiveInvitation($token);

        return $this->ok($quotes->quotePayload($invitation));
    }
}
