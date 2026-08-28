<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ops diagnostic: what client IP Laravel resolves behind nginx/Docker/CloudFront.
 * Safe to expose — returns only request metadata the client already knows.
 */
final class CentralPublicClientIpController extends AbstractApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        return $this->ok([
            'ip' => $request->ip(),
            'ips' => $request->ips(),
            'x_forwarded_for' => $request->header('X-Forwarded-For'),
            'x_real_ip' => $request->header('X-Real-IP'),
            'remote_addr' => $request->server('REMOTE_ADDR'),
            'trusted_proxies_env' => env('TRUSTED_PROXIES'),
        ]);
    }
}
