<?php



declare(strict_types=1);



namespace App\Modules\Billing\Services;



use App\Models\Tenant;

use App\Models\TenantBillingAuditLog;

use App\Modules\Platform\Services\PlatformTenantInsightService;

use Illuminate\Support\Collection;



final class PlatformBillingInsightsService

{

    public function __construct(

        private readonly PlatformTenantInsightService $tenantInsights,

        private readonly StripeBillingConfig $stripe,

        private readonly TenantPlanEntitlementsService $entitlements,

        private readonly PlatformBillingCatalogService $catalog,

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



        $insight = $this->tenantInsights->build($tenants);

        $resolved = $this->catalog->resolvedCatalog();

        $currency = (string) ($resolved['currency'] ?? 'USD');

        $prices = $this->catalog->tierListPrices();



        $revenueByTier = [];

        $billableStatuses = ['active', 'trial'];

        $estimatedMrr = 0.0;

        $stripeLinked = 0;



        foreach ($tenants as $tenant) {

            $tier = $this->entitlements->normalizeTier($tenant->plan_tier);

            $status = strtolower((string) ($tenant->subscription_status ?? 'active'));



            if (in_array($status, $billableStatuses, true)) {

                $monthly = (float) ($prices[$tier] ?? 0);

                $estimatedMrr += $monthly;

                $revenueByTier[$tier] = [

                    'plan_tier' => $tier,

                    'label' => $this->entitlements->forTier($tier)['label'],

                    'tenant_count' => ($revenueByTier[$tier]['tenant_count'] ?? 0) + 1,

                    'estimated_mrr' => ($revenueByTier[$tier]['estimated_mrr'] ?? 0) + $monthly,

                ];

            }



            if (trim((string) ($tenant->stripe_subscription_id ?? '')) !== '') {

                $stripeLinked++;

            }

        }



        ksort($revenueByTier);



        $enterpriseOverrides = $tenants

            ->filter(fn (Tenant $t): bool => $this->entitlements->hasBillingOverrides($t))

            ->map(fn (Tenant $t): array => [

                'id' => (string) $t->id,

                'slug' => $t->slug,

                'plan_tier' => (string) ($t->plan_tier ?? 'starter'),

                'primary_domain' => $t->domains->first()?->domain,

                'billing_overrides' => $t->billing_overrides,

            ])

            ->values()

            ->all();



        $recentActivity = TenantBillingAuditLog::query()

            ->with('tenant.domains:id,domain,tenant_id')

            ->orderByDesc('created_at')

            ->limit(15)

            ->get()

            ->map(static function (TenantBillingAuditLog $log): array {

                $tenant = $log->tenant;

                $domain = $tenant?->domains->first()?->domain;



                return [

                    'id' => (string) $log->id,

                    'tenant_id' => (string) $log->tenant_id,

                    'tenant_label' => $tenant?->slug ?? $domain ?? substr((string) $log->tenant_id, 0, 8),

                    'actor_email' => $log->actor_email,

                    'changes' => $log->changes,

                    'created_at' => $log->created_at?->toIso8601String(),

                ];

            })

            ->all();



        $tenantRows = $this->tenantBillingRows($tenants, $prices);



        $estimateNote = __(

            'Indicative MRR from platform catalog list prices in :currency — not Stripe-invoiced amounts.',

            ['currency' => $currency],

        );



        return [

            'currency' => $currency,

            'estimated_mrr' => round($estimatedMrr, 2),

            'estimated_mrr_note' => $estimateNote,

            'revenue_by_tier' => array_values($revenueByTier),

            'plan_breakdown' => $this->countBy($tenants, 'plan_tier'),

            'subscription_breakdown' => $this->countBy($tenants, 'subscription_status'),

            'stripe' => [

                ...$this->stripe->publicSnapshot(),

                'linked_subscriptions' => $stripeLinked,

            ],

            'usage_totals' => [

                'tenants' => $tenants->count(),

                'total_seats_used' => (int) ($insight['seat_summary']['total_seats_used'] ?? 0),

                'total_seat_limit' => (int) ($insight['seat_summary']['total_seat_limit'] ?? 0),

                'tenants_over_limit' => (int) ($insight['seat_summary']['tenants_over_limit'] ?? 0),

                'tenants_with_overrides' => count($enterpriseOverrides),

            ],

            'enterprise_overrides' => $enterpriseOverrides,

            'recent_billing_activity' => $recentActivity,

            'tenant_billing_rows' => $tenantRows,

            'list_prices' => $prices,

        ];

    }



    /**

     * @param  Collection<int, Tenant>  $tenants

     * @param  array<string, float>  $prices

     * @return list<array<string, mixed>>

     */

    private function tenantBillingRows(Collection $tenants, array $prices): array

    {

        return $tenants

            ->map(function (Tenant $tenant) use ($prices): array {

                $tier = $this->entitlements->normalizeTier($tenant->plan_tier);

                $status = strtolower((string) ($tenant->subscription_status ?? 'active'));



                return [

                    'id' => (string) $tenant->id,

                    'slug' => $tenant->slug,

                    'primary_domain' => $tenant->domains->first()?->domain,

                    'plan_tier' => $tier,

                    'plan_label' => $this->entitlements->forTier($tier)['label'],

                    'subscription_status' => $status,

                    'seat_limit' => $this->entitlements->effectiveSeatLimit($tenant),

                    'has_billing_overrides' => $this->entitlements->hasBillingOverrides($tenant),

                    'stripe_subscription_id' => $tenant->stripe_subscription_id,

                    'estimated_mrr' => in_array($status, ['active', 'trial'], true)

                        ? (float) ($prices[$tier] ?? 0)

                        : 0,

                ];

            })

            ->sortByDesc('estimated_mrr')

            ->values()

            ->take(25)

            ->all();

    }



    /**

     * @param  Collection<int, Tenant>  $tenants

     * @return array<string, int>

     */

    private function countBy(Collection $tenants, string $field): array

    {

        $counts = [];

        foreach ($tenants as $tenant) {

            $key = (string) ($tenant->{$field} ?? 'unknown');

            $counts[$key] = ($counts[$key] ?? 0) + 1;

        }

        ksort($counts);



        return $counts;

    }

}


