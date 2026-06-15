<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services;

use App\Models\Tenant;
use App\Modules\Platform\Models\RolloutPlaybookVersion;
use App\Modules\Platform\Models\TenantPlaybookBinding;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class PlatformDashboardService
{
    public function __construct(
        private readonly PlatformTenantInsightService $insights,
        private readonly PlatformTenantAuditIndexService $auditIndex,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $tenants = Tenant::query()
            ->with(['domains:id,domain,tenant_id'])
            ->orderByDesc('created_at')
            ->get();

        $tenantIds = $tenants->pluck('id')->all();
        $bindings = TenantPlaybookBinding::query()
            ->whereIn('tenant_id', $tenantIds)
            ->with(['playbookVersion:id,version', 'rolloutPolicyBundle:id,code'])
            ->get()
            ->keyBy('tenant_id');

        $latestVersion = RolloutPlaybookVersion::query()
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->value('version');

        $enriched = $tenants->map(function (Tenant $tenant) use ($bindings, $latestVersion): array {
            /** @var TenantPlaybookBinding|null $binding */
            $binding = $bindings->get($tenant->id);
            $assigned = $binding?->playbookVersion?->version;
            $domains = $tenant->domains->pluck('domain')->values()->all();

            return [
                'id' => (string) $tenant->id,
                'slug' => $tenant->slug,
                'brand_domain' => $tenant->brand_domain,
                'environment' => (string) ($tenant->environment ?? 'production'),
                'parent_tenant_id' => $tenant->parent_tenant_id,
                'domains' => $domains,
                'primary_domain' => $domains[0] ?? null,
                'mfa_required' => (bool) ($tenant->mfa_required ?? false),
                'plan_tier' => (string) ($tenant->plan_tier ?? 'starter'),
                'subscription_status' => (string) ($tenant->subscription_status ?? 'active'),
                'seat_limit' => (int) ($tenant->seat_limit ?? 25),
                'created_at' => $tenant->created_at?->toIso8601String(),
                'playbook_upgrade_available' => $latestVersion !== null
                    && $assigned !== null
                    && version_compare((string) $latestVersion, (string) $assigned, '>'),
                'assigned_playbook_version' => $assigned,
            ];
        });

        $organizations = $this->organizationRoots($enriched);
        $environmentCounts = $this->countBy($enriched, 'environment');
        $mfaOn = $enriched->where('mfa_required', true)->count();
        $upgradePending = $enriched->where('playbook_upgrade_available', true)->count();
        $missingDomain = $enriched->filter(fn (array $row): bool => ($row['primary_domain'] ?? null) === null)->count();
        $recent30d = $enriched->filter(function (array $row): bool {
            if ($row['created_at'] === null) {
                return false;
            }

            return Carbon::parse($row['created_at'])->gte(Carbon::now()->subDays(30));
        })->count();

        $productionWithoutMfa = $enriched
            ->where('environment', 'production')
            ->where('mfa_required', false)
            ->count();

        $insight = $this->insights->build($tenants);
        $healthSummary = $insight['health_summary'];
        $seatSummary = $insight['seat_summary'];
        $subscriptionAlerts = $insight['subscription_alerts'];

        $kpis = [
            [
                'key' => 'total_tenants',
                'label' => 'Tenant records',
                'value' => (string) $enriched->count(),
                'change' => 'All environment rows in central DB',
                'tone' => 'neutral',
            ],
            [
                'key' => 'organizations',
                'label' => 'Organizations',
                'value' => (string) $organizations->count(),
                'change' => 'Unique org roots (slug + brand)',
                'tone' => 'success',
            ],
            [
                'key' => 'recent_30d',
                'label' => 'Provisioned (30d)',
                'value' => (string) $recent30d,
                'change' => 'New tenant rows',
                'tone' => $recent30d > 0 ? 'success' : 'neutral',
            ],
            [
                'key' => 'mfa_on',
                'label' => 'MFA enforced',
                'value' => (string) $mfaOn,
                'change' => $enriched->count() > 0
                    ? sprintf('%d%% of tenant rows', (int) round(($mfaOn / max(1, $enriched->count())) * 100))
                    : 'No tenants yet',
                'tone' => $mfaOn > 0 ? 'success' : 'warning',
            ],
            [
                'key' => 'playbook_upgrades',
                'label' => 'Playbook upgrades',
                'value' => (string) $upgradePending,
                'change' => $latestVersion !== null ? "Latest published v{$latestVersion}" : 'No published playbook',
                'tone' => $upgradePending > 0 ? 'warning' : 'success',
            ],
            [
                'key' => 'missing_domain',
                'label' => 'Missing hostname',
                'value' => (string) $missingDomain,
                'change' => 'Failed or incomplete provisioning',
                'tone' => $missingDomain > 0 ? 'danger' : 'success',
            ],
            [
                'key' => 'seats_used',
                'label' => 'Seats in use',
                'value' => (string) ($seatSummary['total_seats_used'] ?? 0),
                'change' => sprintf(
                    '%d / %d licensed across healthy tenants',
                    (int) ($seatSummary['total_seats_used'] ?? 0),
                    (int) ($seatSummary['total_seat_limit'] ?? 0),
                ),
                'tone' => ($seatSummary['tenants_over_limit'] ?? 0) > 0 ? 'danger' : 'neutral',
            ],
            [
                'key' => 'db_health',
                'label' => 'DB health issues',
                'value' => (string) (($healthSummary['database_missing'] ?? 0) + ($healthSummary['migrations_pending'] ?? 0)),
                'change' => sprintf(
                    '%d healthy · %d missing DB · %d pending migrate',
                    (int) ($healthSummary['healthy'] ?? 0),
                    (int) ($healthSummary['database_missing'] ?? 0),
                    (int) ($healthSummary['migrations_pending'] ?? 0),
                ),
                'tone' => (($healthSummary['database_missing'] ?? 0) + ($healthSummary['migrations_pending'] ?? 0)) > 0
                    ? 'warning'
                    : 'success',
            ],
            [
                'key' => 'subscription_alerts',
                'label' => 'Subscription alerts',
                'value' => (string) count($subscriptionAlerts),
                'change' => 'Non-active subscription status',
                'tone' => count($subscriptionAlerts) > 0 ? 'warning' : 'success',
            ],
        ];

        $actions = array_values(array_filter([
            $missingDomain > 0 ? [
                'id' => 'pf-missing-domain',
                'label' => 'Tenants without hostname',
                'count' => $missingDomain,
                'href' => '/platform#tenant-directory',
                'priority' => 'high',
            ] : null,
            $upgradePending > 0 ? [
                'id' => 'pf-playbook-upgrade',
                'label' => 'Tenants with playbook upgrade',
                'count' => $upgradePending,
                'href' => '/platform/playbooks',
                'priority' => 'high',
            ] : null,
            $productionWithoutMfa > 0 ? [
                'id' => 'pf-prod-mfa-off',
                'label' => 'Production tenants without MFA',
                'count' => $productionWithoutMfa,
                'href' => '/platform#tenant-directory',
                'priority' => 'normal',
            ] : null,
            ($healthSummary['database_missing'] ?? 0) > 0 ? [
                'id' => 'pf-db-missing',
                'label' => 'Missing tenant databases',
                'count' => (int) $healthSummary['database_missing'],
                'href' => '/platform#tenant-directory',
                'priority' => 'high',
            ] : null,
            ($healthSummary['migrations_pending'] ?? 0) > 0 ? [
                'id' => 'pf-migrate-pending',
                'label' => 'Pending tenant migrations',
                'count' => (int) $healthSummary['migrations_pending'],
                'href' => '/platform#tenant-directory',
                'priority' => 'high',
            ] : null,
            ($seatSummary['tenants_over_limit'] ?? 0) > 0 ? [
                'id' => 'pf-seats-over',
                'label' => 'Tenants over seat limit',
                'count' => (int) $seatSummary['tenants_over_limit'],
                'href' => '/platform#tenant-directory',
                'priority' => 'high',
            ] : null,
            count($subscriptionAlerts) > 0 ? [
                'id' => 'pf-subscription',
                'label' => 'Subscription needs attention',
                'count' => count($subscriptionAlerts),
                'href' => '/platform#tenant-directory',
                'priority' => 'normal',
            ] : null,
        ]));

        return [
            'environment' => app()->environment(),
            'latest_playbook_version' => $latestVersion,
            'kpis' => array_slice($kpis, 0, 9),
            'environment_breakdown' => $environmentCounts,
            'subscription_breakdown' => $this->countBy($enriched, 'subscription_status'),
            'plan_breakdown' => $this->countBy($enriched, 'plan_tier'),
            'actions' => $actions,
            'health_summary' => $healthSummary,
            'health_issues' => $insight['health_issues'],
            'seat_summary' => $seatSummary,
            'seat_usage' => $insight['seat_usage'],
            'subscription_alerts' => $subscriptionAlerts,
            'provisioning_trend' => $insight['provisioning_trend'],
            'brand_breakdown' => $insight['brand_breakdown'],
            'recent_tenants' => $enriched
                ->take(5)
                ->map(static fn (array $row) => [
                    'id' => $row['id'],
                    'slug' => $row['slug'],
                    'environment' => $row['environment'],
                    'primary_domain' => $row['primary_domain'],
                    'created_at' => $row['created_at'],
                    'mfa_required' => $row['mfa_required'],
                    'playbook_upgrade_available' => $row['playbook_upgrade_available'],
                ])
                ->values()
                ->all(),
            'recent_audit' => $this->auditIndex->recent(12),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function organizationRoots(Collection $rows): Collection
    {
        return $rows
            ->filter(fn (array $row): bool => ($row['parent_tenant_id'] ?? null) === null)
            ->unique(fn (array $row): string => strtolower(trim((string) ($row['slug'] ?? ''))).'|'.strtolower(trim((string) ($row['brand_domain'] ?? ''))));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function countBy(Collection $rows, string $field): array
    {
        $counts = [];

        foreach ($rows as $row) {
            $key = (string) ($row[$field] ?? 'unknown');
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }
}
