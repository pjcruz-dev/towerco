<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Microsoft Graph application (client credentials) — uses per-tenant Microsoft settings when tenancy is active.
 */
final class EntraGraphAppService
{
    public function __construct(
        private readonly TenantSsoConfigService $tenantMicrosoft,
    ) {}

    public function isConfigured(): bool
    {
        $this->bootstrapAzureConfig();

        $clientId = (string) config('services.azure.client_id', '');
        $clientSecret = (string) config('services.azure.client_secret', '');

        return $clientId !== '' && $clientSecret !== '';
    }

    public function getAppAccessToken(): ?string
    {
        if (! $this->bootstrapAzureConfig()) {
            return null;
        }

        $tenant = (string) config('services.azure.tenant', 'common');
        $cacheKey = 'entra_app_token:'.(tenant('id') ?? 'global').':'.$tenant;

        /** @var string|null $cached */
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::asForm()
            ->timeout(15)
            ->post("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token", [
                'client_id' => config('services.azure.client_id'),
                'client_secret' => config('services.azure.client_secret'),
                'grant_type' => 'client_credentials',
                'scope' => 'https://graph.microsoft.com/.default',
            ]);

        if (! $response->successful()) {
            Log::warning('Entra app token request failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return null;
        }

        $token = $response->json('access_token');
        if (! is_string($token) || $token === '') {
            return null;
        }

        $expiresIn = (int) $response->json('expires_in', 3600);
        Cache::put($cacheKey, $token, max(60, $expiresIn - 120));

        return $token;
    }

    /**
     * Resolve the requestor's direct manager mailbox from Entra ID.
     */
    public function getManagerEmailForUser(string $userEmail): ?string
    {
        $userEmail = trim($userEmail);
        if ($userEmail === '') {
            return null;
        }

        $token = $this->getAppAccessToken();
        if ($token === null) {
            return null;
        }

        $encoded = rawurlencode($userEmail);
        $response = Http::timeout(15)
            ->acceptJson()
            ->withToken($token)
            ->get("https://graph.microsoft.com/v1.0/users/{$encoded}/manager");

        if (! $response->successful()) {
            Log::info('Entra manager lookup failed', [
                'email' => $userEmail,
                'status' => $response->status(),
            ]);

            return null;
        }

        $mail = $response->json('mail');
        if (is_string($mail) && $mail !== '') {
            return strtolower($mail);
        }

        $upn = $response->json('userPrincipalName');
        if (is_string($upn) && $upn !== '') {
            return strtolower($upn);
        }

        return null;
    }

    private function bootstrapAzureConfig(): bool
    {
        $tenantId = tenant('id');
        if ($tenantId !== null) {
            return $this->tenantMicrosoft->applyAzureRuntimeConfig((string) $tenantId);
        }

        return (string) config('services.azure.client_id', '') !== ''
            && (string) config('services.azure.client_secret', '') !== '';
    }
}
