<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Services;

use App\Models\Tenant;
use App\Modules\Platform\Models\RolloutPlaybookVersion;
use App\Modules\Platform\Models\TenantPlaybookBinding;
use App\Modules\Platform\Services\RolloutPlaybookCatalogService;
use App\Modules\Platform\Services\RolloutPolicyBundleService;
use App\Modules\Rollout\Services\TenantPlaybookSyncService;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;
use App\Modules\Tenancy\Support\TenantEnabledModulesValidator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

final class TenantEnvironmentProvisioningService
{
    /** @var list<string> */
    private const RESERVED_SLUG_LABELS = ['local', 'test', 'staging', 'app', 'www'];

    public function __construct(
        private readonly TenantDomainSlugService $domainSlugs,
        private readonly RolloutPlaybookCatalogService $playbookCatalog,
        private readonly RolloutPolicyBundleService $policyBundles,
        private readonly TenantPlaybookSyncService $playbookSync,
        private readonly TenantRolloutBootstrapService $rolloutBootstrap,
        private readonly TenantAdminBootstrapService $adminBootstrap,
        private readonly TenantDocumentsBootstrapService $documentsBootstrap,
        private readonly TenantEnabledModulesResolver $enabledModulesResolver,
        private readonly TenantModuleRbacSyncService $moduleRbacSync,
    ) {}

    /**
     * @param  array{
     *   environment: string,
     *   domain?: string|null,
     *   migrate?: bool,
     *   seed?: bool,
     *   enabled_modules?: list<string>|null,
     *   admin_password?: string|null
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

        $adminPassword = isset($input['admin_password']) ? trim((string) $input['admin_password']) : '';
        if ($adminPassword !== '' && strlen($adminPassword) < 12) {
            throw ValidationException::withMessages([
                'admin_password' => [__('Admin password must be at least 12 characters.')],
            ]);
        }

        $orgRoot = $this->resolveOrgRoot($sourceTenant);
        $slug = $this->resolveSlug($orgRoot, $sourceTenant);
        if ($slug === '') {
            throw ValidationException::withMessages([
                'environment' => [__(
                    'Source tenant is missing an organization slug. Set slug on the org (e.g. atc), then create the environment again.'
                )],
            ]);
        }

        $brandDomain = trim((string) ($orgRoot->brand_domain ?? $sourceTenant->brand_domain ?? 'toweros.app'));

        if (Tenant::query()->where('slug', $slug)->where('environment', $environment)->exists()) {
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
                'domain' => [__('This domain is already assigned to another tenant. Delete the failed environment tenant first, or choose a different domain.')],
            ]);
        }

        $enabledModules = array_key_exists('enabled_modules', $input)
            ? TenantEnabledModulesValidator::validate($input['enabled_modules'], $this->enabledModulesResolver)
            : $sourceTenant->enabled_modules;

        $tenant = null;

        try {
            /** @var Tenant $tenant */
            $tenant = Tenant::create([
                'id' => (string) Str::uuid(),
                'slug' => $slug,
                'brand_domain' => $brandDomain !== '' ? $brandDomain : null,
                'environment' => $environment,
                'tco_sequence_prefix' => $orgRoot->tco_sequence_prefix ?? $sourceTenant->tco_sequence_prefix ?? 'A',
                'parent_tenant_id' => $orgRoot->id,
                'mfa_required' => $orgRoot->mfa_required
                    ?? (bool) config('toweros.tenant_provisioning.default_mfa_required', false),
                'plan_tier' => $orgRoot->plan_tier ?? config('toweros.tenant_provisioning.default_plan_tier', 'starter'),
                'subscription_status' => $orgRoot->subscription_status ?? 'active',
                'seat_limit' => $orgRoot->seat_limit ?? 25,
                'enabled_modules' => $enabledModules,
            ]);

            $tenant->createDomain($domain);
            $this->domainSlugs->persistEndpoints($tenant, $recommendation);

            $binding = $this->copyPlaybookBinding($sourceTenant, $tenant);

            $shouldMigrate = ! empty($input['migrate']);
            $shouldSeed = ! empty($input['seed']);

            // Stancl TenantCreated runs CreateDatabase + MigrateDatabase (sync unless queued).
            // Always ensure schema before bootstrap — covers queued provisioning and partial failures.
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

            $rolloutBootstrap = [
                'public_holidays_seeded' => 0,
                'holiday_years' => [],
            ];

            if ($shouldMigrate) {
                if ($binding !== null) {
                    $this->playbookSync->syncBindingToTenantDatabase($tenant, $binding);
                }

                $rolloutBootstrap = $this->rolloutBootstrap->provision($tenant);
            }

            // Stancl creates the tenant DB on TenantCreated; always ensure admin@{domain} exists.
            $initialAdmin = $this->adminBootstrap->bootstrap(
                $tenant,
                $domain,
                $adminPassword !== '' ? $adminPassword : null,
            );

            $this->documentsBootstrap->provisionSiteDocumentReviewForm($tenant);

            if ($enabledModules !== null) {
                $this->moduleRbacSync->syncForTenant($tenant);
            }

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
        } catch (ValidationException $e) {
            $this->discardFailedTenant($tenant);
            throw $e;
        } catch (InvalidArgumentException $e) {
            $this->discardFailedTenant($tenant);
            throw $e;
        } catch (Throwable $e) {
            $this->discardFailedTenant($tenant);
            Log::error('tenant.environment.provision_failed', [
                'source_tenant_id' => (string) $sourceTenant->id,
                'environment' => $environment,
                'domain' => $domain,
                'message' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'environment' => [__(
                    'Environment provisioning failed: :message',
                    ['message' => $this->publicFailureMessage($e)],
                )],
            ]);
        }
    }

    private function discardFailedTenant(?Tenant $tenant): void
    {
        if ($tenant === null) {
            return;
        }

        try {
            $tenant->delete();
        } catch (Throwable $cleanupError) {
            Log::warning('tenant.environment.discard_failed', [
                'tenant_id' => (string) $tenant->id,
                'message' => $cleanupError->getMessage(),
            ]);
        }
    }

    private function publicFailureMessage(Throwable $e): string
    {
        $message = trim($e->getMessage());
        if ($message === '') {
            return (string) __('Unexpected server error while creating the environment tenant.');
        }

        // Keep operator-facing SQL noise short.
        if (str_contains($message, 'Base table or view not found')
            || str_contains($message, 'no such table')
            || str_contains($message, 'doesn\'t exist')) {
            return (string) __('Tenant database schema was not ready. Try again; if it persists, check tenancy migrate/queue settings.');
        }

        return Str::limit($message, 240);
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
        foreach ([$orgRoot->slug ?? '', $sourceTenant->slug ?? ''] as $candidate) {
            $slug = $this->domainSlugs->normalizeSlug((string) $candidate);
            if ($slug !== '' && ! $this->isReservedSlugLabel($slug)) {
                return $slug;
            }
        }

        // Brand hosts like staging.alliancetowers.com must not become slug "staging".
        return '';
    }

    private function isReservedSlugLabel(string $slug): bool
    {
        return in_array($slug, self::RESERVED_SLUG_LABELS, true);
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
