<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Models\Tenant;
use App\Modules\Tenancy\Support\TenantAppUrlResolver;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\MissingAppKeyException;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use stdClass;

final class TenantSsoConfigService
{
    public function __construct(
        private readonly TenantAppUrlResolver $appUrls,
    ) {}

    /**
     * SSO app registrations live on the central database (not per-tenant DB).
     */
    private function ssoConfigs(): Builder
    {
        return $this->centralConnection()->table('tenant_sso_configs');
    }

    private function centralConnection(): Connection
    {
        $name = (string) config('tenancy.database.central_connection', 'central');

        return DB::connection($name);
    }

    public function findForTenant(string $tenantId, string $provider = 'azure'): ?stdClass
    {
        return $this->ssoConfigs()
            ->where('tenant_id', $tenantId)
            ->where('provider', $provider)
            ->first();
    }

    public function findEnabledForTenant(string $tenantId, string $provider = 'azure'): ?stdClass
    {
        $config = $this->findForTenant($tenantId, $provider);

        if ($config === null || ! (bool) $config->enabled) {
            return null;
        }

        return $config;
    }

    /**
     * @return array{
     *     enabled: bool,
     *     provider: string,
     *     redirect_path: string,
     *     label: string
     * }|null
     */
    public function publicStatus(string $tenantId): ?array
    {
        $config = $this->findForTenant($tenantId, 'azure');
        if ($config === null || ! (bool) $config->enabled) {
            return null;
        }

        return [
            'enabled' => true,
            'provider' => 'azure',
            'redirect_path' => '/auth/sso/azure/redirect',
            'label' => 'Sign in with Microsoft',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function toAdminPayload(?stdClass $config): ?array
    {
        if ($config === null) {
            return null;
        }

        $rules = $this->normalizeGroupMappingRules(
            json_decode((string) $config->group_mapping_rules, true),
        );

        $domains = app(TenantAuthPolicyService::class)->normalizeDomainList($config->allowed_email_domains ?? null);

        return [
            'id' => $config->id,
            'provider' => $config->provider,
            'issuer' => $config->issuer,
            'client_id' => $config->client_id,
            'has_client_secret' => trim((string) $config->client_secret_encrypted) !== '',
            'tenant_identifier' => $config->tenant_identifier,
            'group_mapping_rules' => $rules,
            'allowed_email_domains' => $domains,
            'auto_provision_users' => (bool) $config->auto_provision_users,
            'disable_password_login_when_enabled' => (bool) ($config->disable_password_login_when_enabled ?? true),
            'enabled' => (bool) $config->enabled,
            'redirect_uri' => $this->redirectUri(),
            'login_redirect_path' => '/auth/sso/azure/redirect',
            'powers_entra_graph' => $this->hasMicrosoftCredentials($config),
        ];
    }

    public function redirectUri(): string
    {
        return route('api.tenant.v1.auth.sso.azure.callback', [], true);
    }

    public function hasMicrosoftCredentials(?stdClass $config): bool
    {
        if ($config === null) {
            return false;
        }

        if (trim((string) $config->client_id) === '') {
            return false;
        }

        return trim((string) $config->client_secret_encrypted) !== '';
    }

    /**
     * Load this tenant's Microsoft app registration into runtime config (SSO, Graph, manager steps).
     */
    public function applyAzureRuntimeConfig(string $tenantId): bool
    {
        $config = $this->findForTenant($tenantId, 'azure');
        if (! $this->hasMicrosoftCredentials($config)) {
            return false;
        }

        $this->applyAzureSocialiteConfig($config);

        return true;
    }

    public function applyAzureSocialiteConfig(stdClass $config): void
    {
        config([
            'services.azure.client_id' => $config->client_id,
            'services.azure.client_secret' => $this->decryptClientSecret((string) $config->client_secret_encrypted),
            'services.azure.tenant' => $config->tenant_identifier ?: 'common',
            'services.azure.redirect' => $this->redirectUri(),
        ]);
    }

    private function decryptClientSecret(string $encrypted): string
    {
        if (trim($encrypted) === '') {
            throw ValidationException::withMessages([
                'client_secret' => [__('Microsoft client secret is not configured. Enter it under Sign-in & security and save.')],
            ]);
        }

        if (trim((string) config('app.key')) === '') {
            throw ValidationException::withMessages([
                'client_secret' => [__(
                    'Application encryption key (APP_KEY) is not configured. Ensure backend/.env contains APP_KEY=base64:… then run: docker compose restart api'
                )],
            ]);
        }

        try {
            return decrypt($encrypted);
        } catch (MissingAppKeyException) {
            throw ValidationException::withMessages([
                'client_secret' => [__(
                    'Application encryption key (APP_KEY) is not configured. Ensure backend/.env contains APP_KEY=base64:… then run: docker compose restart api'
                )],
            ]);
        } catch (DecryptException) {
            throw ValidationException::withMessages([
                'client_secret' => [__(
                    'The stored client secret cannot be decrypted (APP_KEY may have changed). Paste the Microsoft client secret again on Sign-in & security and save.'
                )],
            ]);
        }
    }

    /**
     * @param  array{
     *     issuer?: string|null,
     *     client_id: string,
     *     client_secret?: string|null,
     *     tenant_identifier?: string|null,
     *     group_mapping_rules?: array<string, mixed>|null,
     *     allowed_email_domains?: list<string>|null,
     *     auto_provision_users?: bool,
     *     disable_password_login_when_enabled?: bool,
     *     enabled?: bool
     * }  $data
     */
    public function upsert(string $tenantId, array $data, string $provider = 'azure'): void
    {
        $existing = $this->findForTenant($tenantId, $provider);
        $enabled = (bool) ($data['enabled'] ?? false);

        if ($existing === null && $enabled && empty($data['client_secret'])) {
            throw ValidationException::withMessages([
                'client_secret' => [__('Client secret is required when enabling Microsoft sign-in.')],
            ]);
        }

        $domainPolicy = app(TenantAuthPolicyService::class);
        $allowedDomains = array_key_exists('allowed_email_domains', $data)
            ? $domainPolicy->validateAllowedEmailDomainsInput(
                is_array($data['allowed_email_domains']) ? $data['allowed_email_domains'] : null,
            )
            : ($existing !== null
                ? $domainPolicy->normalizeDomainList($existing->allowed_email_domains ?? null)
                : []);

        $payload = [
            'tenant_id' => $tenantId,
            'provider' => $provider,
            'issuer' => $data['issuer'] ?? null,
            'client_id' => $data['client_id'],
            'tenant_identifier' => $data['tenant_identifier'] ?? 'common',
            'group_mapping_rules' => $this->encodeGroupMappingRules($data['group_mapping_rules'] ?? []),
            'allowed_email_domains' => json_encode($allowedDomains),
            'auto_provision_users' => (bool) ($data['auto_provision_users'] ?? config('toweros.tenant_auth.default_sso_auto_provision', false)),
            'disable_password_login_when_enabled' => (bool) ($data['disable_password_login_when_enabled'] ?? config('toweros.tenant_auth.default_disable_password_when_sso', true)),
            'enabled' => $enabled,
            'updated_at' => now(),
        ];

        if (! empty($data['client_secret'])) {
            $payload['client_secret_encrypted'] = encrypt((string) $data['client_secret']);
        }

        if ($existing) {
            if (empty($data['client_secret'])) {
                unset($payload['client_secret_encrypted']);
            }

            $this->ssoConfigs()->where('id', $existing->id)->update($payload);

            return;
        }

        $this->ssoConfigs()->insert($payload + [
            'id' => (string) Str::uuid(),
            'created_at' => now(),
        ]);
    }

    public function resolveSsoCallbackFrontendUrl(): string
    {
        return $this->appUrls->urlForCurrentTenant('/login/sso-callback');
    }

    /**
     * @return array<string, list<string>>
     */
    private function normalizeGroupMappingRules(mixed $rules): array
    {
        if (! is_array($rules) || $rules === [] || array_is_list($rules)) {
            return [];
        }

        return $rules;
    }

    private function encodeGroupMappingRules(mixed $rules): string
    {
        $normalized = $this->normalizeGroupMappingRules($rules);

        return json_encode(
            $normalized,
            $normalized === [] ? JSON_FORCE_OBJECT : 0,
            JSON_THROW_ON_ERROR,
        );
    }
}
