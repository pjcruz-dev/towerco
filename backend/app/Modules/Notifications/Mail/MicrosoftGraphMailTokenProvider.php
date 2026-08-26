<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Mail;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportException;

/**
 * Client-credentials tokens for Graph Mail.Send.
 *
 * Uses central env credentials only (never tenant SSO overrides) so queued
 * notifications keep a stable sender app under active tenancy.
 */
final class MicrosoftGraphMailTokenProvider
{
    public function getAccessToken(bool $forceRefresh = false): string
    {
        $clientId = $this->clientId();
        $clientSecret = $this->clientSecret();
        $tenant = $this->tenantId();

        if ($clientId === '' || $clientSecret === '') {
            throw new TransportException(
                'Microsoft Graph mail is not configured. Set MAIL_GRAPH_CLIENT_ID / MAIL_GRAPH_CLIENT_SECRET (or AZURE_CLIENT_ID / AZURE_CLIENT_SECRET).',
            );
        }

        if ($tenant === '' || in_array($tenant, ['common', 'organizations', 'consumers'], true)) {
            throw new TransportException(
                'Microsoft Graph mail requires MAIL_GRAPH_TENANT_ID (or AZURE_TENANT_ID) set to your Entra directory GUID.',
            );
        }

        $cacheKey = 'microsoft_graph_mail_token:'.$tenant.':'.$clientId;

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        /** @var string|null $cached */
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = Http::timeout(20)
                ->asForm()
                ->post("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token", [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'grant_type' => 'client_credentials',
                    'scope' => 'https://graph.microsoft.com/.default',
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('Microsoft Graph mail token request timed out', [
                'message' => $exception->getMessage(),
            ]);

            throw new TransportException(
                'Microsoft Graph mail token request failed: '.$exception->getMessage(),
                0,
                $exception,
            );
        }

        if (! $response->successful()) {
            $error = (string) ($response->json('error_description') ?? $response->json('error') ?? $response->body());
            Log::warning('Microsoft Graph mail token request failed', [
                'status' => $response->status(),
                'error' => $response->json('error'),
            ]);

            throw new TransportException(
                'Microsoft Graph mail token request failed: '.$error,
                $response->status(),
            );
        }

        $token = $response->json('access_token');
        if (! is_string($token) || $token === '') {
            throw new TransportException('Microsoft Graph mail token response did not include an access_token.');
        }

        $expiresIn = (int) $response->json('expires_in', 3600);
        $ttl = max(60, $expiresIn - 120);
        Cache::put($cacheKey, $token, $ttl);

        return $token;
    }

    public function forgetCachedToken(): void
    {
        $clientId = $this->clientId();
        $tenant = $this->tenantId();
        if ($clientId === '' || $tenant === '') {
            return;
        }

        Cache::forget('microsoft_graph_mail_token:'.$tenant.':'.$clientId);
    }

    private function clientId(): string
    {
        return trim((string) config('services.microsoft_graph_mail.client_id', ''));
    }

    private function clientSecret(): string
    {
        return (string) config('services.microsoft_graph_mail.client_secret', '');
    }

    private function tenantId(): string
    {
        return strtolower(trim((string) config('services.microsoft_graph_mail.tenant', '')));
    }
}
