<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Validation\ValidationException;

/**
 * Central auth policy for a tenant (SSO config + password rules).
 */
final class TenantAuthPolicyService
{
    public function __construct(
        private readonly TenantSsoConfigService $ssoConfig,
    ) {}

    /**
     * @return list<string> Lowercase domain labels without leading @ (e.g. atc.com).
     */
    public function allowedEmailDomains(string $tenantId): array
    {
        $config = $this->ssoConfig->findForTenant($tenantId, 'azure');

        return $this->normalizeDomainList($config?->allowed_email_domains ?? null);
    }

    public function isEmailDomainAllowed(string $tenantId, string $email): bool
    {
        $allowed = $this->allowedEmailDomains($tenantId);
        if ($allowed === []) {
            return true;
        }

        $domain = self::emailDomain($email);
        if ($domain === '') {
            return false;
        }

        return in_array($domain, $allowed, true);
    }

    public function assertEmailDomainAllowed(string $tenantId, string $email): void
    {
        if ($this->isEmailDomainAllowed($tenantId, $email)) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => [__('Sign-in is not allowed for this email domain. Contact your administrator.')],
        ]);
    }

    public function shouldAutoProvisionSso(string $tenantId): bool
    {
        $config = $this->ssoConfig->findEnabledForTenant($tenantId, 'azure');

        return $config !== null && (bool) $config->auto_provision_users;
    }

    public function isPasswordLoginRestricted(string $tenantId): bool
    {
        $config = $this->ssoConfig->findEnabledForTenant($tenantId, 'azure');
        if ($config === null) {
            return false;
        }

        return (bool) ($config->disable_password_login_when_enabled ?? true);
    }

    public function canUserPasswordLogin(TenantUser $user, string $tenantId): bool
    {
        if (! $this->isPasswordLoginRestricted($tenantId)) {
            return true;
        }

        return (bool) $user->password_login_exempt;
    }

    public function assertPasswordLoginAllowed(TenantUser $user, string $tenantId): void
    {
        if ($this->canUserPasswordLogin($user, $tenantId)) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => [__('Password sign-in is disabled for this organization. Use Microsoft sign-in or contact your administrator.')],
        ]);
    }

    /**
     * @return array{
     *     available: bool,
     *     restricted_when_sso_enabled: bool
     * }
     */
    public function publicPasswordLoginStatus(string $tenantId): array
    {
        $restricted = $this->isPasswordLoginRestricted($tenantId);

        return [
            'available' => ! $restricted,
            'restricted_when_sso_enabled' => $restricted,
        ];
    }

    /**
     * @param  mixed  $raw
     * @return list<string>
     */
    public function normalizeDomainList(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $this->normalizeDomainList($decoded) : [];
        }

        if (! is_array($raw)) {
            return [];
        }

        $domains = [];
        foreach ($raw as $entry) {
            if (! is_string($entry)) {
                continue;
            }
            $normalized = self::normalizeDomain($entry);
            if ($normalized !== '') {
                $domains[] = $normalized;
            }
        }

        return array_values(array_unique($domains));
    }

    public static function normalizeDomain(string $value): string
    {
        $value = strtolower(trim($value));
        $value = ltrim($value, '@');

        if ($value === '' || ! preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/i', $value)) {
            return '';
        }

        return $value;
    }

    public static function emailDomain(string $email): string
    {
        $at = strrpos($email, '@');
        if ($at === false) {
            return '';
        }

        return strtolower(substr($email, $at + 1));
    }

    /**
     * @param  list<string>|null  $domains
     * @return list<string>
     */
    public function validateAllowedEmailDomainsInput(?array $domains): array
    {
        if ($domains === null || $domains === []) {
            return [];
        }

        $normalized = $this->normalizeDomainList($domains);
        if (count($normalized) !== count(array_filter($domains, static fn ($d) => is_string($d) && trim($d) !== ''))) {
            throw ValidationException::withMessages([
                'allowed_email_domains' => [__('Each allowed email domain must be a valid domain (e.g. example.com).')],
            ]);
        }

        return $normalized;
    }
}
