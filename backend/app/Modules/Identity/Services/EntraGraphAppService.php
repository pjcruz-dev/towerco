<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Support\EntraDirectoryPerson;
use App\Modules\Identity\Support\EntraManagerLookupResult;
use App\Modules\Identity\Support\EntraUserManagerMatch;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Microsoft Graph application (client credentials) — uses per-tenant Microsoft settings when tenancy is active.
 */
class EntraGraphAppService
{
    private const SELECT = 'id,mail,userPrincipalName,displayName,jobTitle';

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
        if (! $this->bootstrapAzureConfig()) {
            return null;
        }

        $cacheKey = $this->currentTokenCacheKey();
        if ($cacheKey === null) {
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

        $response = $this->graphGet(
            $token,
            '/users/'.rawurlencode($entraUserId).'/directReports?$select='.self::SELECT.'&$top=999',
        );
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
     * One Graph round-trip: user profile plus manager (via $expand).
     */
    public function findUserWithManager(string $token, string $email, bool $retried = false): EntraUserManagerMatch|EntraManagerLookupResult
    {
        $byKey = $this->graphGet($token, '/users/'.rawurlencode($email), [
            '$select' => self::SELECT,
        ]);
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
        $byMail = $this->graphGet($token, '/users', [
            '$filter' => "mail eq '{$escaped}' or userPrincipalName eq '{$escaped}'",
            '$select' => self::SELECT,
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

    private function fetchManagerByEntraId(string $token, string $entraId): EntraDirectoryPerson|EntraManagerLookupResult|null
    {
        $response = $this->graphGet($token, '/users/'.rawurlencode($entraId).'/manager', [
            '$select' => self::SELECT,
        ]);
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

    private function graphGet(string $token, string $path, array $query = []): Response
    {
        $request = Http::timeout(12)
            ->acceptJson()
            ->withToken($token);

        $url = 'https://graph.microsoft.com/v1.0'.$path;

        return $query === [] ? $request->get($url) : $request->get($url, $query);
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
