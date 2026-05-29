<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures Sanctum resolves API tokens against tenant-scoped users after tenancy is initialized.
 */
class ConfigureTenantSanctumProvider
{
    public function handle(Request $request, Closure $next): Response
    {
        config([
            'auth.guards.sanctum.provider' => 'tenant_users',
        ]);

        return $next($request);
    }
}
