"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useState } from "react";
import { ExternalLink, Shield } from "lucide-react";

import { PlatformTenantAccessPanel } from "@/components/platform/platform-tenant-access-panel";
import { PlatformTenantBackupsPanel } from "@/components/platform/platform-tenant-backups-panel";
import { TenantOperatorAccessCard } from "@/components/platform/tenant-operator-access-card";
import { TenantPlaybookManageSheet } from "@/components/platform/tenant-playbook-manage-sheet";
import { TenantBillingSheet } from "@/components/platform/tenant-billing-sheet";
import { TenantBrandingSheet } from "@/components/platform/tenant-branding-sheet";
import { TenantModulesSheet } from "@/components/platform/tenant-modules-sheet";
import { environmentBadgeClass } from "@/components/platform/tenant-environment-sheet";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DashboardContentSkeleton, PageHeaderSkeleton } from "@/components/ui/page-skeletons";
import { getErrorMessage } from "@/lib/api/error";
import {
  platformFetchTenant,
  platformFetchTenantAudit,
  platformPatchTenantSettings,
  platformUpdateTenantComingSoon,
  platformUpdateTenantMfa,
  type PlatformTenantRow,
  type PlatformTenantThemeTokens,
} from "@/lib/api/modules/platform-api";
import {
  formatModulesLabel,
  formatTenantModuleBadges,
  moduleBadgeClass,
  tenantUsesPlatformModuleDefault,
} from "@/lib/platform/tenant-directory-utils";
import { formatAuditChangeSummary } from "@/lib/platform/platform-audit-utils";
import { platformHasPermission, PLATFORM_PERMS } from "@/lib/platform/platform-permissions";
import { tenantLoginUrl } from "@/lib/tenant/resolve-tenant-domain";
import { usePlatformAuthStore } from "@/stores/platform-auth-store";
import { useNotificationStore } from "@/stores/notification-store";
import { cn } from "@/lib/utils";

type Tab = "overview" | "billing" | "modules" | "access" | "backups" | "activity";

type Props = {
  tenantId: string;
};

const tabs: { id: Tab; label: string }[] = [
  { id: "overview", label: "Overview" },
  { id: "billing", label: "Billing" },
  { id: "modules", label: "Modules" },
  { id: "access", label: "Access" },
  { id: "backups", label: "Backups" },
  { id: "activity", label: "Activity" },
];

export function PlatformTenantDetailPageClient({ tenantId }: Props) {
  const queryClient = useQueryClient();
  const notify = useNotificationStore((s) => s.push);
  const accessToken = usePlatformAuthStore((s) => s.accessToken);
  const platformUser = usePlatformAuthStore((s) => s.user);
  const isHydrated = usePlatformAuthStore((s) => s.isHydrated);
  const canManageTenants = platformHasPermission(platformUser, PLATFORM_PERMS.tenantsManage);
  const canManageBilling = platformHasPermission(platformUser, PLATFORM_PERMS.billingManage);
  const canManagePlaybooks = platformHasPermission(platformUser, PLATFORM_PERMS.playbooksManage);

  const [tab, setTab] = useState<Tab>("overview");
  const [billingOpen, setBillingOpen] = useState(false);
  const [modulesOpen, setModulesOpen] = useState(false);
  const [brandingOpen, setBrandingOpen] = useState(false);
  const [playbookManageOpen, setPlaybookManageOpen] = useState(false);
  const [billingDowngradeWarnings, setBillingDowngradeWarnings] = useState<string[]>([]);
  const [confirmPlanDowngrade, setConfirmPlanDowngrade] = useState(false);

  const tenantQuery = useQuery({
    queryKey: ["platform", "tenants", tenantId],
    queryFn: () => platformFetchTenant(tenantId),
    enabled: Boolean(isHydrated && accessToken),
  });

  const auditQuery = useQuery({
    queryKey: ["platform", "tenants", tenantId, "audit"],
    queryFn: () => platformFetchTenantAudit(tenantId),
    enabled: Boolean(isHydrated && accessToken && tab === "activity"),
  });

  const billingMutation = useMutation({
    mutationFn: (payload: Parameters<typeof platformPatchTenantSettings>[1]) =>
      platformPatchTenantSettings(tenantId, payload),
    onSuccess: (data) => {
      void queryClient.invalidateQueries({ queryKey: ["platform", "tenants"] });
      void queryClient.invalidateQueries({ queryKey: ["platform", "tenants", tenantId, "audit"] });
      void queryClient.invalidateQueries({ queryKey: ["platform", "dashboard"] });
      setBillingDowngradeWarnings([]);
      setConfirmPlanDowngrade(false);
      setBillingOpen(false);
      notify({
        level: "success",
        title: "Billing updated",
        message: `Plan ${data.plan_tier}, status ${data.subscription_status}.`,
      });
    },
    onError: (error) => {
      const axiosData =
        typeof error === "object" && error !== null && "response" in error
          ? (error as { response?: { data?: { errors?: Record<string, string[]> } } }).response?.data
              ?.errors
          : undefined;
      if (axiosData?.plan_tier?.length) {
        setBillingDowngradeWarnings(axiosData.plan_tier);
        return;
      }
      notify({ level: "error", title: "Could not update billing", message: getErrorMessage(error) });
    },
  });

  const modulesMutation = useMutation({
    mutationFn: (enabled_modules: string[] | null) =>
      platformPatchTenantSettings(tenantId, { enabled_modules }),
    onSuccess: (data) => {
      queryClient.setQueryData<PlatformTenantRow | undefined>(
        ["platform", "tenants", tenantId],
        (current) =>
          current
            ? {
                ...current,
                enabled_modules: data.enabled_modules ?? current.enabled_modules,
                effective_enabled_modules:
                  data.effective_enabled_modules ?? current.effective_enabled_modules,
              }
            : current,
      );
      void queryClient.invalidateQueries({ queryKey: ["platform", "tenants"] });
      void queryClient.invalidateQueries({ queryKey: ["platform", "tenants", tenantId] });
      void queryClient.invalidateQueries({ queryKey: ["platform", "tenants", tenantId, "audit"] });
      void queryClient.invalidateQueries({ queryKey: ["platform", "dashboard"] });
      void queryClient.invalidateQueries({ queryKey: ["platform", "tenant-modules", "catalog"] });
      setModulesOpen(false);
      notify({ level: "success", title: "Modules updated", message: "Workspace modules saved." });
    },
    onError: (error) =>
      notify({ level: "error", title: "Could not update modules", message: getErrorMessage(error) }),
  });

  const brandingMutation = useMutation({
    mutationFn: (payload: { themeTokens: PlatformTenantThemeTokens | null; mfaRequired: boolean }) =>
      platformUpdateTenantMfa(tenantId, {
        mfa_required: payload.mfaRequired,
        theme_tokens: payload.themeTokens,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["platform", "tenants"] });
      void queryClient.invalidateQueries({ queryKey: ["platform", "tenants", tenantId, "audit"] });
      void queryClient.invalidateQueries({ queryKey: ["platform", "dashboard"] });
      setBrandingOpen(false);
      notify({ level: "success", title: "Branding updated", message: "Tenant branding saved." });
    },
    onError: (error) =>
      notify({ level: "error", title: "Could not update branding", message: getErrorMessage(error) }),
  });

  const mfaMutation = useMutation({
    mutationFn: (mfaRequired: boolean) =>
      platformUpdateTenantMfa(tenantId, { mfa_required: mfaRequired }),
    onSuccess: (data) => {
      void queryClient.invalidateQueries({ queryKey: ["platform", "tenants"] });
      void queryClient.invalidateQueries({ queryKey: ["platform", "tenants", tenantId, "audit"] });
      void queryClient.invalidateQueries({ queryKey: ["platform", "dashboard"] });
      notify({
        level: "success",
        title: "MFA updated",
        message: data.mfa_required ? "MFA required for this tenant." : "MFA optional for this tenant.",
      });
    },
    onError: (error) =>
      notify({ level: "error", title: "Could not update MFA", message: getErrorMessage(error) }),
  });

  const comingSoonMutation = useMutation({
    mutationFn: (payload: {
      coming_soon_enabled: boolean;
      coming_soon_message?: string | null;
      coming_soon_contact?: string | null;
    }) => platformUpdateTenantComingSoon(tenantId, payload),
    onSuccess: (data) => {
      void queryClient.invalidateQueries({ queryKey: ["platform", "tenants"] });
      void queryClient.invalidateQueries({ queryKey: ["platform", "tenants", tenantId, "audit"] });
      notify({
        level: "success",
        title: "Coming Soon updated",
        message: data.coming_soon_enabled
          ? "Login shows Coming Soon for this environment."
          : "Login is open for this environment.",
      });
    },
    onError: (error) =>
      notify({
        level: "error",
        title: "Could not update Coming Soon",
        message: getErrorMessage(error),
      }),
  });

  if (!isHydrated || !accessToken) {
    return (
      <div className="space-y-6 p-6">
        <PageHeaderSkeleton actionCount={2} />
        <DashboardContentSkeleton />
      </div>
    );
  }

  const tenant = tenantQuery.data;

  if (tenantQuery.isLoading) {
    return (
      <div className="space-y-6 p-6">
        <PageHeaderSkeleton actionCount={2} />
        <DashboardContentSkeleton />
      </div>
    );
  }

  if (tenantQuery.isError || !tenant) {
    return (
      <div className="p-6">
        <p className="text-sm text-destructive">Could not load tenant.</p>
        <Link href="/platform#tenant-directory" className={buttonVariants({ variant: "outline", className: "mt-4" })}>
          Back to directory
        </Link>
      </div>
    );
  }

  const primaryDomain = tenant.domains[0];
  const loginUrl = primaryDomain ? tenantLoginUrl(primaryDomain) : null;
  const moduleBadges = formatTenantModuleBadges(tenant);

  return (
    <div className="flex flex-col gap-6">
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
            <Link href="/platform#tenant-directory" className="hover:text-foreground">
              Tenant directory
            </Link>
            <span aria-hidden>/</span>
            <span className="text-foreground">{tenant.slug ?? tenant.id}</span>
          </div>
          <h1 className="mt-2 text-2xl font-semibold text-foreground">
            {tenant.slug ?? primaryDomain ?? tenant.id}
          </h1>
          <div className="mt-2 flex flex-wrap items-center gap-2">
            <span
              className={cn(
                "rounded-full px-2 py-0.5 text-[11px] font-medium capitalize",
                environmentBadgeClass(tenant.environment),
              )}
            >
              {tenant.environment ?? "production"}
            </span>
            <Badge variant="secondary" className="capitalize">
              {tenant.plan_tier ?? "starter"}
            </Badge>
            <Badge variant="outline" className="capitalize">
              {tenant.subscription_status ?? "active"}
            </Badge>
            {tenant.access_mode && tenant.access_mode !== "full" ? (
              <Badge variant="outline" className="border-amber-300 text-amber-800">
                {tenant.access_mode.replace("_", " ")}
              </Badge>
            ) : null}
          </div>
        </div>
        <div className="flex flex-wrap gap-2">
          {loginUrl ? (
            <a href={loginUrl} target="_blank" rel="noopener noreferrer" className={buttonVariants({ variant: "outline" })}>
              <ExternalLink className="size-4" />
              Open tenant
            </a>
          ) : null}
          {canManagePlaybooks && tenantHasProjectOne(tenant) ? (
            <Button type="button" variant="outline" onClick={() => setPlaybookManageOpen(true)}>
              Manage rollout policy
            </Button>
          ) : null}
          {canManageTenants ? (
            <Button type="button" variant="outline" onClick={() => setModulesOpen(true)}>
              Edit modules
            </Button>
          ) : null}
          {canManageBilling ? (
            <Button type="button" onClick={() => setBillingOpen(true)}>
              Edit billing
            </Button>
          ) : null}
        </div>
      </header>

      <nav className="flex flex-wrap gap-1 border-b border-border pb-1">
        {tabs.map((item) => (
          <button
            key={item.id}
            type="button"
            onClick={() => setTab(item.id)}
            className={cn(
              "rounded-md px-3 py-2 text-sm font-medium transition-colors",
              tab === item.id
                ? "bg-muted text-foreground"
                : "text-muted-foreground hover:bg-muted/60 hover:text-foreground",
            )}
          >
            {item.label}
          </button>
        ))}
      </nav>

      {tab === "overview" ? (
        <div className="grid gap-4 md:grid-cols-2">
          <Card className="rounded-xl shadow-sm">
            <CardHeader>
              <CardTitle className="text-base font-medium">Organization</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
              <p>
                <span className="text-muted-foreground">Tenant ID</span>
                <br />
                <span className="font-mono text-xs">{tenant.id}</span>
              </p>
              <p>
                <span className="text-muted-foreground">Domains</span>
                <br />
                {tenant.domains.join(", ") || "—"}
              </p>
              {tenant.brand_domain ? (
                <p>
                  <span className="text-muted-foreground">Brand domain</span>
                  <br />
                  {tenant.brand_domain}
                </p>
              ) : null}
              <p>
                <span className="text-muted-foreground">Created</span>
                <br />
                {tenant.created_at ? new Date(tenant.created_at).toLocaleString() : "—"}
              </p>
            </CardContent>
          </Card>
          <Card className="rounded-xl shadow-sm">
            <CardHeader>
              <CardTitle className="text-base font-medium">Workspace modules</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3 text-sm">
              <div className="flex flex-wrap gap-1.5">
                {moduleBadges.map((badge) => (
                  <span
                    key={badge}
                    className={cn(
                      "inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium",
                      moduleBadgeClass(badge),
                    )}
                  >
                    {badge}
                  </span>
                ))}
              </div>
              <p className="text-muted-foreground">{formatModulesLabel(tenant)}</p>
              {tenantUsesPlatformModuleDefault(tenant) ? (
                <p className="text-xs text-muted-foreground">Using deployment default module catalog.</p>
              ) : null}
            </CardContent>
          </Card>
          <TenantOperatorAccessCard tenant={tenant} />

          <Card className="rounded-xl shadow-sm">
            <CardHeader>
              <CardTitle className="text-base font-medium">Security</CardTitle>
            </CardHeader>
            <CardContent>
              <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={mfaMutation.isPending}
                onClick={() => mfaMutation.mutate(!tenant.mfa_required)}
              >
                <Shield className="size-4" />
                MFA {tenant.mfa_required ? "on" : "off"} — toggle
              </Button>
            </CardContent>
          </Card>

          <Card className="rounded-xl shadow-sm">
            <CardHeader>
              <CardTitle className="text-base font-medium">Coming Soon</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              <p className="text-xs text-muted-foreground">
                Per environment. Turn on for staging while production stays open. No countdown —
                login is replaced with a short message.
              </p>
              <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={comingSoonMutation.isPending}
                onClick={() =>
                  comingSoonMutation.mutate({
                    coming_soon_enabled: !tenant.coming_soon_enabled,
                    coming_soon_message: tenant.coming_soon_message ?? null,
                    coming_soon_contact: tenant.coming_soon_contact ?? null,
                  })
                }
              >
                Coming Soon {tenant.coming_soon_enabled ? "on" : "off"} — toggle
              </Button>
              <div className="space-y-2">
                <label className="block text-xs font-medium text-muted-foreground" htmlFor="cs-message">
                  Message
                </label>
                <textarea
                  id="cs-message"
                  className="min-h-[72px] w-full rounded-md border border-border bg-background px-2 py-1.5 text-sm"
                  defaultValue={tenant.coming_soon_message ?? ""}
                  placeholder="This workspace is not open for sign-in yet…"
                  onBlur={(e) => {
                    const next = e.target.value.trim();
                    const current = (tenant.coming_soon_message ?? "").trim();
                    if (next === current) return;
                    comingSoonMutation.mutate({
                      coming_soon_enabled: Boolean(tenant.coming_soon_enabled),
                      coming_soon_message: next || null,
                      coming_soon_contact: tenant.coming_soon_contact ?? null,
                    });
                  }}
                />
              </div>
              <div className="space-y-2">
                <label className="block text-xs font-medium text-muted-foreground" htmlFor="cs-contact">
                  Contact (email or URL)
                </label>
                <input
                  id="cs-contact"
                  className="h-9 w-full rounded-md border border-border bg-background px-2 text-sm"
                  defaultValue={tenant.coming_soon_contact ?? ""}
                  placeholder="ops@example.com"
                  onBlur={(e) => {
                    const next = e.target.value.trim();
                    const current = (tenant.coming_soon_contact ?? "").trim();
                    if (next === current) return;
                    comingSoonMutation.mutate({
                      coming_soon_enabled: Boolean(tenant.coming_soon_enabled),
                      coming_soon_message: tenant.coming_soon_message ?? null,
                      coming_soon_contact: next || null,
                    });
                  }}
                />
              </div>
            </CardContent>
          </Card>

          {tenantHasProjectOne(tenant) ? (
            <Card className="rounded-xl shadow-sm">
              <CardHeader>
                <CardTitle className="text-base font-medium">Project-One (advanced)</CardTitle>
              </CardHeader>
              <CardContent className="space-y-2 text-sm text-muted-foreground">
                <p>
                  Playbook:{" "}
                  <span className="font-mono text-foreground">
                    {tenant.assigned_playbook_version ? `v${tenant.assigned_playbook_version}` : "Not assigned"}
                  </span>
                </p>
                <p>
                  Policy bundle:{" "}
                  <span className="font-mono text-foreground">
                    {tenant.assigned_rollout_policy_code ?? "Not assigned"}
                  </span>
                  {tenant.assigned_rollout_policy_name ? ` (${tenant.assigned_rollout_policy_name})` : ""}
                </p>
                {tenant.playbook_upgrade_available ? (
                  <p className="text-xs text-amber-700 dark:text-amber-300">
                    A newer published playbook version is available.
                  </p>
                ) : null}
                {canManagePlaybooks ? (
                  <Button type="button" size="sm" onClick={() => setPlaybookManageOpen(true)}>
                    Manage rollout policy
                  </Button>
                ) : null}
                <Link href="/platform/playbooks" className={buttonVariants({ variant: "outline", size: "sm" })}>
                  View playbook catalog
                </Link>
              </CardContent>
            </Card>
          ) : null}
        </div>
      ) : null}

      {tab === "billing" ? (
        <Card className="rounded-xl shadow-sm">
          <CardContent className="space-y-3 p-6 text-sm">
            <p>
              Plan <strong className="capitalize">{tenant.plan_tier}</strong> ·{" "}
              {tenant.seat_limit ?? tenant.effective_seat_limit} seats · status{" "}
              <strong className="capitalize">{tenant.subscription_status}</strong>
            </p>
            <Button type="button" onClick={() => setBillingOpen(true)}>
              Open billing editor
            </Button>
          </CardContent>
        </Card>
      ) : null}

      {tab === "modules" ? (
        <Card className="rounded-xl shadow-sm">
          <CardContent className="space-y-3 p-6 text-sm">
            <p className="text-muted-foreground">{formatModulesLabel(tenant)}</p>
            <div className="flex flex-wrap gap-2">
              {moduleBadges.map((badge) => (
                <span
                  key={badge}
                  className={cn(
                    "rounded-full px-2.5 py-0.5 text-xs font-medium",
                    moduleBadgeClass(badge),
                  )}
                >
                  {badge}
                </span>
              ))}
            </div>
            {tenantUsesPlatformModuleDefault(tenant) ? (
              <p className="text-xs text-muted-foreground">Using deployment default module catalog.</p>
            ) : null}
            <Button type="button" onClick={() => setModulesOpen(true)}>
              Edit workspace modules
            </Button>
          </CardContent>
        </Card>
      ) : null}

      {tab === "access" ? (
        <PlatformTenantAccessPanel tenant={tenant} onEditBranding={() => setBrandingOpen(true)} />
      ) : null}

      {tab === "backups" ? <PlatformTenantBackupsPanel tenant={tenant} /> : null}

      {tab === "activity" ? (
        <Card className="rounded-xl shadow-sm">
          <CardContent className="p-0">
            {auditQuery.isLoading ? (
              <p className="p-6 text-sm text-muted-foreground">Loading activity…</p>
            ) : (auditQuery.data ?? []).length === 0 ? (
              <p className="p-6 text-sm text-muted-foreground">No platform activity recorded yet.</p>
            ) : (
              <ul className="divide-y divide-border">
                {(auditQuery.data ?? []).map((entry) => (
                  <li key={entry.id} className="px-6 py-4 text-sm">
                    <div className="flex flex-wrap items-center gap-2">
                      <Badge variant="outline" className="font-normal">
                        {entry.event_label}
                      </Badge>
                      <span className="text-muted-foreground">
                        {entry.created_at ? new Date(entry.created_at).toLocaleString() : ""}
                      </span>
                    </div>
                    <p className="mt-2 text-foreground">{formatAuditChangeSummary(entry)}</p>
                    <p className="mt-1 text-xs text-muted-foreground">
                      {entry.actor_email ?? "System"}
                    </p>
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>
      ) : null}

      <TenantBillingSheet
        open={billingOpen}
        onOpenChange={setBillingOpen}
        tenant={tenant}
        isPending={billingMutation.isPending}
        downgradeWarnings={billingDowngradeWarnings}
        confirmDowngrade={confirmPlanDowngrade}
        onConfirmDowngradeChange={setConfirmPlanDowngrade}
        onClearDowngradeWarnings={() => setBillingDowngradeWarnings([])}
        onSave={(payload) => billingMutation.mutate(payload)}
      />

      <TenantModulesSheet
        open={modulesOpen}
        onOpenChange={setModulesOpen}
        tenant={tenant}
        isPending={modulesMutation.isPending}
        onSave={(payload) => modulesMutation.mutate(payload.enabled_modules)}
      />

      <TenantBrandingSheet
        open={brandingOpen}
        onOpenChange={setBrandingOpen}
        tenant={tenant}
        isPending={brandingMutation.isPending}
        onSave={(themeTokens) =>
          brandingMutation.mutate({
            themeTokens,
            mfaRequired: tenant.mfa_required,
          })
        }
        onUploaded={() => {
          void queryClient.invalidateQueries({ queryKey: ["platform", "tenants"] });
          void queryClient.invalidateQueries({ queryKey: ["platform", "tenants", tenantId] });
          void queryClient.invalidateQueries({ queryKey: ["platform", "dashboard"] });
        }}
      />

      <TenantPlaybookManageSheet
        open={playbookManageOpen}
        onOpenChange={setPlaybookManageOpen}
        tenant={tenant}
      />
    </div>
  );
}

function tenantHasProjectOne(tenant: PlatformTenantRow): boolean {
  return (tenant.effective_enabled_modules ?? []).includes("project_one");
}
