<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementVendorInboxService;
use App\Modules\ProcurementOne\Services\ProcurementVendorInboxTokenService;
use Illuminate\Http\JsonResponse;

final class ProcurementVendorInboxShowController extends AbstractApiController
{
    public function __invoke(
        string $token,
        ProcurementVendorInboxTokenService $tokens,
        ProcurementVendorInboxService $inbox,
    ): JsonResponse {
        $vendor = $tokens->resolveVendor($token);

        return $this->ok($inbox->inboxPayload($vendor));
    }
}
