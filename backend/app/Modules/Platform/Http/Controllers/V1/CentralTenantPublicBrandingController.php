<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Platform\Support\TenantThemeTokensValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stancl\Tenancy\Database\Models\Domain;

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
