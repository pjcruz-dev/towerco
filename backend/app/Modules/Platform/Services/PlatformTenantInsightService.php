<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services;

use App\Models\Tenant;
use App\Modules\Identity\Models\TenantUser;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PlatformTenantInsightService
{
    private const CACHE_SECONDS = 90;

    /**
     * @param  Collection<int, Tenant>  $tenants
     * @return array<string, mixed>
     */
    public function build(Collection $tenants): array
    {
        $latest = $tenants->max('updated_at');
        $cacheKey = 'platform:dashboard:insights:'.$tenants->count().':'.($latest?->timestamp ?? 0);

        return Cache::remember($cacheKey, self::CACHE_SECONDS, fn (): array => $this->buildUncached($tenants));
    }

    /**
     * @param  Collection<int, Tenant>  $tenants
     * @return array<string, mixed>
     */
    private function buildUncached(Collection $tenants): array
    {
        $seatRows = [];
        $healthIssues = [];
        $databaseMissing = 0;
        $migrationsPending = 0;
        $healthy = 0;

        foreach ($tenants as $tenant) {
            $databaseName = $tenant->database()->getName();
            $dbExists = $tenant->database()->manager()->databaseExists($databaseName);
            $label = $this->tenantLabel($tenant);

            if (! $dbExists) {
                $databaseMissing++;
                $healthIssues[] = [
                    'id' => (string) $tenant->id,
                    'slug' => $tenant->slug,
                    'primary_domain' => $tenant->domains->first()?->domain,
                    'issue' => 'missing_database',
                    'detail' => "MySQL database {$databaseName} not found",
                ];

                continue;
            }

            $pendingCount = $this->countPendingMigrations($tenant);
            if ($pendingCount > 0) {
                $migrationsPending++;
                $healthIssues[] = [
                    'id' => (string) $tenant->id,
                    'slug' => $tenant->slug,
                    'primary_domain' => $tenant->domains->first()?->domain,
                    'issue' => 'migrations_pending',
                    'detail' => "{$pendingCount} pending migration(s)",
                    'pending_migrations' => $pendingCount,
                ];

                continue;
            }

            $healthy++;

            $seatUsed = $this->countTenantUsers($tenant);
            $seatLimit = (int) ($tenant->seat_limit ?? 25);
            $utilization = $seatLimit > 0 ? (int) round(($seatUsed / $seatLimit) * 100) : 0;

            $seatRows[] = [
                'id' => (string) $tenant->id,
                'slug' => $tenant->slug,
                'label' => $label,
                'primary_domain' => $tenant->domains->first()?->domain,
                'environment' => (string) ($tenant->environment ?? 'production'),
                'seat_used' => $seatUsed,
                'seat_limit' => $seatLimit,
                'utilization_percent' => min(100, $utilization),
                'over_limit' => $seatUsed > $seatLimit,
            ];
        }

        $totalSeatsUsed = array_sum(array_column($seatRows, 'seat_used'));
        $totalSeatLimit = array_sum(array_column($seatRows, 'seat_limit'));
        $overLimit = count(array_filter($seatRows, static fn (array $r): bool => $r['over_limit']));
        $nearLimit = count(array_filter(
            $seatRows,
            static fn (array $r): bool => ! $r['over_limit'] && $r['utilization_percent'] >= 80,
        ));

        usort($seatRows, static fn (array $a, array $b): int => $b['utilization_percent'] <=> $a['utilization_percent']);

        $subscriptionAlerts = $tenants
            ->filter(static fn (Tenant $t): bool => strtolower((string) ($t->subscription_status ?? 'active')) !== 'active')
            ->map(static fn (Tenant $t): array => [
                'id' => (string) $t->id,
                'slug' => $t->slug,
                'primary_domain' => $t->domains->first()?->domain,
                'subscription_status' => (string) ($t->subscription_status ?? 'unknown'),
                'plan_tier' => (string) ($t->plan_tier ?? 'starter'),
                'environment' => (string) ($t->environment ?? 'production'),
            ])
            ->values()
            ->all();

        return [
            'health_summary' => [
                'healthy' => $healthy,
                'database_missing' => $databaseMissing,
                'migrations_pending' => $migrationsPending,
            ],
            'health_issues' => $healthIssues,
            'seat_summary' => [
                'total_seats_used' => $totalSeatsUsed,
                'total_seat_limit' => $totalSeatLimit,
                'tenants_over_limit' => $overLimit,
                'tenants_near_limit' => $nearLimit,
            ],
            'seat_usage' => array_slice($seatRows, 0, 10),
            'subscription_alerts' => $subscriptionAlerts,
            'provisioning_trend' => $this->provisioningTrend($tenants),
            'brand_breakdown' => $this->brandBreakdown($tenants),
        ];
    }

    private function tenantLabel(Tenant $tenant): string
    {
        $domain = $tenant->domains->first()?->domain;

        return $tenant->slug ?? $domain ?? substr((string) $tenant->id, 0, 8);
    }

    private function countTenantUsers(Tenant $tenant): int
    {
        try {
            return (int) $tenant->run(function (): int {
                if (! Schema::connection('tenant')->hasTable('users')) {
                    return 0;
                }

                return TenantUser::query()->count();
            });
        } catch (\Throwable) {
            return 0;
        }
    }

    private function countPendingMigrations(Tenant $tenant): int
    {
        try {
            return (int) $tenant->run(function (): int {
                if (! Schema::connection('tenant')->hasTable('migrations')) {
                    return 1;
                }

                $ran = DB::connection('tenant')->table('migrations')->pluck('migration')->all();
                $path = database_path('migrations/tenant');
                $files = glob($path.'/*.php') ?: [];
                $fileNames = array_map(
                    static fn (string $file): string => pathinfo($file, PATHINFO_FILENAME),
                    $files,
                );

                return count(array_diff($fileNames, $ran));
            });
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @param  Collection<int, Tenant>  $tenants
     * @return list<array{week_start: string, label: string, count: int}>
     */
    private function provisioningTrend(Collection $tenants): array
    {
        $weeks = [];
        $start = Carbon::now()->startOfWeek()->subWeeks(11);

        for ($i = 0; $i < 12; $i++) {
            $weekStart = $start->copy()->addWeeks($i);
            $key = $weekStart->toDateString();
            $weeks[$key] = [
                'week_start' => $key,
                'label' => $weekStart->format('M j'),
                'count' => 0,
            ];
        }

        foreach ($tenants as $tenant) {
            if ($tenant->created_at === null) {
                continue;
            }
            $created = Carbon::parse($tenant->created_at)->startOfWeek();
            $key = $created->toDateString();
            if (isset($weeks[$key])) {
                $weeks[$key]['count']++;
            }
        }

        return array_values($weeks);
    }

    /**
     * @param  Collection<int, Tenant>  $tenants
     * @return array<string, int>
     */
    private function brandBreakdown(Collection $tenants): array
    {
        $counts = [];

        foreach ($tenants as $tenant) {
            $brand = trim((string) ($tenant->brand_domain ?? ''));
            if ($brand === '') {
                $brand = '—';
            }
            $counts[$brand] = ($counts[$brand] ?? 0) + 1;
        }

        arsort($counts);

        return $counts;
    }
}
