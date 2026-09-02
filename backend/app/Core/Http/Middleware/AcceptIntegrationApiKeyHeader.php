<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accept X-API-Key header or ?api_key= query as Sanctum Bearer token for integration routes.
 */
class AcceptIntegrationApiKeyHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        if (is_string($request->bearerToken()) && $request->bearerToken() !== '') {
            return $next($request);
        }

        $key = trim((string) $request->header('X-API-Key', ''));
        if ($key === '') {
            $key = trim((string) $request->query('api_key', ''));
        }

        if ($key !== '') {
            $request->headers->set('Authorization', 'Bearer '.$key);
        }

        return $next($request);
    }
}
