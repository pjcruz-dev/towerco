"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useCallback, useEffect, useState } from "react";

import { PlatformDashboardOverview } from "@/components/platform/platform-dashboard-overview";
import {
  TenantDirectoryTable,
  type TenantDirectoryFilters,
} from "@/components/platform/tenant-directory-table";
import { TenantPlaybookManageSheet } from "@/components/platform/tenant-playbook-manage-sheet";
import { TenantBillingSheet } from "@/components/platform/tenant-billing-sheet";
import { TenantBrandingSheet } from "@/components/platform/tenant-branding-sheet";
import { TenantModulesSheet } from "@/components/platform/tenant-modules-sheet";
import { TenantCredentialsPanel } from "@/components/platform/tenant-credentials-panel";
import { TenantDeleteSheet } from "@/components/platform/tenant-delete-sheet";
import { TenantEnvironmentSheet } from "@/components/platform/tenant-environment-sheet";
import { Button, buttonVariants } from "@/components/ui/button";
import { DashboardContentSkeleton, PageHeaderSkeleton } from "@/components/ui/page-skeletons";
import { useServerTableSort } from "@/hooks/use-server-table-sort";
import { getErrorMessage } from "@/lib/api/error";
import {
  platformCreateTenantEnvironment,
  platformDeleteTenant,
  platformListTenants,
  platformPatchTenantSettings,
  platformUpdateTenantMfa,
  type CreateTenantEnvironmentResponse,
  type PlatformTenantRow,
  type PlatformTenantThemeTokens,
} from "@/lib/api/modules/platform-api";
import { exportTenantsCsv } from "@/lib/platform/tenant-directory-utils";
import { platformHasPermission, PLATFORM_PERMS } from "@/lib/platform/platform-permissions";
import { usePlatformAuthStore } from "@/stores/platform-auth-store";
import { useNotificationStore } from "@/stores/notification-store";

const TENANT_DIRECTORY_DEFAULT_SORT = "created_at:desc";
const TENANT_DIRECTORY_COLUMN_TO_API: Record<string, string> = {
  created: "created_at",
  plan_usage: "plan_tier",
};
const TENANT_DIRECTORY_API_TO_COLUMN: Record<string, string> = {
  created_at: "created",
  plan_tier: "plan_usage",
};

function tenantGroupEnvironments(rows: PlatformTenantRow[], source: PlatformTenantRow): string[] {
  const rootId = source.parent_tenant_id ?? source.id;

  return rows
    .filter((row) => row.id === rootId || row.parent_tenant_id === rootId)
    .map((row) => row.environment ?? "production");
}

function tenantChildRows(rows: PlatformTenantRow[], target: PlatformTenantRow): PlatformTenantRow[] {
  return rows.filter((row) => row.parent_tenant_id === target.id);
}

export function PlatformHomePageClient() {
  const router = useRouter();
  const queryClient = useQueryClient();
  const notify = useNotificationStore((state) => state.push);
  const accessToken = usePlatformAuthStore((state) => state.accessToken);
  const platformUser = usePlatformAuthStore((state) => state.user);
  const isHydrated = usePlatformAuthStore((state) => state.isHydrated);
  const canManagePlaybooks = platformHasPermission(platformUser, PLATFORM_PERMS.playbooksManage);

  const [searchInput, setSearchInput] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");
  const [page, setPage] = useState(1);
  const [filters, setFilters] = useState<TenantDirectoryFilters>({});
  const perPage = 25;
  const { sort, sorting, onSortingChange, manualSorting } = useServerTableSort({
    defaultSort: TENANT_DIRECTORY_DEFAULT_SORT,
    columnIdToApiField: TENANT_DIRECTORY_COLUMN_TO_API,
    apiFieldToColumnId: TENANT_DIRECTORY_API_TO_COLUMN,
    sortableColumnIds: ["created", "plan_usage"],
  });

  useEffect(() => {
    const handle = window.setTimeout(() => {
      setDebouncedSearch(searchInput.trim());
      setPage(1);
    }, 320);
    return () => window.clearTimeout(handle);
  }, [searchInput]);

  useEffect(() => {
    setPage(1);
  }, [sort]);

  useEffect(() => {
    if (!isHydrated) return;
    if (!accessToken) {
      router.replace("/platform/login");
    }
  }, [accessToken, isHydrated, router]);

  const tenantsQuery = useQuery({
    queryKey: ["platform", "tenants", debouncedSearch, filters, page, perPage, sort],
    queryFn: () =>
      platformListTenants({
        search: debouncedSearch || undefined,
        page,
        per_page: perPage,
        sort,
        environment: filters.environment,
        plan_tier: filters.plan_tier,
        subscription_status: filters.subscription_status,
        modules: filters.modules,
        access_mode: filters.access_mode,
      }),
    enabled: Boolean(isHydrated && accessToken),
    retry: 1,
  });

  const [deleteTarget, setDeleteTarget] = useState<{
    tenantId: string;
    tenantLabel: string;
    domains: string[];
    childTenants: Array<{ id: string; environment: string | null; domain: string | null }>;
  } | null>(null);
  const [deleteConfirmation, setDeleteConfirmation] = useState("");
  const [deleteCascade, setDeleteCascade] = useState(false);
  const [environmentTarget, setEnvironmentTarget] = useState<PlatformTenantRow | null>(null);
  const [environmentCreated, setEnvironmentCreated] = useState<CreateTenantEnvironmentResponse | null>(null);
  const [brandingTarget, setBrandingTarget] = useState<PlatformTenantRow | null>(null);
  const [billingTarget, setBillingTarget] = useState<PlatformTenantRow | null>(null);
  const [modulesTarget, setModulesTarget] = useState<PlatformTenantRow | null>(null);
  const [playbookTarget, setPlaybookTarget] = useState<PlatformTenantRow | null>(null);
  const [billingDowngradeWarnings, setBillingDowngradeWarnings] = useState<string[]>([]);
  const [confirmPlanDowngrade, setConfirmPlanDowngrade] = useState(false);

  const modulesMutation = useMutation({
    mutationFn: ({
      id,
      enabled_modules,
    }: {
      id: string;
      enabled_modules: string[] | null;
    }) => platformPatchTenantSettings(id, { enabled_modules }),
    onSuccess: (data) => {
      void queryClient.invalidateQueries({ queryKey: ["platform", "tenants"] });
      void queryClient.invalidateQueries({ queryKey: ["platform", "dashboard"] });
      setModulesTarget(null);
      notify({
        level: "success",
        title: "Workspace modules updated",
        message: data.effective_enabled_modules?.join(", ") ?? "Tenant module settings saved.",
      });
    },
    onError: (error) =>
      notify({
        level: "error",
        title: "Could not update modules",
        message: getErrorMessage(error),
      }),
  });

  const billingMutation = useMutation({
    mutationFn: ({
      id,
      plan_tier,
      subscription_status,
      trial_ends_at,
      past_due_grace_ends_at,
      seat_limit,
      billing_meter_starts_at,
      billing_interval,
      confirm_plan_downgrade,
      billing_overrides,
    }: {
      id: string;
      plan_tier: "starter" | "professional" | "enterprise";
      subscription_status: "trial" | "active" | "past_due" | "canceled";
      trial_ends_at?: string | null;
      past_due_grace_ends_at?: string | null;
      seat_limit: number;
      billing_meter_starts_at?: string | null;
      billing_interval?: "monthly" | "annual";
      confirm_plan_downgrade?: boolean;
      billing_overrides?: import("@/lib/api/modules/platform-api").PlatformTenantSettingsPatch["billing_overrides"];
    }) =>
      platformPatchTenantSettings(id, {
        plan_tier,
        subscription_status,
        trial_ends_at,
        past_due_grace_ends_at,
        seat_limit,
        billing_meter_starts_at,
        billing_interval,
        confirm_plan_downgrade,
        billing_overrides,
      }),
    onSuccess: (data) => {
      void queryClient.invalidateQueries({ queryKey: ["platform", "tenants"] });
      void queryClient.invalidateQueries({ queryKey: ["platform", "dashboard"] });
      if (billingTarget) {
        void queryClient.invalidateQueries({
          queryKey: ["platform", "tenants", billingTarget.id, "audit"],
        });
      }
      setBillingDowngradeWarnings([]);
      setConfirmPlanDowngrade(false);
      setBillingTarget(null);
      const warningNote =
        data.warnings && data.warnings.length > 0
          ? ` Note: ${data.warnings[0]}`
          : "";
      notify({
        level: "success",
        title: "Billing updated",
        message: `Plan ${data.plan_tier}, ${data.seat_limit} seats, status ${data.subscription_status}.${warningNote}`,
      });
    },
    onError: (error) => {
      const axiosData =
        typeof error === "object" && error !== null && "response" in error
          ? (error as { response?: { data?: { errors?: Record<string, string[]> } } }).response?.data
              ?.errors
          : undefined;
      const planErrors = axiosData?.plan_tier;
      if (planErrors && planErrors.length > 0) {
        setBillingDowngradeWarnings(planErrors);
        notify({
          level: "warning",
          title: "Confirm plan downgrade",
          message: "Review warnings in the billing panel and check the confirmation box.",
        });
        return;
      }
      notify({
        level: "error",
        title: "Could not update billing",
        message: getErrorMessage(error),
      });
    },
  });

  const brandingMutation = useMutation({
    mutationFn: ({
      id,
      mfaRequired,
      themeTokens,
    }: {
      id: string;
      mfaRequired: boolean;
      themeTokens: PlatformTenantThemeTokens | null;
    }) => platformUpdateTenantMfa(id, { mfa_required: mfaRequired, theme_tokens: themeTokens }),
    onSuccess: (data) => {
      void queryClient.invalidateQueries({ queryKey: ["platform", "tenants"] });
      void queryClient.invalidateQueries({ queryKey: ["platform", "dashboard"] });
      setBrandingTarget(null);
      notify({
        level: "success",
        title: "Branding updated",
        message: data.theme_tokens?.logo_url
          ? "Tenant logo will appear in the sidebar and login page."
          : "Tenant branding cleared.",
      });
    },
    onError: (error) =>
      notify({
        level: "error",
        title: "Could not update branding",
        message: getErrorMessage(error),
      }),
  });

  const mfaMutation = useMutation({
    mutationFn: ({ id, mfaRequired }: { id: string; mfaRequired: boolean }) =>
      platformUpdateTenantMfa(id, { mfa_required: mfaRequired }),
    onSuccess: (data) => {
      void queryClient.invalidateQueries({ queryKey: ["platform", "tenants"] });
      notify({
        level: "success",
        title: "MFA policy updated",
        message: data.mfa_required
          ? "Users in this tenant must complete MFA when policy is active."
          : "MFA is bypassed for this tenant (global TENANT_MFA_REQUIRED can still disable MFA entirely).",
      });
    },
    onError: (error) =>
      notify({
        level: "error",
        title: "Could not update MFA",
        message: getErrorMessage(error),
      }),
  });

  const deleteTenantMutation = useMutation({
    mutationFn: ({
      tenantId,
      confirmation,
      cascade,
    }: {
      tenantId: string;
      confirmation: string;
      cascade?: boolean;
    }) => platformDeleteTenant(tenantId, { confirmation, cascade }),
    onSuccess: (data) => {
      void queryClient.invalidateQueries({ queryKey: ["platform", "tenants"] });
      void queryClient.invalidateQueries({ queryKey: ["platform", "dashboard"] });
      const childCount = data.children_deleted?.length ?? 0;
      notify({
        level: "success",
        title: "Tenant deleted",
        message:
          childCount > 0
            ? `Removed ${data.tenant_id} and ${childCount} linked environment tenant(s).`
            : `Removed ${data.tenant_id} and dropped tenant database.`,
      });
      setDeleteTarget(null);
      setDeleteConfirmation("");
      setDeleteCascade(false);
    },
    onError: (error) =>
      notify({
        level: "error",
        title: "Could not delete tenant",
        message: getErrorMessage(error),
      }),
  });

  const environmentMutation = useMutation({
    mutationFn: ({
      tenantId,
      environment,
      domain,
      enabled_modules,
      admin_password,
    }: {
      tenantId: string;
      environment: "local" | "test" | "staging" | "production";
      domain?: string;
      enabled_modules?: string[] | null;
      admin_password?: string;
    }) =>
      platformCreateTenantEnvironment(tenantId, {
        environment,
        domain,
        migrate: true,
        seed: false,
        enabled_modules,
        admin_password,
      }),
    onSuccess: (data) => {
      void queryClient.invalidateQueries({ queryKey: ["platform", "tenants"] });
      void queryClient.invalidateQueries({ queryKey: ["platform", "dashboard"] });
      setEnvironmentTarget(null);
      setEnvironmentCreated(data);
      notify({
        level: "success",
        title: "Environment tenant created",
        message: `${data.environment ?? "Tenant"} ready at ${data.domain ?? data.tenant_id}.`,
      });
    },
    onError: (error) =>
      notify({
        level: "error",
        title: "Could not create environment tenant",
        message: getErrorMessage(error),
      }),
  });

  const handleExportCsv = useCallback(async () => {
    try {
      const response = await platformListTenants({
        search: debouncedSearch || undefined,
        page: 1,
        per_page: 500,
        sort,
        environment: filters.environment,
        plan_tier: filters.plan_tier,
        subscription_status: filters.subscription_status,
        modules: filters.modules,
      });
      exportTenantsCsv(response.items);
      notify({
        level: "success",
        title: "Export started",
        message: `Exported ${response.items.length} tenant row(s) matching current filters.`,
      });
    } catch (error) {
      notify({
        level: "error",
        title: "Export failed",
        message: getErrorMessage(error),
      });
    }
  }, [debouncedSearch, filters, notify, sort]);

  useEffect(() => {
    if (!tenantsQuery.isError) return;
    notify({
      level: "error",
      title: "Could not load tenants",
      message: getErrorMessage(tenantsQuery.error),
    });
  }, [notify, tenantsQuery.error, tenantsQuery.isError]);

  const copyTenantId = useCallback(
    async (id: string) => {
      try {
        await navigator.clipboard.writeText(id);
        notify({
          level: "success",
          title: "Copied",
          message: "Tenant ID copied to clipboard.",
        });
      } catch {
        notify({
          level: "error",
          title: "Copy failed",
          message: "Clipboard is not available in this browser context.",
        });
      }
    },
    [notify],
  );

  if (!isHydrated || !accessToken) {
    return (
      <div className="flex flex-col gap-8">
        <PageHeaderSkeleton actionCount={2} />
        <DashboardContentSkeleton />
      </div>
    );
  }

  const rows = tenantsQuery.data?.items ?? [];
  const tenantMeta = tenantsQuery.data?.meta;

  return (
    <div className="flex flex-col gap-8">
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight text-foreground">Superadmin dashboard</h1>
          <p className="mt-1 max-w-xl text-sm text-muted-foreground">
            Tenant lifecycle overview and directory — provision organizations, control workspace modules, and open tenant sign-in.
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Link href="/platform/tenants/create" className={buttonVariants({ size: "default" })}>
            Create tenant
          </Link>
          <Button
            type="button"
            variant="outline"
            disabled={(tenantMeta?.total ?? 0) === 0 || tenantsQuery.isLoading}
            onClick={() => void handleExportCsv()}
          >
            Export CSV
          </Button>
        </div>
      </header>

      <PlatformDashboardOverview
        enabled={Boolean(isHydrated && accessToken)}
        beforeRecentActivity={
          <section id="tenant-directory" className="space-y-4 scroll-mt-24">
            <div>
              <h2 className="text-base font-medium text-foreground">Tenant directory</h2>
              <p className="mt-1 text-sm text-muted-foreground">
                Search, filter by modules and plan, manage MFA, and open tenant workspaces.
              </p>
            </div>

            <TenantDirectoryTable
              rows={rows}
              total={tenantMeta?.total ?? 0}
              page={tenantMeta?.page ?? page}
              lastPage={tenantMeta?.last_page ?? 1}
              perPage={tenantMeta?.per_page ?? perPage}
              isLoading={tenantsQuery.isLoading}
              searchEmpty={Boolean(
                debouncedSearch ||
                  filters.environment ||
                  filters.modules ||
                  filters.plan_tier ||
                  filters.subscription_status ||
                  filters.access_mode,
              )}
              filters={filters}
              onFiltersChange={(next) => {
                setFilters(next);
                setPage(1);
              }}
              onPageChange={setPage}
              sorting={sorting}
              onSortingChange={onSortingChange}
              manualSorting={manualSorting}
              mfaPendingTenantId={
                mfaMutation.isPending ? mfaMutation.variables?.id : undefined
              }
              searchValue={searchInput}
              onSearchChange={setSearchInput}
              onCopyId={(id) => void copyTenantId(id)}
              onBranding={setBrandingTarget}
              onBilling={setBillingTarget}
              onModules={setModulesTarget}
              onPlaybook={
                canManagePlaybooks
                  ? (row) => {
                      if ((row.effective_enabled_modules ?? []).includes("project_one")) {
                        setPlaybookTarget(row);
                      }
                    }
                  : undefined
              }
              onAddEnv={setEnvironmentTarget}
              onMfaToggle={(row) => mfaMutation.mutate({ id: row.id, mfaRequired: !row.mfa_required })}
              onDelete={(row) => {
                const childTenants = tenantChildRows(rows, row).map((child) => ({
                  id: child.id,
                  environment: child.environment ?? null,
                  domain: child.domains[0] ?? null,
                }));
                setDeleteConfirmation("");
                setDeleteCascade(childTenants.length > 0);
                setDeleteTarget({
                  tenantId: row.id,
                  tenantLabel: row.domains[0] ?? row.slug ?? row.id,
                  domains: row.domains,
                  childTenants,
                });
              }}
            />
          </section>
        }
      />

      {environmentTarget ? (
        <TenantEnvironmentSheet
          key={environmentTarget.id}
          open
          onOpenChange={(open) => {
            if (!open) {
              setEnvironmentTarget(null);
            }
          }}
          sourceTenant={environmentTarget}
          existingEnvironments={tenantGroupEnvironments(rows, environmentTarget)}
          isPending={environmentMutation.isPending}
          onConfirm={({ environment, domain, enabled_modules, admin_password }) => {
            environmentMutation.mutate({
              tenantId: environmentTarget.id,
              environment,
              domain,
              enabled_modules,
              admin_password,
            });
          }}
        />
      ) : null}

      {environmentCreated ? (
        <div className="rounded-xl border border-border bg-card p-6 shadow-sm">
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h2 className="text-lg font-semibold text-foreground">Environment tenant ready</h2>
              <p className="mt-1 text-sm text-muted-foreground">
                {(environmentCreated.environment ?? "Tenant").toUpperCase()} tenant{" "}
                <span className="font-mono text-foreground">{environmentCreated.domain ?? environmentCreated.tenant_id}</span>
              </p>
            </div>
            <Button type="button" variant="outline" size="sm" onClick={() => setEnvironmentCreated(null)}>
              Dismiss
            </Button>
          </div>
          {environmentCreated.initial_admin ? (
            <div className="mt-4">
              <TenantCredentialsPanel
                initialAdmin={environmentCreated.initial_admin}
                loginDomain={environmentCreated.domain}
                title={`${environmentCreated.environment ?? "Tenant"} administrator`}
              />
            </div>
          ) : null}
        </div>
      ) : null}

      {modulesTarget ? (
        <TenantModulesSheet
          key={modulesTarget.id}
          open
          onOpenChange={(open) => {
            if (!open) {
              setModulesTarget(null);
            }
          }}
          tenant={modulesTarget}
          isPending={modulesMutation.isPending}
          onSave={(payload) => modulesMutation.mutate({ id: modulesTarget.id, ...payload })}
        />
      ) : null}

      {billingTarget ? (
        <TenantBillingSheet
          key={billingTarget.id}
          open
          onOpenChange={(open) => {
            if (!open) {
              setBillingTarget(null);
            }
          }}
          tenant={billingTarget}
          isPending={billingMutation.isPending}
          downgradeWarnings={billingDowngradeWarnings}
          confirmDowngrade={confirmPlanDowngrade}
          onConfirmDowngradeChange={setConfirmPlanDowngrade}
          onClearDowngradeWarnings={() => setBillingDowngradeWarnings([])}
          onSave={(payload) => billingMutation.mutate({ id: billingTarget.id, ...payload })}
        />
      ) : null}

      {playbookTarget ? (
        <TenantPlaybookManageSheet
          key={playbookTarget.id}
          open
          onOpenChange={(open) => {
            if (!open) {
              setPlaybookTarget(null);
            }
          }}
          tenant={playbookTarget}
        />
      ) : null}

      {brandingTarget ? (
        <TenantBrandingSheet
          key={brandingTarget.id}
          open
          onOpenChange={(open) => {
            if (!open) {
              setBrandingTarget(null);
            }
          }}
          tenant={brandingTarget}
          isPending={brandingMutation.isPending}
          onSave={(themeTokens) => {
            brandingMutation.mutate({
              id: brandingTarget.id,
              mfaRequired: brandingTarget.mfa_required,
              themeTokens,
            });
          }}
          onUploaded={(themeTokens) => {
            setBrandingTarget((current) => (current ? { ...current, theme_tokens: themeTokens } : current));
            void queryClient.invalidateQueries({ queryKey: ["platform", "tenants"] });
            void queryClient.invalidateQueries({ queryKey: ["platform", "dashboard"] });
          }}
        />
      ) : null}

      <TenantDeleteSheet
        open={deleteTarget !== null}
        onOpenChange={(open) => {
          if (!open) {
            setDeleteTarget(null);
            setDeleteConfirmation("");
            setDeleteCascade(false);
          }
        }}
        tenantId={deleteTarget?.tenantId ?? ""}
        tenantLabel={deleteTarget?.tenantLabel ?? "Tenant"}
        domains={deleteTarget?.domains ?? []}
        childTenants={deleteTarget?.childTenants ?? []}
        confirmation={deleteConfirmation}
        cascadeDelete={deleteCascade}
        onConfirmationChange={setDeleteConfirmation}
        onCascadeDeleteChange={setDeleteCascade}
        isPending={deleteTenantMutation.isPending}
        onConfirm={() => {
          if (!deleteTarget) {
            return;
          }
          deleteTenantMutation.mutate({
            tenantId: deleteTarget.tenantId,
            confirmation: deleteConfirmation.trim(),
            cascade: deleteTarget.childTenants.length > 0 ? deleteCascade : undefined,
          });
        }}
      />
    </div>
  );
}
