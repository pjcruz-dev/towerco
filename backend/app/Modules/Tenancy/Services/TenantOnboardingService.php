<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Services;

use App\Models\Tenant;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;
use App\Modules\Tenancy\Support\TenantEnabledModulesValidator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class TenantOnboardingService
{
    public function __construct(
        private readonly TenantAdminBootstrapService $adminBootstrap,
        private readonly TenantDomainSlugService $domainSlugs,
        private readonly TenantEnabledModulesResolver $enabledModulesResolver,
        private readonly TenantModuleRbacSyncService $moduleRbacSync,
    ) {}

    /**
     * @param  array{
     *   tenant_id?: string|null,
     *   domain: string,
     *   slug?: string|null,
     *   brand_domain?: string|null,
     *   environment?: string|null,
     *   tco_sequence_prefix?: string|null,
     *   enabled_modules?: list<string>|null,
     *   migrate?: bool,
     *   seed?: bool
     * }  $input
     * @return array{
     *   tenant: Tenant,
     *   domain_endpoints?: array<string, mixed>,
     *   initial_admin?: array{email: string, password: string, password_generated: bool}
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

        $enabledModules = array_key_exists('enabled_modules', $input)
            ? TenantEnabledModulesValidator::validate($input['enabled_modules'], $this->enabledModulesResolver)
            : null;

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
            'enabled_modules' => $enabledModules,
        ]);

        app(\App\Modules\Billing\Services\TenantSubscriptionLifecycleService::class)
            ->applyProvisioningDefaults($tenant);
        $tenant->save();

        $tenant->createDomain($domain);

        $recommendation = $this->domainSlugs->recommend($tenant, $slug, $brandDomain, $environment);
        $this->domainSlugs->persistEndpoints($tenant, $recommendation);

        $shouldMigrate = ! empty($input['migrate']);
        $shouldSeed = ! empty($input['seed']);

        if ($shouldMigrate || $shouldSeed) {
            $migrateParams = [
                '--tenants' => [$tenant->id],
                '--force' => true,
            ];
            if ($shouldSeed) {
                $migrateParams['--seed'] = true;
                $migrateParams['--seeder'] = 'Database\\Seeders\\TenantDatabaseSeeder';
            }
            Artisan::call('tenants:migrate', $migrateParams);
        }

        $initialAdmin = $this->adminBootstrap->bootstrap($tenant, $domain);

        // Seed Administrator + ATC job roles / module tiers for enabled modules.
        $this->moduleRbacSync->syncForTenant($tenant);

        return [
            'tenant' => $tenant->fresh(['domains']),
            'domain_endpoints' => $recommendation,
            'initial_admin' => $initialAdmin,
        ];
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
