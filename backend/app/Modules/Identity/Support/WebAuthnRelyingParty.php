<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Models\Tenant;
use App\Modules\Tenancy\Support\FrontendDevUrl;
use Stancl\Tenancy\Database\Models\Domain;

/**
 * Resolves WebAuthn Relying Party ID and allowed origins for the current tenant hostname.
 */
final class WebAuthnRelyingParty
{
    public function rpId(): string
    {
        $host = $this->primaryHostname();
        if ($host !== null) {
            return strtolower($host);
        }

        $configured = parse_url(FrontendDevUrl::configuredBaseUrl(), PHP_URL_HOST);

        return is_string($configured) && $configured !== '' ? strtolower($configured) : 'localhost';
    }

    public function rpName(): string
    {
        $tenant = tenant();
        if ($tenant instanceof Tenant) {
            $slug = trim((string) ($tenant->slug ?? ''));
            if ($slug !== '') {
                return strtoupper($slug).' TowerOS';
            }
        }

        return (string) config('app.name', 'TowerOS');
    }

    /**
     * Origins the browser may present (scheme://host[:port]).
     *
     * @return list<string>
     */
    public function allowedOrigins(): array
    {
        $host = $this->rpId();
        $environment = tenant() instanceof Tenant
            ? (string) (tenant('environment') ?? 'local')
            : 'local';
        $scheme = FrontendDevUrl::schemeForTenantHost($host, $environment);
        $authority = FrontendDevUrl::authority($host);

        $origins = ["{$scheme}://{$authority}"];

        // Local convenience: allow configured frontend base when host is *.localhost.
        if (str_ends_with($host, '.localhost') || $host === 'localhost') {
            $configured = FrontendDevUrl::configuredBaseUrl();
            if ($configured !== '' && ! in_array($configured, $origins, true)) {
                $origins[] = $configured;
            }
        }

        $extra = config('toweros.tenant_passkeys.extra_allowed_origins', []);
        if (is_array($extra)) {
            foreach ($extra as $origin) {
                if (is_string($origin) && $origin !== '' && ! in_array($origin, $origins, true)) {
                    $origins[] = $origin;
                }
            }
        }

        return array_values(array_unique($origins));
    }

    private function primaryHostname(): ?string
    {
        $tenant = tenant();
        if (! $tenant instanceof Tenant) {
            return null;
        }

        $domain = Domain::query()->where('tenant_id', $tenant->id)->orderBy('id')->value('domain');

        return is_string($domain) && $domain !== '' ? strtolower($domain) : null;
    }
}
