"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect } from "react";
import { CreditCard, Users } from "lucide-react";

import { BillingEstimateCard } from "@/components/billing/billing-estimate-card";
import { ProcurementEntitlementsCard } from "@/components/billing/procurement-entitlements-card";
import { TenantBillingMetricCard } from "@/components/billing/tenant-billing-metric-card";
import { PlanTierComparisonTable } from "@/components/billing/plan-tier-comparison-table";
import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { WorkspacePageHeader } from "@/components/layout/workspace-page-header";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { BillingPageSkeleton, KpiStripSkeleton } from "@/components/ui/page-skeletons";
import {
  createTenantBillingCheckoutSession,
  createTenantBillingPortalSession,
  fetchTenantBilling,
  fetchTenantBillingUsage,
} from "@/lib/api/modules/admin-billing-api";
import { getErrorMessage } from "@/lib/api/error";
import { permissions } from "@/lib/rbac/permissions";
import type { ProcurementPlanFeatures } from "@/modules/procurement-one/types";
import { cn } from "@/lib/utils";
import { useNotificationStore } from "@/stores/notification-store";

const planLabels: Record<string, string> = {
  starter: "Starter",
  professional: "Professional",
  enterprise: "Enterprise",
};

const statusVariant = (status: string): "default" | "secondary" | "destructive" | "outline" => {
  if (status === "active") return "default";
  if (status === "trial") return "secondary";
  if (status === "past_due" || status === "canceled") return "destructive";
  return "outline";
};

function utilizationTone(percent: number): "default" | "warning" | "danger" {
  if (percent >= 100) return "danger";
  if (percent >= 80) return "warning";
  return "default";
}

export function BillingPageClient() {
  const searchParams = useSearchParams();
  const notify = useNotificationStore((state) => state.push);
  const queryClient = useQueryClient();

  const query = useQuery({
    queryKey: ["admin", "billing"],
    queryFn: fetchTenantBilling,
  });

  const usageQuery = useQuery({
    queryKey: ["admin", "billing", "usage"],
    queryFn: fetchTenantBillingUsage,
    enabled: Boolean(query.data),
  });

  useEffect(() => {
    const checkout = searchParams.get("checkout");
    if (checkout === "success") {
      notify({
        level: "success",
        title: "Checkout complete",
        message: "Your subscription is updating. Refresh if plan tier has not changed yet.",
      });
      void queryClient.invalidateQueries({ queryKey: ["admin", "billing"] });
    } else if (checkout === "canceled") {
      notify({
        level: "info",
        title: "Checkout canceled",
        message: "No changes were made to your subscription.",
      });
    }
  }, [searchParams, notify, queryClient]);

  const checkoutMutation = useMutation({
    mutationFn: (planTier: string) => createTenantBillingCheckoutSession(planTier),
    onSuccess: (data) => {
      window.location.href = data.url;
    },
    onError: (error) =>
      notify({
        level: "error",
        title: "Checkout unavailable",
        message: getErrorMessage(error),
      }),
  });

  const portalMutation = useMutation({
    mutationFn: createTenantBillingPortalSession,
    onSuccess: (data) => {
      window.location.href = data.url;
    },
    onError: (error) =>
      notify({
        level: "error",
        title: "Billing portal unavailable",
        message: getErrorMessage(error),
      }),
  });

  const snapshot = query.data;
  const atSeatLimit = snapshot ? snapshot.seats_available <= 0 : false;
  const catalogTiers = snapshot?.plan_catalog?.tiers ?? [];
  const payments = snapshot?.payments;
  const selfServe = payments?.operational === true;
  const currency = snapshot?.currency ?? snapshot?.plan_catalog?.currency ?? "USD";
  const estimate = snapshot?.billing_estimate ?? snapshot?.overage ?? null;
  const procurementEntitlements = (
    snapshot?.plan_features.procurement_one ??
    snapshot?.entitlements?.procurement_one
  ) as ProcurementPlanFeatures | undefined;

  const seatUtilization =
    snapshot && snapshot.seat_limit > 0
      ? Math.round((snapshot.seat_used / snapshot.seat_limit) * 100)
      : 0;
  const rfiUsed = snapshot?.rfi_units?.used ?? 0;
  const rfiLimit = snapshot?.rfi_units?.limit ?? 0;
  const rfiUtilization = rfiLimit > 0 ? Math.round((rfiUsed / rfiLimit) * 100) : 0;

  return (
    <PermissionGate requiredPermissions={[permissions.billingView]}>
      <div className="space-y-6">
        <WorkspacePageHeader
          title="Billing & subscription"
          description={
            <>
              Plan tier, seats, and RFI capacity for your organization. Seat limits and plan changes
              are managed in the{" "}
              <strong className="font-medium text-foreground">Platform console</strong> (Tenants →
              Billing &amp; plan).
              {selfServe ? " Use online billing below to upgrade or manage payment details." : null}
            </>
          }
        />

        {query.isLoading ? (
          <BillingPageSkeleton />
        ) : query.isError ? (
          <p className="text-sm text-destructive">
            Could not load billing. Confirm you have billing:view access.
          </p>
        ) : snapshot ? (
          <>
            {snapshot.subscription?.access_mode === "grace" && snapshot.subscription.message ? (
              <div className="rounded-xl border border-amber-500/40 bg-amber-500/5 px-4 py-3 text-sm text-amber-900 dark:text-amber-200">
                {snapshot.subscription.message}
              </div>
            ) : null}
            {snapshot.subscription?.access_mode === "blocked" && snapshot.subscription.message ? (
              <div className="rounded-xl border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">
                {snapshot.subscription.message}
              </div>
            ) : null}

            <div className="grid gap-3 sm:grid-cols-2">
              <Link
                href="/users"
                className={cn(
                  "flex items-center gap-3 rounded-xl border border-border bg-card px-4 py-3 shadow-sm transition-colors",
                  "hover:border-primary/30 hover:bg-muted/30",
                )}
              >
                <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                  <Users className="h-4 w-4" aria-hidden />
                </div>
                <div>
                  <p className="text-sm font-medium text-foreground">Manage users</p>
                  <p className="text-xs text-muted-foreground">
                    {snapshot.seats_available} paid seat{snapshot.seats_available === 1 ? "" : "s"}{" "}
                    available
                  </p>
                </div>
              </Link>
              {selfServe ? (
                <button
                  type="button"
                  className={cn(
                    "flex items-center gap-3 rounded-xl border border-border bg-card px-4 py-3 text-left shadow-sm transition-colors",
                    "hover:border-primary/30 hover:bg-muted/30",
                  )}
                  disabled={!payments?.has_stripe_customer || portalMutation.isPending}
                  onClick={() => portalMutation.mutate()}
                >
                  <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                    <CreditCard className="h-4 w-4" aria-hidden />
                  </div>
                  <div>
                    <p className="text-sm font-medium text-foreground">Stripe billing portal</p>
                    <p className="text-xs text-muted-foreground">Invoices and payment method</p>
                  </div>
                </button>
              ) : null}
            </div>

            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
              <TenantBillingMetricCard
                label="Plan tier"
                value={snapshot.plan_label ?? planLabels[snapshot.plan_tier] ?? snapshot.plan_tier}
                hint={
                  snapshot.billing_interval === "annual"
                    ? `Annual prepay · ${snapshot.annual_discount_percent ?? 0}% discount`
                    : "Monthly billing"
                }
              />
              <TenantBillingMetricCard
                label="Subscription"
                value={
                  <span className="flex flex-wrap items-center gap-2">
                    <Badge variant={statusVariant(snapshot.subscription_status)} className="capitalize">
                      {snapshot.subscription_status.replace(/_/g, " ")}
                    </Badge>
                    {snapshot.subscription?.access_mode === "grace" ? (
                      <Badge variant="outline">Grace</Badge>
                    ) : null}
                    {snapshot.subscription?.access_mode === "blocked" ? (
                      <Badge variant="destructive">Suspended</Badge>
                    ) : null}
                  </span>
                }
                hint={
                  snapshot.subscription?.trial_ends_at
                    ? `Trial ends ${new Date(snapshot.subscription.trial_ends_at).toLocaleDateString()}`
                    : snapshot.subscription?.past_due_grace_ends_at
                      ? `Grace ends ${new Date(snapshot.subscription.past_due_grace_ends_at).toLocaleDateString()}`
                      : undefined
                }
              />
              <TenantBillingMetricCard
                label="Paid seats"
                value={
                  <>
                    {snapshot.seat_used}{" "}
                    <span className="text-base font-normal text-muted-foreground">
                      / {snapshot.seat_limit}
                    </span>
                  </>
                }
                hint={
                  snapshot.viewer_seats_used != null && snapshot.viewer_seats_used > 0
                    ? `${snapshot.viewer_seats_used} viewer${snapshot.viewer_seats_used === 1 ? "" : "s"} (free)`
                    : `${snapshot.seats_available} available`
                }
                utilizationPercent={seatUtilization}
                tone={utilizationTone(seatUtilization)}
              />
              <TenantBillingMetricCard
                label="RFI units"
                value={
                  <>
                    {rfiUsed}{" "}
                    <span className="text-base font-normal text-muted-foreground">/ {rfiLimit}</span>
                  </>
                }
                hint={
                  snapshot.rfi_units?.metering_active
                    ? `${snapshot.rfi_units.available} remaining after go-live`
                    : "Metering not active yet"
                }
                utilizationPercent={snapshot.rfi_units?.metering_active ? rfiUtilization : undefined}
                tone={utilizationTone(rfiUtilization)}
              />
            </div>

            {atSeatLimit ? (
              <div className="rounded-xl border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">
                Seat limit reached.{" "}
                <Link href="/users" className="font-medium underline-offset-2 hover:underline">
                  Deactivate a user
                </Link>{" "}
                before inviting or reactivating another.
              </div>
            ) : null}

            <div className="grid gap-4 lg:grid-cols-2">
              {estimate ? <BillingEstimateCard estimate={estimate} /> : null}

              {procurementEntitlements ? (
                <ProcurementEntitlementsCard features={procurementEntitlements} />
              ) : null}

              {!snapshot.plan_features.file_uploads ? (
                <div className="rounded-xl border border-primary/20 bg-primary/5 px-4 py-4 text-sm">
                  <p className="font-medium text-foreground">Upgrade for file uploads</p>
                  <p className="mt-1 text-muted-foreground">
                    E-Approval file fields require <strong className="font-medium">Professional</strong>{" "}
                    or <strong className="font-medium">Enterprise</strong>.
                    {selfServe ? " Use checkout below or contact support." : " Contact support to change your plan."}
                  </p>
                </div>
              ) : snapshot.has_enterprise_overrides ? (
                <div className="rounded-xl border border-primary/20 bg-primary/5 px-4 py-4 text-sm">
                  <p className="font-medium text-foreground">Custom enterprise entitlements</p>
                  <p className="mt-1 text-muted-foreground">
                    This organization has platform-defined billing overrides beyond the standard catalog.
                  </p>
                </div>
              ) : null}
            </div>

            {usageQuery.isLoading ? (
              <KpiStripSkeleton count={4} />
            ) : usageQuery.data ? (
              <EApprovalSectionCard
                title="Usage (last 30 days)"
                description="Operational activity across E-Approval, PROJECT-ONE, and Procurement-One."
                bodyClassName="p-0"
              >
                <div className="grid gap-0 sm:grid-cols-2 lg:grid-cols-4 sm:divide-x divide-border">
                  {[
                    {
                      label: "E-Approval forms",
                      value: usageQuery.data.modules.e_approval.forms_published,
                      sub: `${usageQuery.data.modules.e_approval.forms_total} total`,
                    },
                    {
                      label: "Submissions",
                      value: usageQuery.data.modules.e_approval.submissions_last_30d,
                      sub: `${usageQuery.data.modules.e_approval.submissions_total} all time`,
                    },
                    {
                      label: "Rollouts",
                      value: usageQuery.data.modules.project_one.rollouts_last_30d,
                      sub: `${usageQuery.data.modules.project_one.rollouts_total} total`,
                    },
                    {
                      label: "Active users",
                      value: usageQuery.data.seats.used,
                      sub: `${usageQuery.data.seats.total_users} accounts`,
                    },
                  ].map((item) => (
                    <div key={item.label} className="px-4 py-4">
                      <p className="text-xs font-medium text-muted-foreground">{item.label}</p>
                      <p className="mt-1 text-lg font-semibold tabular-nums text-foreground">{item.value}</p>
                      <p className="text-xs text-muted-foreground">{item.sub}</p>
                    </div>
                  ))}
                </div>
              </EApprovalSectionCard>
            ) : null}

            {catalogTiers.length > 0 ? (
              <EApprovalSectionCard
                title="Plan comparison"
                description={`List prices in ${currency}. Your current plan is highlighted.`}
                bodyClassName="p-0"
              >
                <PlanTierComparisonTable
                  tiers={catalogTiers}
                  currentTier={snapshot.plan_tier}
                  currency={currency}
                  className="border-0 rounded-none"
                />
              </EApprovalSectionCard>
            ) : null}

            {selfServe ? (
              <EApprovalSectionCard
                title="Online billing"
                description="Secure checkout and invoices are handled by Stripe."
              >
                <div className="flex flex-wrap gap-2">
                  {(payments?.upgrade_options ?? []).map((option) => (
                    <Button
                      key={option.plan_tier}
                      type="button"
                      disabled={checkoutMutation.isPending}
                      onClick={() => checkoutMutation.mutate(option.plan_tier)}
                    >
                      Upgrade to {option.label}
                    </Button>
                  ))}
                  {payments?.has_stripe_customer ? (
                    <Button
                      type="button"
                      variant="outline"
                      disabled={portalMutation.isPending}
                      onClick={() => portalMutation.mutate()}
                    >
                      Manage billing
                    </Button>
                  ) : null}
                </div>
              </EApprovalSectionCard>
            ) : null}
          </>
        ) : null}

        <div className="rounded-xl border border-border bg-muted/30 px-4 py-3 text-sm text-muted-foreground">
          To change plan tier or seat limits, contact{" "}
          {snapshot?.support_email ? (
            <a
              href={`mailto:${snapshot.support_email}`}
              className="font-medium text-primary underline-offset-2 hover:underline"
            >
              {snapshot.support_email}
            </a>
          ) : (
            "your TowerOS account team"
          )}
          . Platform operators update billing under{" "}
          <Link href="/platform" className="font-medium text-primary underline-offset-2 hover:underline">
            Platform → Tenants
          </Link>
          .
        </div>
      </div>
    </PermissionGate>
  );
}
