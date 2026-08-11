"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";

import { PlatformBillingCatalogPanel } from "@/components/platform/platform-billing-catalog-panel";
import { PlatformDataTable } from "@/components/platform/platform-data-table";
import { Badge } from "@/components/ui/badge";
import { buttonVariants } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { RefreshingHint } from "@/components/ui/refreshing-hint";
import { formatMoney } from "@/lib/billing/format-money";
import { platformFetchBillingInsights } from "@/lib/api/modules/platform-api";
import { PlatformBillingPageSkeleton } from "@/components/ui/page-skeletons";
import { cn } from "@/lib/utils";

export function PlatformBillingPageClient() {
  const query = useQuery({
    queryKey: ["platform", "billing", "insights"],
    queryFn: platformFetchBillingInsights,
  });

  const data = query.data;

  return (
    <div className="flex flex-col gap-6">
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold text-foreground">Billing & revenue</h1>
          <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
            Subscription mix, indicative MRR, seat and RFI usage, plus platform catalog pricing.
          </p>
        </div>
        <Link href="/platform#tenant-directory" className={buttonVariants({ variant: "outline" })}>
          Tenant directory
        </Link>
      </header>

      {query.isLoading ? (
        <PlatformBillingPageSkeleton />
      ) : query.isError ? (
        <p className="text-sm text-destructive">Could not load billing insights.</p>
      ) : data ? (
        <>
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card className="rounded-xl shadow-sm">
              <CardContent className="p-5">
                <p className="text-xs font-medium text-muted-foreground">Indicative MRR</p>
                <p className="mt-2 text-2xl font-semibold tabular-nums text-foreground">
                  {formatMoney(data.estimated_mrr, data.currency)}
                </p>
                <p className="mt-1 line-clamp-2 text-xs text-muted-foreground">
                  {data.estimated_mrr_note}
                </p>
              </CardContent>
            </Card>
            <Card className="rounded-xl shadow-sm">
              <CardContent className="p-5">
                <p className="text-xs font-medium text-muted-foreground">Tenants</p>
                <p className="mt-2 text-2xl font-semibold tabular-nums">{data.usage_totals.tenants}</p>
              </CardContent>
            </Card>
            <Card className="rounded-xl shadow-sm">
              <CardContent className="p-5">
                <p className="text-xs font-medium text-muted-foreground">Paid seats in use</p>
                <p className="mt-2 text-2xl font-semibold tabular-nums">
                  {data.usage_totals.total_seats_used}
                  <span className="text-base font-normal text-muted-foreground">
                    {" "}
                    / {data.usage_totals.total_seat_limit}
                  </span>
                </p>
              </CardContent>
            </Card>
            <Card className="rounded-xl shadow-sm">
              <CardContent className="p-5">
                <p className="text-xs font-medium text-muted-foreground">Stripe subscriptions</p>
                <p className="mt-2 text-2xl font-semibold tabular-nums">
                  {data.stripe.linked_subscriptions}
                </p>
                <p className="mt-1 text-xs text-muted-foreground">
                  {data.stripe.operational ? "Self-serve enabled" : "Stripe off or not configured"}
                </p>
              </CardContent>
            </Card>
          </div>

          <div className="grid gap-4 lg:grid-cols-2">
            <Card className="rounded-xl shadow-sm">
              <CardHeader className="border-b border-border pb-3">
                <CardTitle className="text-base font-medium">Revenue by plan tier</CardTitle>
              </CardHeader>
              <CardContent className="p-0">
                {data.revenue_by_tier.length === 0 ? (
                  <p className="p-4 text-sm text-muted-foreground">No billable tenants.</p>
                ) : (
                  <ul className="divide-y divide-border">
                    {data.revenue_by_tier.map((row) => (
                      <li
                        key={row.plan_tier}
                        className="flex items-center justify-between gap-3 px-4 py-3 text-sm"
                      >
                        <div>
                          <p className="font-medium text-foreground">{row.label}</p>
                          <p className="text-xs text-muted-foreground">
                            {row.tenant_count} tenant{row.tenant_count === 1 ? "" : "s"}
                          </p>
                        </div>
                        <span className="font-medium tabular-nums text-foreground">
                          {formatMoney(row.estimated_mrr, data.currency)}/mo
                        </span>
                      </li>
                    ))}
                  </ul>
                )}
              </CardContent>
            </Card>

            <Card className="rounded-xl shadow-sm">
              <CardHeader className="border-b border-border pb-3">
                <CardTitle className="text-base font-medium">Configured list prices</CardTitle>
              </CardHeader>
              <CardContent className="space-y-2 p-4 text-sm">
                {Object.entries(data.list_prices).map(([tier, price]) => (
                  <div key={tier} className="flex items-center justify-between gap-2">
                    <span className="capitalize text-muted-foreground">{tier}</span>
                    <span className="font-medium tabular-nums text-foreground">
                      {formatMoney(price, data.currency)}/mo
                    </span>
                  </div>
                ))}
              </CardContent>
            </Card>
          </div>

          <div className="space-y-3">
            <div className="flex items-center justify-between gap-3">
              <h2 className="text-base font-medium text-foreground">Tenant billing overview</h2>
              <p className="text-xs text-muted-foreground">
                {data.tenant_billing_rows.length} tenant
                {data.tenant_billing_rows.length === 1 ? "" : "s"}
              </p>
            </div>
            <PlatformDataTable
              rows={data.tenant_billing_rows}
              rowKey={(row) => row.id}
              emptyMessage="No tenants with billing data."
              columns={[
                {
                  id: "tenant",
                  header: "Tenant",
                  cell: (row) => (
                    <div>
                      <p className="font-medium text-foreground">
                        {row.slug ?? row.primary_domain ?? row.id.slice(0, 8)}
                      </p>
                      {row.primary_domain ? (
                        <p className="text-xs text-muted-foreground">{row.primary_domain}</p>
                      ) : null}
                      {row.has_billing_overrides ? (
                        <Badge variant="outline" className="mt-1 text-[10px]">
                          Custom limits
                        </Badge>
                      ) : null}
                    </div>
                  ),
                },
                {
                  id: "plan",
                  header: "Plan",
                  cell: (row) => <span className="capitalize">{row.plan_label}</span>,
                },
                {
                  id: "status",
                  header: "Status",
                  cell: (row) => (
                    <span
                      className={cn(
                        "text-xs font-medium capitalize",
                        row.subscription_status === "active"
                          ? "text-emerald-600 dark:text-emerald-400"
                          : "text-amber-600 dark:text-amber-400",
                      )}
                    >
                      {row.subscription_status.replace(/_/g, " ")}
                    </span>
                  ),
                },
                {
                  id: "seats",
                  header: "Seats",
                  headerClassName: "text-right",
                  className: "text-right tabular-nums",
                  cell: (row) => row.seat_limit,
                },
                {
                  id: "mrr",
                  header: "Est. MRR",
                  headerClassName: "text-right",
                  className: "text-right tabular-nums font-medium",
                  cell: (row) => formatMoney(row.estimated_mrr, data.currency),
                },
              ]}
            />
          </div>

          {data.enterprise_overrides.length > 0 ? (
            <Card className="rounded-xl shadow-sm">
              <CardHeader className="border-b border-border pb-3">
                <CardTitle className="text-base font-medium">Custom entitlements</CardTitle>
                <p className="text-xs font-normal text-muted-foreground">
                  Tenants with billing overrides
                </p>
              </CardHeader>
              <CardContent className="p-0">
                <ul className="divide-y divide-border">
                  {data.enterprise_overrides.map((row) => (
                    <li key={row.id} className="px-4 py-3 text-sm">
                      <p className="font-medium text-foreground">
                        {row.slug ?? row.primary_domain ?? row.id.slice(0, 8)}
                      </p>
                      <p className="text-xs text-muted-foreground">
                        {row.plan_tier} · {row.primary_domain ?? "—"}
                      </p>
                    </li>
                  ))}
                </ul>
              </CardContent>
            </Card>
          ) : null}

          {data.recent_billing_activity.length > 0 ? (
            <Card className="rounded-xl shadow-sm">
              <CardHeader className="border-b border-border pb-3">
                <CardTitle className="text-base font-medium">Recent billing activity</CardTitle>
              </CardHeader>
              <CardContent className="p-0">
                <ul className="max-h-64 divide-y divide-border overflow-y-auto">
                  {data.recent_billing_activity.map((row) => (
                    <li key={row.id} className="px-4 py-3 text-sm">
                      <p className="font-medium text-foreground">
                        {row.tenant_label}
                        <span className="mx-1.5 font-normal text-muted-foreground">·</span>
                        <span className="text-xs font-normal text-muted-foreground">
                          {row.actor_email ?? "System"}
                        </span>
                      </p>
                      <p className="mt-0.5 text-xs text-muted-foreground">
                        {Object.keys(row.changes ?? {}).join(", ") || "Updated"}
                      </p>
                    </li>
                  ))}
                </ul>
              </CardContent>
            </Card>
          ) : null}
        </>
      ) : null}

      <div className="space-y-3 border-t border-border pt-6">
        <h2 className="text-base font-medium text-foreground">Platform catalog</h2>
        <p className="text-sm text-muted-foreground">
          Edit list prices and included limits. Changes apply to new tenant provisioning and billing
          snapshots.
        </p>
        <PlatformBillingCatalogPanel />
      </div>

      {query.isFetching ? <RefreshingHint label="Refreshing insights" /> : null}
    </div>
  );
}
