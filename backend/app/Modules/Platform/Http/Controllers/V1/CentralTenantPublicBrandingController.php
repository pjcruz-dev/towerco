<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\Tenant;
use App\Modules\Platform\Services\TenantBrandingAssetService;
use App\Modules\Platform\Support\TenantThemeTokensValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stancl\Tenancy\Database\Models\Domain;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CentralTenantPublicBrandingController extends AbstractApiController
{
    /**
     * Public tenant branding (theme tokens + logo URLs) resolved by registered hostname.
     * Does not reveal whether a domain exists: unknown hosts receive default empty branding.
     */
    public function show(Request $request): JsonResponse
    {
        $domain = $this->normalizeDomain($request->query('domain'));
        if ($domain === null) {
            return $this->ok(TenantThemeTokensValidator::sanitizeForPublic(null));
        }

        /** @var Domain|null $record */
        $record = Domain::query()->where('domain', $domain)->first();
        $raw = $record?->tenant !== null ? $record->tenant->theme_tokens : null;

        return $this->ok(TenantThemeTokensValidator::sanitizeForPublic(
            is_array($raw) ? $raw : null,
        ));
    }

    public function asset(
        Request $request,
        string $asset,
        TenantBrandingAssetService $assets,
    ): StreamedResponse {
        if (! TenantBrandingAssetService::isKind($asset)) {
            throw new NotFoundHttpException;
        }

        $tenant = $this->resolveTenant($request);
        if ($tenant === null) {
            abort(404);
        }

        return $assets->stream($tenant, $asset);
    }

    private function resolveTenant(Request $request): ?Tenant
    {
        $tenantId = trim((string) $request->query('tenant', ''));
        if ($tenantId !== '' && preg_match('/^[0-9a-f-]{36}$/i', $tenantId) === 1) {
            /** @var Tenant|null $tenant */
            $tenant = Tenant::query()->find($tenantId);

            return $tenant;
        }

        $domain = $this->normalizeDomain($request->query('domain'));
        if ($domain === null) {
            return null;
        }

        /** @var Domain|null $record */
        $record = Domain::query()->where('domain', $domain)->first();

        return $record?->tenant instanceof Tenant ? $record->tenant : null;
    }

    private function normalizeDomain(mixed $domain): ?string
    {
        if (! is_string($domain)) {
            return null;
        }

        $host = strtolower(trim($domain));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        if ($host === '' || strlen($host) > 255) {
            return null;
        }

        if (! preg_match('/^[a-z0-9.-]+$/', $host)) {
            return null;
        }

        return $host;
    }
}
