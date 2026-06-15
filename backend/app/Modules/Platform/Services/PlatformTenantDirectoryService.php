<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services;

use App\Models\Tenant;
use App\Modules\Billing\Services\TenantPlanEntitlementsService;
use App\Modules\Billing\Services\TenantRfiMeterService;
use App\Modules\Billing\Services\TenantSubscriptionLifecycleService;
use App\Modules\Platform\Models\RolloutPlaybookVersion;
use App\Modules\Platform\Models\TenantPlaybookBinding;
use App\Modules\Platform\Support\TenantThemeTokensValidator;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class PlatformTenantDirectoryService
{
    public function __construct(
        private readonly TenantPlanEntitlementsService $entitlements,
        private readonly TenantRfiMeterService $rfiMeter,
        private readonly TenantEnabledModulesResolver $enabledModulesResolver,
        private readonly TenantSubscriptionLifecycleService $subscriptions,
    ) {}

    /**
     * @return array{items: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function list(Request $request): array
    {
        $search = Str::limit(trim((string) $request->query('search', '')), 255, '');
        $environment = Str::limit(trim((string) $request->query('environment', '')), 32, '');
        $planTier = Str::limit(trim((string) $request->query('plan_tier', '')), 32, '');
        $subscriptionStatus = Str::limit(trim((string) $request->query('subscription_status', '')), 32, '');
        $modulesFilter = Str::limit(trim((string) $request->query('modules', '')), 32, '');
        $accessMode = Str::limit(trim((string) $request->query('access_mode', '')), 32, '');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(10, (int) $request->query('per_page', 25)));

        $query = Tenant::query()
            ->with(['domains:id,domain,tenant_id'])
            ->orderByDesc('created_at');

        $this->applySearch($query, $search);
        $this->applyEnvironmentFilter($query, $environment);
        $this->applyPlanTierFilter($query, $planTier);
        $this->applySubscriptionStatusFilter($query, $subscriptionStatus);
        $this->applyModulesFilter($query, $modulesFilter);
        $this->applyAccessModeFilter($query, $accessMode);

        $total = (clone $query)->count();
        $tenants = $query
            ->forPage($page, $perPage)
            ->get();

        $items = $this->mapTenants($tenants);

        return [
            'items' => $items,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function show(Tenant $tenant): ?array
    {
        $tenant->loadMissing(['domains:id,domain,tenant_id']);
        $mapped = $this->mapTenants(collect([$tenant]));

        return $mapped[0] ?? null;
    }

    private function applySearch(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $like = '%'.addcslashes($search, '%_\\').'%';
        $query->where(function (Builder $q) use ($like): void {
            $q->where('id', 'like', $like)
                ->orWhere('slug', 'like', $like)
                ->orWhere('brand_domain', 'like', $like)
                ->orWhereHas('domains', function (Builder $domains) use ($like): void {
                    $domains->where('domain', 'like', $like);
                });
        });
    }

    private function applyEnvironmentFilter(Builder $query, string $environment): void
    {
        if ($environment === '') {
            return;
        }

        $query->where('environment', $environment);
    }

    private function applyPlanTierFilter(Builder $query, string $planTier): void
    {
        if ($planTier === '') {
            return;
        }

        $query->where('plan_tier', $planTier);
    }

    private function applySubscriptionStatusFilter(Builder $query, string $status): void
    {
        if ($status === '') {
            return;
        }

        $query->where('subscription_status', $status);
    }

    private function applyAccessModeFilter(Builder $query, string $accessMode): void
    {
        if ($accessMode === '') {
            return;
        }

        if ($accessMode === 'blocked') {
            $query->where(function (Builder $q): void {
                $q->where('operator_access_mode', 'blocked')
                    ->orWhere('subscription_status', 'canceled')
                    ->orWhereNotNull('subscription_locked_at');
            });

            return;
        }

        if ($accessMode === 'read_only') {
            $query->where('operator_access_mode', 'read_only');

            return;
        }

        if ($accessMode === 'grace') {
            $query->where('subscription_status', 'past_due')
                ->whereNull('subscription_locked_at')
                ->where(function (Builder $q): void {
                    $q->whereNull('operator_access_mode')
                        ->orWhere('operator_access_mode', '!=', 'blocked');
                });
        }
    }

    private function applyModulesFilter(Builder $query, string $modulesFilter): void
    {
        if ($modulesFilter === '') {
            return;
        }

        $platform = $this->enabledModulesResolver->platformModules();
        $platformIsEaOnly = in_array('e_approval', $platform, true)
            && ! in_array('project_one', $platform, true);

        if ($modulesFilter === 'e_approval_only') {
            $query->where(function (Builder $q) use ($platformIsEaOnly): void {
                $q->where(function (Builder $explicit): void {
                    $explicit->whereNotNull('enabled_modules')
                        ->whereRaw("JSON_CONTAINS(enabled_modules, '\"e_approval\"')")
                        ->whereRaw("NOT JSON_CONTAINS(enabled_modules, '\"project_one\"')");
                });

                if ($platformIsEaOnly) {
                    $q->orWhereNull('enabled_modules');
                }
            });

            return;
        }

        if ($modulesFilter === 'project_one') {
            $query->where(function (Builder $q) use ($platform): void {
                $q->where(function (Builder $explicit): void {
                    $explicit->whereNotNull('enabled_modules')
                        ->whereRaw("JSON_CONTAINS(enabled_modules, '\"project_one\"')");
                });

                if (in_array('project_one', $platform, true)) {
                    $q->orWhereNull('enabled_modules');
                }
            });

            return;
        }

        if ($modulesFilter === 'ticketing') {
            $query->where(function (Builder $q) use ($platform): void {
                $q->where(function (Builder $explicit): void {
                    $explicit->whereNotNull('enabled_modules')
                        ->whereRaw("JSON_CONTAINS(enabled_modules, '\"ticketing\"')");
                });

                if (in_array('ticketing', $platform, true)) {
                    $q->orWhereNull('enabled_modules');
                }
            });
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Tenant>|\Illuminate\Database\Eloquent\Collection<int, Tenant>  $tenants
     * @return list<array<string, mixed>>
     */
    private function mapTenants($tenants): array
    {
        $rows = $tenants->map(function (Tenant $tenant): array {
            $subscription = $this->subscriptions->snapshot($tenant);

            return [
                'id' => $tenant->id,
                'domains' => $tenant->domains->pluck('domain')->values()->all(),
                'created_at' => $tenant->created_at?->toIso8601String(),
                'mfa_required' => (bool) ($tenant->mfa_required ?? false),
                'plan_tier' => (string) ($tenant->plan_tier ?? 'starter'),
                'subscription_status' => (string) ($tenant->subscription_status ?? 'active'),
                'access_mode' => $subscription['access_mode'],
                'operator_access_mode' => $subscription['operator_access_mode'],
                'seat_limit' => (int) ($tenant->seat_limit ?? 25),
                'effective_seat_limit' => $this->entitlements->effectiveSeatLimit($tenant),
                'effective_rfi_limit' => $this->entitlements->effectiveRfiLimit($tenant),
                'rfi_units_used' => $this->rfiMeter->billableCount($tenant),
                'billing_meter_starts_at' => $tenant->billing_meter_starts_at?->toIso8601String(),
                'billing_interval' => (string) ($tenant->billing_interval ?? 'monthly'),
                'billing_overrides' => $tenant->billing_overrides,
                'enabled_modules' => $tenant->enabled_modules,
                'effective_enabled_modules' => $this->enabledModulesResolver->resolveForTenant($tenant),
                'slug' => $tenant->slug,
                'brand_domain' => $tenant->brand_domain,
                'environment' => (string) ($tenant->environment ?? 'production'),
                'parent_tenant_id' => $tenant->parent_tenant_id,
                'theme_tokens' => $tenant->theme_tokens !== null
                    ? TenantThemeTokensValidator::sanitizeForPublic($tenant->theme_tokens)
                    : null,
            ];
        });

        $tenantIds = $rows->pluck('id')->all();
        $bindings = TenantPlaybookBinding::query()
            ->whereIn('tenant_id', $tenantIds)
            ->with(['playbookVersion:id,version', 'rolloutPolicyBundle:id,code,name'])
            ->get()
            ->keyBy('tenant_id');

        $latestVersion = RolloutPlaybookVersion::query()
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->value('version');

        return $rows
            ->map(function (array $row) use ($bindings, $latestVersion): array {
                /** @var TenantPlaybookBinding|null $binding */
                $binding = $bindings->get($row['id']);
                $assigned = $binding?->playbookVersion?->version;

                $row['assigned_playbook_version'] = $assigned;
                $row['assigned_rollout_policy_code'] = $binding?->rolloutPolicyBundle?->code;
                $row['assigned_rollout_policy_name'] = $binding?->rolloutPolicyBundle?->name;
                $row['rollout_policy_bundle_id'] = $binding?->rollout_policy_bundle_id;
                $row['playbook_upgrade_available'] = $latestVersion !== null
                    && $assigned !== null
                    && version_compare((string) $latestVersion, (string) $assigned, '>');

                return $row;
            })
            ->values()
            ->all();
    }
}
