<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Modules\AdminOne\Services\TenantIntegrationApiKeyService;
use App\Modules\Identity\Models\TenantUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires a Sanctum token with ability "integration" (no interactive session).
 */
class EnsureIntegrationApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof TenantUser) {
            return response()->json(['message' => __('Unauthenticated.')], 401);
        }

        if (! $user->isActive()) {
            return response()->json(['message' => __('API key owner is inactive.')], 401);
        }

        if (! $user->tokenCan(TenantIntegrationApiKeyService::ABILITY)) {
            return response()->json(['message' => __('Integration API key required.')], 403);
        }

        return $next($request);
    }
}
