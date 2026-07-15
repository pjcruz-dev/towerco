<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureTenantModule
{
    public function __construct(
        private readonly TenantEnabledModulesResolver $enabledModules,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $enabled = $this->enabledModules->resolveForCurrentTenant();

        if (! in_array($module, $enabled, true)) {
            abort(Response::HTTP_FORBIDDEN, __('This module is not enabled for your workspace.'));
        }

        return $next($request);
    }
}
