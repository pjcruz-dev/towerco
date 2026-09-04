<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Support\EntraDirectoryPerson;
use App\Modules\Identity\Support\EntraManagerLookupResult;
use App\Modules\Identity\Support\EntraUserManagerMatch;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Microsoft Graph application (client credentials) — uses per-tenant Microsoft settings when tenancy is active.
 */
class EntraGraphAppService
{
    private const SELECT = 'id,mail,userPrincipalName,displayName,jobTitle,department,assignedLicenses';

    private const SELECT_CORE = 'id,mail,userPrincipalName,displayName,jobTitle,department';

    private ?string $tokenFailureMessage = null;

    public function __construct(
        private readonly TenantSsoConfigService $tenantMicrosoft,
    ) {}

    public static function appTokenCacheKey(string $tenantId, string $directory): string
    {
        return 'entra_app_token:'.$tenantId.':'.strtolower(trim($directory));
    }

    public function isConfigured(): bool
    {
        $this->bootstrapAzureConfig();

        $clientId = (string) config('services.azure.client_id', '');
        $clientSecret = (string) config('services.azure.client_secret', '');

        return $clientId !== '' && $clientSecret !== '';
    }

    public function directoryIdentifier(): string
    {
        $this->bootstrapAzureConfig();

        return strtolower(trim((string) config('services.azure.tenant', 'common')));
    }

    public function forgetAppAccessToken(): void
    {
        $this->bootstrapAzureConfig();
        $key = $this->currentTokenCacheKey();
        if ($key !== null) {
            Cache::forget($key);
        }
    }

    public function getAppAccessToken(bool $forceRefresh = false): ?string
    {
        $this->tokenFailureMessage = null;

        if (! $this->bootstrapAzureConfig()) {
            $this->tokenFailureMessage = 'Microsoft Entra is not configured for this organization. Add the app client ID and secret under Administration → Sign-in & security.';

            return null;
        }

        $cacheKey = $this->currentTokenCacheKey();
        if ($cacheKey === null) {
            $this->tokenFailureMessage = 'Set Directory ID to your Entra tenant GUID (not “common”). Client-credential Graph calls require the directory ID.';

            return null;
        }

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        /** @var string|null $cached */
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $tenant = $this->directoryIdentifier();
        try {
            $response = $this->microsoftHttp(20)
                ->asForm()
                ->post("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token", [
                    'client_id' => config('services.azure.client_id'),
                    'client_secret' => config('services.azure.client_secret'),
                    'grant_type' => 'client_credentials',
                    'scope' => 'https://graph.microsoft.com/.default',
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('Entra app token request timed out', [
                'message' => $exception->getMessage(),
            ]);
            $this->tokenFailureMessage = $this->describeConnectionFailure($exception);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Entra app token request failed', [
                'status' => $response->status(),
                'error' => $response->json('error'),
            ]);
            $this->tokenFailureMessage = $this->describeTokenFailure($response);

            return null;
        }

        $token = $response->json('access_token');
        if (! is_string($token) || $token === '') {
            $this->tokenFailureMessage = 'Microsoft returned an app token response without an access token. Check the client secret and Directory ID.';

            return null;
        }

        $expiresIn = (int) $response->json('expires_in', 3600);
        Cache::put($cacheKey, $token, max(60, $expiresIn - 120));

        return $token;
    }

    public function tokenFailureMessage(): ?string
    {
        return $this->tokenFailureMessage;
    }

    /**
     * Resolve the requestor's direct manager mailbox from Entra ID.
     */
    public function getManagerEmailForUser(string $userEmail): ?string
    {
        return $this->lookupManagerForEmail($userEmail)->manager?->email;
    }

    public function lookupManagerForEmail(string $userEmail, bool $retried = false): EntraManagerLookupResult
    {
        $userEmail = strtolower(trim($userEmail));
        if ($userEmail === '' || ! filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            return EntraManagerLookupResult::fail(
                EntraManagerLookupResult::CODE_USER_NOT_FOUND,
                'Enter a valid requestor email address.',
            );
        }

        if (! $this->isConfigured()) {
            return EntraManagerLookupResult::fail(
                EntraManagerLookupResult::CODE_NOT_CONFIGURED,
                'Microsoft Entra is not configured for this organization. Add the app client ID and secret under Administration → Sign-in & security.',
            );
        }

        $directory = $this->directoryIdentifier();
        if ($directory === '' || $directory === 'common' || $directory === 'organizations' || $directory === 'consumers') {
            return EntraManagerLookupResult::fail(
                EntraManagerLookupResult::CODE_DIRECTORY_COMMON,
                'Set Directory ID to your Entra tenant GUID (not “common”). Client-credential Graph calls require the directory ID.',
            );
        }

        $token = $this->getAppAccessToken();
        if ($token === null) {
            return EntraManagerLookupResult::fail(
                EntraManagerLookupResult::CODE_TOKEN_FAILED,
                'Could not get an app token from Microsoft. Check the client secret and Directory ID.',
            );
        }

        $match = $this->findUserWithManager($token, $userEmail, $retried);
        if ($match instanceof EntraManagerLookupResult) {
            return $match;
        }

        if ($match->manager === null) {
            return EntraManagerLookupResult::fail(
                EntraManagerLookupResult::CODE_NO_MANAGER,
                'Microsoft Entra has no Manager assigned for this user. Set Manager on the user in Entra ID (same field Teams Organization uses).',
                404,
                $match->person,
            );
        }

        return new EntraManagerLookupResult(
            ok: true,
            code: EntraManagerLookupResult::CODE_OK,
            message: 'Manager found in Microsoft Entra.',
            manager: $match->manager,
            requestor: $match->person,
            httpStatus: 200,
        );
    }

    /**
     * @return list<EntraDirectoryPerson>
     */
    public function listDirectReports(string $entraUserId): array
    {
        $token = $this->getAppAccessToken();
        if ($token === null || trim($entraUserId) === '') {
            return [];
        }

        $response = $this->graphGetUser($token, '/users/'.rawurlencode($entraUserId).'/directReports', [
            '$top' => '999',
        ]);
        if (! $response->successful()) {
            return [];
        }

        $people = [];
        foreach ($response->json('value') ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $person = EntraDirectoryPerson::fromGraph($row);
            if ($person !== null) {
                $people[] = $person;
            }
        }

        return $people;
    }

    public function findUser(string $token, string $email, bool $retried = false): EntraDirectoryPerson|EntraManagerLookupResult
    {
        $match = $this->findUserWithManager($token, $email, $retried);

        return $match instanceof EntraManagerLookupResult ? $match : $match->person;
    }

    /**
     * skuId => skuPartNumber from this tenant's subscribed SKUs.
     *
     * @return array<string, string>
     */
    public function subscribedSkuMap(string $token): array
    {
        $cacheKey = 'entra_subscribed_skus:'.((string) (tenant('id') ?? 'global')).':'.$this->directoryIdentifier();
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            /** @var array<string, string> $cached */
            return $cached;
        }

        $response = $this->graphGet($token, '/subscribedSkus', [
            '$select' => 'skuId,skuPartNumber',
        ]);
        if (! $response->successful()) {
            return [];
        }

        $map = [];
        foreach ($response->json('value') ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $skuId = strtolower(trim((string) ($row['skuId'] ?? '')));
            $part = trim((string) ($row['skuPartNumber'] ?? ''));
            if ($skuId !== '' && $part !== '') {
                $map[$skuId] = $part;
            }
        }

        Cache::put($cacheKey, $map, now()->addHours(6));

        return $map;
    }

    /**
     * One Graph round-trip: user profile plus manager (via $expand).
     */
    public function findUserWithManager(string $token, string $email, bool $retried = false): EntraUserManagerMatch|EntraManagerLookupResult
    {
        $byKey = $this->graphGetUser($token, '/users/'.rawurlencode($email));
        if ($byKey->successful()) {
            $match = $this->matchFromGraphUser($token, $byKey->json() ?? []);
            if ($match instanceof EntraManagerLookupResult) {
                return $match;
            }
            if ($match !== null) {
                return $match;
            }
        }
        if ($byKey->status() === 403) {
            if (! $retried) {
                $this->forgetAppAccessToken();
                $fresh = $this->getAppAccessToken();
                if (is_string($fresh) && $fresh !== '') {
                    return $this->findUserWithManager($fresh, $email, true);
                }
            }

            return $this->forbiddenResult($token, $byKey);
        }

        $escaped = str_replace("'", "''", $email);
        $byMail = $this->graphGetUser($token, '/users', [
            '$filter' => "mail eq '{$escaped}' or userPrincipalName eq '{$escaped}'",
        ]);
        if ($byMail->status() === 403) {
            if (! $retried) {
                $this->forgetAppAccessToken();
                $fresh = $this->getAppAccessToken();
                if (is_string($fresh) && $fresh !== '') {
                    return $this->findUserWithManager($fresh, $email, true);
                }
            }

            return $this->forbiddenResult($token, $byMail);
        }
        if ($byMail->successful()) {
            $first = $byMail->json('value.0');
            if (is_array($first)) {
                $match = $this->matchFromGraphUser($token, $first);
                if ($match instanceof EntraManagerLookupResult) {
                    return $match;
                }
                if ($match !== null) {
                    return $match;
                }
            }
        }

        return EntraManagerLookupResult::fail(
            EntraManagerLookupResult::CODE_USER_NOT_FOUND,
            'No Microsoft Entra user matched this email. Confirm the mailbox exists in this directory.',
            $byKey->successful() ? $byMail->status() : $byKey->status(),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function matchFromGraphUser(string $token, array $payload): EntraUserManagerMatch|EntraManagerLookupResult|null
    {
        $person = EntraDirectoryPerson::fromGraph($payload);
        if ($person === null) {
            return null;
        }

        $manager = $this->fetchManagerByEntraId($token, $person->entraId);
        if ($manager instanceof EntraManagerLookupResult) {
            return $manager;
        }

        return new EntraUserManagerMatch($person, $manager);
    }

    public function fetchManagerPerson(string $token, string $entraId): ?EntraDirectoryPerson
    {
        if (trim($entraId) === '') {
            return null;
        }

        $manager = $this->fetchManagerByEntraId($token, $entraId);

        return $manager instanceof EntraDirectoryPerson ? $manager : null;
    }

    private function fetchManagerByEntraId(string $token, string $entraId): EntraDirectoryPerson|EntraManagerLookupResult|null
    {
        $response = $this->graphGetUser($token, '/users/'.rawurlencode($entraId).'/manager');
        if ($response->status() === 404) {
            return null;
        }
        if ($response->status() === 403) {
            return $this->forbiddenResult($token, $response);
        }
        if (! $response->successful()) {
            return EntraManagerLookupResult::fail(
                EntraManagerLookupResult::CODE_GRAPH_ERROR,
                'Microsoft Graph manager lookup failed (HTTP '.$response->status().').',
                $response->status(),
            );
        }

        return EntraDirectoryPerson::fromGraph($response->json() ?? []);
    }

    private function forbiddenResult(string $token, Response $response): EntraManagerLookupResult
    {
        $graphCode = $response->json('error.code');
        $hasRole = in_array('User.Read.All', $this->tokenAppRoles($token), true);

        Log::info('Entra Graph app call forbidden', [
            'graph_code' => is_string($graphCode) ? $graphCode : null,
            'has_user_read_all' => $hasRole,
            'directory' => $this->directoryIdentifier(),
        ]);

        $message = $hasRole
            ? 'Graph returned 403 even though the app token includes User.Read.All. Confirm Directory ID is the Entra tenant GUID for Alliance Tower, then try again in a minute.'
            : 'Graph refused the app token. Add Application permission User.Read.All (not only Delegated) and grant admin consent. If you just granted it, sync again — an older app token may still be cached.';

        return EntraManagerLookupResult::fail(
            EntraManagerLookupResult::CODE_FORBIDDEN,
            $message,
            403,
        );
    }

    /**
     * @return list<string>
     */
    private function tokenAppRoles(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) < 2) {
            return [];
        }

        $padded = strtr($parts[1], '-_', '+/');
        $remainder = strlen($padded) % 4;
        if ($remainder !== 0) {
            $padded .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode($padded, true);
        if (! is_string($decoded) || $decoded === '') {
            return [];
        }

        $payload = json_decode($decoded, true);
        $roles = is_array($payload) ? ($payload['roles'] ?? []) : [];
        if (! is_array($roles)) {
            return [];
        }

        return array_values(array_filter($roles, static fn (mixed $role): bool => is_string($role) && $role !== ''));
    }

    private function currentTokenCacheKey(): ?string
    {
        $tenant = $this->directoryIdentifier();
        if ($tenant === '' || $tenant === 'common' || $tenant === 'organizations' || $tenant === 'consumers') {
            return null;
        }

        return self::appTokenCacheKey((string) (tenant('id') ?? 'global'), $tenant);
    }

    private function describeTokenFailure(Response $response): string
    {
        $error = strtolower(trim((string) ($response->json('error') ?? '')));
        $description = trim((string) ($response->json('error_description') ?? ''));
        $firstLine = trim(explode("\r\n", $description)[0]);

        if (str_contains($description, 'AADSTS7000222')) {
            return 'The Microsoft client secret has expired. Create a new secret in Entra → App registrations → Certificates & secrets, then paste it under Administration → Sign-in & security.';
        }
        if (str_contains($description, 'AADSTS7000215') || $error === 'invalid_client') {
            return 'Microsoft rejected the client secret. Paste the current secret under Administration → Sign-in & security and save.';
        }
        if (str_contains($description, 'AADSTS700016') || str_contains($description, 'AADSTS90002')) {
            return 'Microsoft did not find this app in that Directory ID. Confirm Application (client) ID and Directory (tenant) ID under Administration → Sign-in & security.';
        }
        if ($firstLine !== '') {
            return 'Microsoft refused the app token. '.$firstLine;
        }

        return 'Could not get an app token from Microsoft. Check the client secret and Directory ID.';
    }

    private function describeConnectionFailure(ConnectionException $exception): string
    {
        $raw = $exception->getMessage();
        if (str_contains($raw, 'Could not resolve') || str_contains($raw, 'cURL error 6')) {
            return 'The API could not resolve login.microsoftonline.com. Recreate the API container so it uses public DNS (8.8.8.8).';
        }
        if (str_contains($raw, 'timed out') || str_contains($raw, 'cURL error 28')) {
            return 'The API timed out reaching login.microsoftonline.com (Docker DNS or IPv6 is a common cause). Recreate the API container, then sync again.';
        }

        $short = trim(explode("\n", $raw)[0]);
        if (strlen($short) > 180) {
            $short = mb_substr($short, 0, 180);
        }

        return 'Could not reach login.microsoftonline.com from the API. '.$short;
    }

    /**
     * Force IPv4 so Docker Desktop on Windows does not hang on IPv6 to Microsoft.
     */
    private function microsoftHttp(int $timeout): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout($timeout)
            ->connectTimeout(8)
            ->withOptions([
                'curl' => [
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                ],
            ]);
    }

    /**
     * @param  array<string, string>  $query
     */
    private function graphGetUser(string $token, string $path, array $query = []): Response
    {
        $response = $this->graphGet($token, $path, array_merge($query, ['$select' => self::SELECT]));
        if ($response->status() !== 400 && ! $this->selectRejectedLicenses($response)) {
            return $response;
        }

        return $this->graphGet($token, $path, array_merge($query, ['$select' => self::SELECT_CORE]));
    }

    private function selectRejectedLicenses(Response $response): bool
    {
        if ($response->status() !== 403) {
            return false;
        }

        $body = strtolower($response->body());

        return str_contains($body, 'assignedlicenses') || str_contains($body, 'license');
    }

    /**
     * Download the user's profile photo bytes from Microsoft Graph (best-effort).
     *
     * @return array{bytes: string, content_type: string}|null
     */
    public function fetchUserPhoto(string $token, string $entraUserId): ?array
    {
        $entraUserId = trim($entraUserId);
        if ($entraUserId === '' || $token === '') {
            return null;
        }

        try {
            $response = $this->microsoftHttp(12)
                ->withToken($token)
                ->withHeaders(['Accept' => 'image/*'])
                ->get('https://graph.microsoft.com/v1.0/users/'.rawurlencode($entraUserId).'/photo/$value');
        } catch (ConnectionException $exception) {
            Log::warning('Entra photo request timed out', [
                'entra_id' => $entraUserId,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            Log::info('Entra photo request failed', [
                'entra_id' => $entraUserId,
                'status' => $response->status(),
            ]);

            return null;
        }

        $bytes = $response->body();
        if ($bytes === '') {
            return null;
        }

        $contentType = (string) ($response->header('Content-Type') ?: 'image/jpeg');
        if (! str_starts_with(strtolower($contentType), 'image/')) {
            $contentType = 'image/jpeg';
        }

        return [
            'bytes' => $bytes,
            'content_type' => explode(';', $contentType)[0] ?: 'image/jpeg',
        ];
    }

    /**
     * @param  array<string, string>  $query
     */
    private function graphGet(string $token, string $path, array $query = []): Response
    {
        try {
            $request = $this->microsoftHttp(12)
                ->acceptJson()
                ->withToken($token);

            $url = 'https://graph.microsoft.com/v1.0'.$path;

            return $query === [] ? $request->get($url) : $request->get($url, $query);
        } catch (ConnectionException $exception) {
            Log::warning('Entra Graph request timed out', [
                'path' => $path,
                'message' => $exception->getMessage(),
            ]);

            return new Response(new Psr7Response(
                504,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'error' => [
                        'code' => 'Timeout',
                        'message' => $exception->getMessage(),
                    ],
                ], JSON_THROW_ON_ERROR),
            ));
        }
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
