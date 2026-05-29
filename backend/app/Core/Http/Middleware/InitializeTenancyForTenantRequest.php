<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves tenant context for tenant API routes: by HTTP Host on tenant domains (recommended for production), or on
 * central API hosts when `toweros.allow_tenant_on_central_host` is true and the client sends `X-Tenant-Id` (UUID) or
 * `X-Tenant-Domain` (registered tenant hostname). In production, domain-header resolution additionally enforces HTTPS
 * and Origin/Referer alignment when configured.
 */
class InitializeTenancyForTenantRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $centralDomains = config('tenancy.central_domains');
        $host = $request->getHost();

        if (! in_array($host, $centralDomains, true)) {
            return $this->onTenantHost($request, $next);
        }

        if (! (bool) config('toweros.allow_tenant_on_central_host')) {
            return app(PreventAccessFromCentralDomains::class)->handle($request, $next);
        }

        $tenantId = $request->header('X-Tenant-Id');
        if (is_string($tenantId) && $tenantId !== '' && Str::isUuid($tenantId)) {
            /** @var Tenant|null $tenant */
            $tenant = Tenant::query()->find($tenantId);
            if (! $tenant) {
                abort(Response::HTTP_NOT_FOUND, __('Tenant not found.'));
            }

            tenancy()->initialize($tenant);

            return $next($request);
        }

        $tenantDomain = $this->normalizeTenantDomainHeader($request->header('X-Tenant-Domain'));
        if ($tenantDomain !== null) {
            $this->assertSafeCentralHostTenantDomainResolution($request, $tenantDomain);

            /** @var Domain|null $domain */
            $domain = Domain::query()->where('domain', $tenantDomain)->first();
            if ($domain?->tenant) {
                tenancy()->initialize($domain->tenant);

                return $next($request);
            }

            abort(Response::HTTP_NOT_FOUND, __('Tenant domain not found.'));
        }

        if ($this->isTenantAuthRoute($request)) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, __(
                'Tenant context is required. Open your tenant sign-in URL (for example http://staging.quantum.localhost:3001/login) instead of the platform host.',
            ));
        }

        return app(PreventAccessFromCentralDomains::class)->handle($request, $next);
    }

    private function isTenantAuthRoute(Request $request): bool
    {
        return $request->is('api/*/auth/*') || $request->is('*/auth/login');
    }

    private function normalizeTenantDomainHeader(mixed $header): ?string
    {
        if (! is_string($header)) {
            return null;
        }

        $host = strtolower(trim($header));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        if ($host === '' || strlen($host) > 255) {
            return null;
        }

        if (! preg_match('/^[a-z0-9.-]+$/', $host)) {
            return null;
        }

        return $host;
    }

    private function assertSafeCentralHostTenantDomainResolution(Request $request, string $tenantDomain): void
    {
        if ((bool) config('toweros.tenant_central_domain_header_require_https') && ! $request->isSecure()) {
            abort(Response::HTTP_FORBIDDEN, __('Tenant domain identification requires HTTPS.'));
        }

        if (! (bool) config('toweros.tenant_central_domain_header_require_origin_match')) {
            return;
        }

        $browserHost = $this->browserHostnameFromForwardedBrowserHeaders($request);
        if ($browserHost === null) {
            abort(
                Response::HTTP_FORBIDDEN,
                __('Tenant domain identification requires a verifiable Origin or Referer host.'),
            );
        }

        if ($browserHost !== $tenantDomain) {
            abort(Response::HTTP_FORBIDDEN, __('Tenant domain header does not match the browser origin.'));
        }
    }

    private function browserHostnameFromForwardedBrowserHeaders(Request $request): ?string
    {
        foreach (['Origin', 'Referer'] as $headerName) {
            $host = $this->extractHostFromUrl($request->headers->get($headerName));
            if ($host !== null) {
                return $host;
            }
        }

        return null;
    }

    private function extractHostFromUrl(?string $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host']) || ! is_string($parts['host'])) {
            return null;
        }

        return strtolower($parts['host']);
    }

    private function onTenantHost(Request $request, Closure $next): Response
    {
        return app(InitializeTenancyByDomain::class)->handle($request, function (Request $inner) use ($next) {
            return app(PreventAccessFromCentralDomains::class)->handle($inner, $next);
        });
    }
}
