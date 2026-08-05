<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Services;

use App\Models\Tenant;
use App\Modules\Identity\Services\TenantSsoConfigService;
use App\Modules\Tenancy\Support\FrontendDevUrl;

/**
 * Lists linked environment workspaces for the current org.
 *
 * Phase 1: login deep-links. Phase 2: prefer Microsoft SSO soft-switch when configured.
 */
final class TenantLinkedEnvironmentsService
{
    /** @var list<string> */
    private const ENVIRONMENT_ORDER = ['local', 'test', 'staging', 'production'];

    /** @var array<string, string> */
    private const ENVIRONMENT_LABELS = [
        'local' => 'Local',
        'test' => 'Test',
        'staging' => 'Staging',
        'production' => 'Production',
    ];

    public function __construct(
        private readonly TenantSsoConfigService $ssoConfig,
    ) {}

    /**
     * @return array{
     *   current: array{environment: string, hostname: string|null},
     *   handoff_supported: bool,
     *   environments: list<array{
     *     environment: string,
     *     label: string,
     *     hostname: string,
     *     login_url: string,
     *     sso_enabled: bool,
     *     sso_url: string|null,
     *     switch_url: string,
     *     handoff_available: bool,
     *     is_current: bool
     *   }>
     * }
     */
    public function listForTenant(Tenant $tenant): array
    {
        $handoffSupported = (bool) config('toweros.environment_switch.enabled', true);
        $orgRoot = $this->resolveOrgRoot($tenant);
        $siblings = Tenant::query()
            ->where(function ($query) use ($orgRoot): void {
                $query->where('id', $orgRoot->id)
                    ->orWhere('parent_tenant_id', $orgRoot->id);
            })
            ->with('domains')
            ->get();

        $currentId = (string) $tenant->id;
        $currentEnvironment = strtolower((string) ($tenant->environment ?? 'production'));
        $currentHostname = $this->primaryHostname($tenant);

        $siblingIds = $siblings
            ->map(static fn (Tenant $sibling): string => (string) $sibling->id)
            ->values()
            ->all();
        $ssoEnabledIds = array_fill_keys($this->ssoConfig->enabledTenantIds($siblingIds), true);

        $environments = [];
        foreach ($siblings as $sibling) {
            $hostname = $this->primaryHostname($sibling);
            if ($hostname === null || $hostname === '') {
                continue;
            }

            $environment = strtolower((string) ($sibling->environment ?? 'production'));
            $loginUrl = FrontendDevUrl::tenantLoginUrl($hostname, $environment);
            $ssoEnabled = isset($ssoEnabledIds[(string) $sibling->id]);
            $ssoUrl = $ssoEnabled ? FrontendDevUrl::tenantSsoRedirectUrl($hostname) : null;

            $isCurrent = (string) $sibling->id === $currentId;
            $environments[] = [
                'environment' => $environment,
                'label' => self::ENVIRONMENT_LABELS[$environment] ?? ucfirst($environment),
                'hostname' => $hostname,
                'login_url' => $loginUrl,
                'sso_enabled' => $ssoEnabled,
                'sso_url' => $ssoUrl,
                // Prefer target-host login with auto-SSO so the browser lands on the sibling first.
                'switch_url' => $ssoEnabled
                    ? FrontendDevUrl::tenantSsoSwitchLoginUrl($hostname, $environment)
                    : $loginUrl,
                'handoff_available' => $handoffSupported && ! $isCurrent,
                'is_current' => $isCurrent,
            ];
        }

        usort($environments, static function (array $left, array $right): int {
            $leftRank = array_search($left['environment'], self::ENVIRONMENT_ORDER, true);
            $rightRank = array_search($right['environment'], self::ENVIRONMENT_ORDER, true);
            $leftRank = $leftRank === false ? 99 : $leftRank;
            $rightRank = $rightRank === false ? 99 : $rightRank;

            if ($leftRank !== $rightRank) {
                return $leftRank <=> $rightRank;
            }

            return strcmp($left['hostname'], $right['hostname']);
        });

        return [
            'current' => [
                'environment' => $currentEnvironment,
                'hostname' => $currentHostname,
            ],
            'handoff_supported' => $handoffSupported,
            'environments' => array_values($environments),
        ];
    }

    private function resolveOrgRoot(Tenant $tenant): Tenant
    {
        $current = $tenant;

        while ($current->parent_tenant_id !== null) {
            /** @var Tenant|null $parent */
            $parent = Tenant::query()->find($current->parent_tenant_id);
            if ($parent === null) {
                break;
            }
            $current = $parent;
        }

        return $current;
    }

    private function primaryHostname(Tenant $tenant): ?string
    {
        $domain = $tenant->domains->first()?->domain
            ?? $tenant->domains()->first()?->domain
            ?? null;

        if (! is_string($domain) || trim($domain) === '') {
            return null;
        }

        return strtolower(trim($domain));
    }
}
