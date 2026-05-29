<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Services;

use App\Models\Tenant;
use App\Modules\Platform\Models\RolloutPlaybookVersion;
use App\Modules\Platform\Models\TenantPlaybookBinding;
use App\Modules\Platform\Services\RolloutPlaybookCatalogService;
use App\Modules\Platform\Services\RolloutPolicyBundleService;
use App\Modules\Rollout\Services\TenantPlaybookSyncService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class TenantEnvironmentProvisioningService
{
    public function __construct(
        private readonly TenantDomainSlugService $domainSlugs,
        private readonly RolloutPlaybookCatalogService $playbookCatalog,
        private readonly RolloutPolicyBundleService $policyBundles,
        private readonly TenantPlaybookSyncService $playbookSync,
        private readonly TenantRolloutBootstrapService $rolloutBootstrap,
        private readonly TenantAdminBootstrapService $adminBootstrap,
    ) {}

    /**
     * @param  array{
     *   environment: string,
     *   domain?: string|null,
     *   migrate?: bool,
     *   seed?: bool
     * }  $input
     * @return array<string, mixed>
     */
    public function createFromTenant(Tenant $sourceTenant, array $input): array
    {
        $environment = strtolower(trim((string) ($input['environment'] ?? '')));

        if (! in_array($environment, ['local', 'test', 'staging', 'production'], true)) {
            throw ValidationException::withMessages([
                'environment' => [__('Environment must be local, test, staging, or production.')],
            ]);
        }

        if ($sourceTenant->environment === $environment) {
            throw ValidationException::withMessages([
                'environment' => [__('Source tenant is already in this environment.')],
            ]);
        }

        $orgRoot = $this->resolveOrgRoot($sourceTenant);
        $slug = $this->resolveSlug($orgRoot, $sourceTenant);
        $brandDomain = trim((string) ($orgRoot->brand_domain ?? $sourceTenant->brand_domain ?? 'toweros.app'));

        if ($slug !== '' && Tenant::query()->where('slug', $slug)->where('environment', $environment)->exists()) {
            throw ValidationException::withMessages([
                'environment' => [__('An environment tenant already exists for this slug.')],
            ]);
        }

        $recommendation = $this->domainSlugs->recommend($orgRoot, $slug, $brandDomain, $environment);
        $domain = $this->normalizeDomain((string) ($input['domain'] ?? ''));
        if ($domain === '') {
            $domain = (string) ($recommendation['endpoints'][0]['hostname'] ?? '');
        }

        if ($domain === '') {
            throw ValidationException::withMessages([
                'domain' => [__('Could not derive a primary domain for this environment.')],
            ]);
        }

        if (Tenant::query()->whereHas('domains', fn ($query) => $query->where('domain', $domain))->exists()) {
            throw ValidationException::withMessages([
                'domain' => [__('This domain is already assigned to another tenant.')],
            ]);
        }

        /** @var Tenant $tenant */
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'slug' => $slug !== '' ? $slug : null,
            'brand_domain' => $brandDomain !== '' ? $brandDomain : null,
            'environment' => $environment,
            'tco_sequence_prefix' => $orgRoot->tco_sequence_prefix ?? $sourceTenant->tco_sequence_prefix ?? 'A',
            'parent_tenant_id' => $orgRoot->id,
            'mfa_required' => $orgRoot->mfa_required ?? true,
            'plan_tier' => $orgRoot->plan_tier ?? 'starter',
            'subscription_status' => $orgRoot->subscription_status ?? 'active',
            'seat_limit' => $orgRoot->seat_limit ?? 25,
        ]);

        $tenant->createDomain($domain);
        $this->domainSlugs->persistEndpoints($tenant, $recommendation);

        $binding = $this->copyPlaybookBinding($sourceTenant, $tenant);

        $initialAdmin = null;
        $rolloutBootstrap = [
            'public_holidays_seeded' => 0,
            'holiday_years' => [],
        ];

        if (! empty($input['migrate'])) {
            if (! empty($input['seed'])) {
                Artisan::call('tenants:migrate', [
                    '--tenants' => [$tenant->id],
                    '--force' => true,
                    '--seed' => true,
                    '--seeder' => 'Database\\Seeders\\TenantDatabaseSeeder',
                ]);
            }

            if ($binding !== null) {
                $this->playbookSync->syncBindingToTenantDatabase($tenant, $binding);
            }

            $rolloutBootstrap = $this->rolloutBootstrap->provision($tenant);
        }

        // Stancl creates the tenant DB on createDomain(); always ensure admin@{domain} exists.
        $initialAdmin = $this->adminBootstrap->bootstrap($tenant, $domain);

        return [
            'tenant' => $tenant->fresh(['domains']),
            'source_tenant_id' => $sourceTenant->id,
            'org_root_tenant_id' => $orgRoot->id,
            'domain_endpoints' => $recommendation,
            'playbook_version' => $binding?->playbookVersion?->version,
            'assigned_policy_code' => $binding?->rolloutPolicyBundle?->code,
            'initial_admin' => $initialAdmin,
            'public_holidays_seeded' => $rolloutBootstrap['public_holidays_seeded'],
            'holiday_years' => $rolloutBootstrap['holiday_years'],
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

    private function resolveSlug(Tenant $orgRoot, Tenant $sourceTenant): string
    {
        $slug = $this->domainSlugs->normalizeSlug((string) ($orgRoot->slug ?? ''));

        if ($slug !== '') {
            return $slug;
        }

        $slug = $this->domainSlugs->normalizeSlug((string) ($sourceTenant->slug ?? ''));

        if ($slug !== '') {
            return $slug;
        }

        $domain = $sourceTenant->domains()->first()?->domain ?? $orgRoot->domains()->first()?->domain ?? '';

        return $this->domainSlugs->normalizeSlug(explode('.', (string) $domain)[0] ?? 'tenant');
    }

    private function copyPlaybookBinding(Tenant $sourceTenant, Tenant $targetTenant): ?TenantPlaybookBinding
    {
        /** @var TenantPlaybookBinding|null $sourceBinding */
        $sourceBinding = TenantPlaybookBinding::query()
            ->where('tenant_id', $sourceTenant->id)
            ->with(['playbookVersion', 'rolloutPolicyBundle'])
            ->first();

        if ($sourceBinding === null) {
            $version = $this->playbookCatalog->latestPublished();
            if ($version === null) {
                return null;
            }

            $defaultBundle = $this->policyBundles->resolveDefaultForProvisioning($version);
            if ($defaultBundle !== null) {
                return $this->policyBundles->assignToTenant($targetTenant, $defaultBundle);
            }

            return $this->playbookCatalog->assignToTenant($targetTenant, $version);
        }

        if ($sourceBinding->rollout_policy_bundle_id !== null && $sourceBinding->rolloutPolicyBundle !== null) {
            return $this->policyBundles->assignToTenant(
                $targetTenant,
                $sourceBinding->rolloutPolicyBundle,
                $sourceBinding->upgrade_policy,
            );
        }

        /** @var RolloutPlaybookVersion|null $version */
        $version = $sourceBinding->playbookVersion;
        if ($version === null) {
            return null;
        }

        $defaultBundle = $this->policyBundles->resolveDefaultForProvisioning($version);
        if ($defaultBundle !== null) {
            return $this->policyBundles->assignToTenant(
                $targetTenant,
                $defaultBundle,
                $sourceBinding->upgrade_policy,
            );
        }

        return $this->playbookCatalog->assignToTenant(
            $targetTenant,
            $version,
            $sourceBinding->upgrade_policy,
        );
    }

    private function normalizeDomain(string $domain): string
    {
        $domain = trim($domain);
        $domain = preg_replace('#^https?://#i', '', $domain) ?? $domain;
        $domain = trim($domain, "/ \t\n\r\0\x0B");
        $domain = strtolower($domain);

        if ($domain === '') {
            return '';
        }

        if (! preg_match('/^[a-z0-9.-]+$/', $domain)) {
            throw new InvalidArgumentException('Domain contains invalid characters.');
        }

        return $domain;
    }
}
