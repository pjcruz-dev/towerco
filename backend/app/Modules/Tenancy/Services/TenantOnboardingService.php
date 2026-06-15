<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Services;

use App\Models\Tenant;
use App\Modules\Platform\Models\RolloutPlaybookVersion;
use App\Modules\Platform\Models\RolloutPolicyBundle;
use App\Modules\Platform\Models\TenantPlaybookBinding;
use App\Modules\Platform\Services\RolloutPlaybookCatalogService;
use App\Modules\Platform\Services\RolloutPolicyBundleService;
use App\Modules\Rollout\Services\TenantPlaybookSyncService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class TenantOnboardingService
{
    public function __construct(
        private readonly TenantAdminBootstrapService $adminBootstrap,
        private readonly TenantDomainSlugService $domainSlugs,
        private readonly RolloutPlaybookCatalogService $playbookCatalog,
        private readonly RolloutPolicyBundleService $policyBundles,
        private readonly TenantPlaybookSyncService $playbookSync,
        private readonly TenantRolloutBootstrapService $rolloutBootstrap,
    ) {}

    /**
     * @param  array{
     *   tenant_id?: string|null,
     *   domain: string,
     *   slug?: string|null,
     *   brand_domain?: string|null,
     *   environment?: string|null,
     *   tco_sequence_prefix?: string|null,
     *   playbook_version_id?: string|null,
     *   rollout_policy_bundle_id?: string|null,
     *   migrate?: bool,
     *   seed?: bool
     * }  $input
     * @return array{
     *   tenant: Tenant,
     *   domain_endpoints?: array<string, mixed>,
     *   playbook_version?: string,
     *   assigned_policy_code?: string|null,
     *   initial_admin?: array{email: string, password: string, password_generated: bool},
     *   public_holidays_seeded?: int,
     *   holiday_years?: list<int>
     * }
     */
    public function createTenant(array $input): array
    {
        $domain = $this->normalizeDomain($input['domain'] ?? '');

        $tenantId = $input['tenant_id'] ?? null;
        if ($tenantId !== null && $tenantId !== '') {
            $tenantId = (string) $tenantId;
        } else {
            $tenantId = (string) Str::uuid();
        }

        $environment = strtolower((string) ($input['environment'] ?? 'local'));
        $slug = $this->domainSlugs->normalizeSlug((string) ($input['slug'] ?? ''));
        $brandDomain = trim((string) ($input['brand_domain'] ?? 'toweros.app'));
        $tcoPrefix = strtoupper(substr((string) ($input['tco_sequence_prefix'] ?? 'A'), 0, 1));

        if ($slug !== '' && Tenant::query()->where('slug', $slug)->where('environment', $environment)->exists()) {
            throw ValidationException::withMessages([
                'environment' => [__('A tenant already exists for this slug and environment. Use Add env on the tenant directory instead.')],
            ]);
        }

        $planTier = strtolower(trim((string) ($input['plan_tier'] ?? config('toweros.tenant_provisioning.default_plan_tier', 'starter'))));
        if (! in_array($planTier, ['starter', 'professional', 'enterprise'], true)) {
            $planTier = 'starter';
        }

        /** @var Tenant $tenant */
        $tenant = Tenant::create([
            'id' => $tenantId,
            'slug' => $slug !== '' ? $slug : null,
            'brand_domain' => $brandDomain !== '' ? $brandDomain : null,
            'environment' => $environment,
            'tco_sequence_prefix' => $tcoPrefix,
            'mfa_required' => (bool) config('toweros.tenant_provisioning.default_mfa_required', false),
            'plan_tier' => $planTier,
            'seat_limit' => (int) ($input['seat_limit'] ?? 25),
        ]);

        app(\App\Modules\Billing\Services\TenantSubscriptionLifecycleService::class)
            ->applyProvisioningDefaults($tenant);
        $tenant->save();

        $tenant->createDomain($domain);

        $recommendation = $this->domainSlugs->recommend($tenant, $slug, $brandDomain, $environment);
        $this->domainSlugs->persistEndpoints($tenant, $recommendation);

        $playbookVersion = $this->resolvePlaybookVersion($input['playbook_version_id'] ?? null);
        $binding = $this->assignPlaybookBinding(
            $tenant,
            $playbookVersion,
            $input['rollout_policy_bundle_id'] ?? null,
        );

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

            // Stancl TenantCreated already runs CreateDatabase + MigrateDatabase synchronously.
            // Avoid a second full tenants:migrate pass (often exceeds frontend timeouts on Windows).

            $this->playbookSync->syncBindingToTenantDatabase(
                $tenant,
                $binding->fresh(['playbookVersion', 'rolloutPolicyBundle']),
            );

            $rolloutBootstrap = $this->rolloutBootstrap->provision($tenant);
        }

        // Stancl creates the tenant DB on createDomain(); always ensure admin@{domain} exists.
        $initialAdmin = $this->adminBootstrap->bootstrap($tenant, $domain);

        return [
            'tenant' => $tenant->fresh(['domains']),
            'domain_endpoints' => $recommendation,
            'playbook_version' => $playbookVersion->version,
            'assigned_policy_code' => $binding->rolloutPolicyBundle?->code,
            'initial_admin' => $initialAdmin,
            'public_holidays_seeded' => $rolloutBootstrap['public_holidays_seeded'],
            'holiday_years' => $rolloutBootstrap['holiday_years'],
        ];
    }

    private function assignPlaybookBinding(
        Tenant $tenant,
        RolloutPlaybookVersion $playbookVersion,
        ?string $rolloutPolicyBundleId,
    ): TenantPlaybookBinding {
        if (is_string($rolloutPolicyBundleId) && $rolloutPolicyBundleId !== '') {
            /** @var RolloutPolicyBundle $bundle */
            $bundle = RolloutPolicyBundle::query()->findOrFail($rolloutPolicyBundleId);

            return $this->policyBundles->assignToTenant($tenant, $bundle);
        }

        $defaultBundle = $this->policyBundles->resolveDefaultForProvisioning($playbookVersion);
        if ($defaultBundle !== null) {
            return $this->policyBundles->assignToTenant($tenant, $defaultBundle);
        }

        return $this->playbookCatalog->assignToTenant($tenant, $playbookVersion);
    }

    private function resolvePlaybookVersion(?string $playbookVersionId): RolloutPlaybookVersion
    {
        if (is_string($playbookVersionId) && $playbookVersionId !== '') {
            /** @var RolloutPlaybookVersion $version */
            $version = RolloutPlaybookVersion::query()->findOrFail($playbookVersionId);

            return $version;
        }

        $policy = (string) config('toweros.tenant_provisioning.default_playbook', 'latest');
        if ($policy === 'v1') {
            return $this->playbookCatalog->ensurePublishedV1();
        }

        return $this->playbookCatalog->latestPublished()
            ?? $this->playbookCatalog->ensurePublishedV1();
    }

    private function normalizeDomain(string $domain): string
    {
        $domain = trim($domain);
        $domain = preg_replace('#^https?://#i', '', $domain) ?? $domain;
        $domain = trim($domain, "/ \t\n\r\0\x0B");
        $domain = strtolower($domain);

        if ($domain === '') {
            throw new InvalidArgumentException('Domain is required.');
        }

        if (! preg_match('/^[a-z0-9.-]+$/', $domain)) {
            throw new InvalidArgumentException('Domain contains invalid characters.');
        }

        return $domain;
    }
}
