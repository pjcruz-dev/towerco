"use client";

import { Search } from "lucide-react";
import Link from "next/link";
import { useMemo } from "react";
import type { OnChangeFn, SortingState } from "@tanstack/react-table";

import { createTenantDirectoryTableColumns } from "@/components/platform/tenant-directory-table-columns";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import type { PlatformTenantListParams, PlatformTenantRow } from "@/lib/api/modules/platform-api";

export type TenantDirectoryFilters = Pick<
  PlatformTenantListParams,
  "environment" | "plan_tier" | "subscription_status" | "modules" | "access_mode"
>;

type Props = {
  rows: PlatformTenantRow[];
  total: number;
  page: number;
  lastPage: number;
  perPage: number;
  isLoading: boolean;
  searchEmpty: boolean;
  mfaPendingTenantId?: string;
  filters: TenantDirectoryFilters;
  onFiltersChange: (filters: TenantDirectoryFilters) => void;
  onPageChange: (page: number) => void;
  onSearchChange: (value: string) => void;
  searchValue: string;
  onCopyId: (id: string) => void;
  onBranding: (row: PlatformTenantRow) => void;
  onBilling: (row: PlatformTenantRow) => void;
  onModules: (row: PlatformTenantRow) => void;
  onPlaybook?: (row: PlatformTenantRow) => void;
  onAddEnv: (row: PlatformTenantRow) => void;
  onDelete: (row: PlatformTenantRow) => void;
  onMfaToggle: (row: PlatformTenantRow) => void;
  sorting?: SortingState;
  onSortingChange?: OnChangeFn<SortingState>;
  manualSorting?: boolean;
};

export function TenantDirectoryTable({
  rows,
  total,
  page,
  lastPage,
  perPage,
  isLoading,
  searchEmpty,
  mfaPendingTenantId,
  filters,
  onFiltersChange,
  onPageChange,
  onSearchChange,
  searchValue,
  onCopyId,
  onBranding,
  onBilling,
  onModules,
  onPlaybook,
  onAddEnv,
  onDelete,
  onMfaToggle,
  sorting,
  onSortingChange,
  manualSorting = true,
}: Props) {
  const mfaOnCount = rows.filter((row) => row.mfa_required).length;
  const localCount = rows.filter((row) => row.environment === "local").length;
  const blockedCount = rows.filter((row) => row.access_mode === "blocked").length;
  const readOnlyCount = rows.filter((row) => row.access_mode === "read_only").length;
  const upgradeCount = rows.filter((row) => row.playbook_upgrade_available).length;
  const eaOnlyCount = rows.filter(
    (row) =>
      (row.effective_enabled_modules ?? []).includes("e_approval") &&
      !(row.effective_enabled_modules ?? []).includes("project_one"),
  ).length;

  const columns = useMemo(
    () =>
      createTenantDirectoryTableColumns({
        mfaPendingTenantId,
        onCopyId,
        onBranding,
        onBilling,
        onModules,
        onPlaybook,
        onAddEnv,
        onDelete,
        onMfaToggle,
      }),
    [
      mfaPendingTenantId,
      onCopyId,
      onBranding,
      onBilling,
      onModules,
      onPlaybook,
      onAddEnv,
      onDelete,
      onMfaToggle,
    ],
  );

  return (
    <div className="space-y-4">
      <Card size="sm" className="py-0">
        <CardContent className="flex flex-col gap-3 py-3">
          <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
            <div className="relative min-w-0 flex-1">
              <Search
                className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                aria-hidden
              />
              <Input
                className="h-9 pl-9"
                placeholder="Search UUID, slug, domain, or brand…"
                value={searchValue}
                onChange={(event) => onSearchChange(event.target.value)}
                autoComplete="off"
                aria-label="Search tenants"
              />
            </div>
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
              <Select
                className="h-9"
                value={filters.environment ?? ""}
                onChange={(event) =>
                  onFiltersChange({ ...filters, environment: event.target.value || undefined })
                }
                aria-label="Filter by environment"
              >
                <option value="">All environments</option>
                <option value="production">Production</option>
                <option value="staging">Staging</option>
                <option value="test">Test</option>
                <option value="local">Local</option>
              </Select>
              <Select
                className="h-9"
                value={filters.modules ?? ""}
                onChange={(event) =>
                  onFiltersChange({
                    ...filters,
                    modules: (event.target.value || undefined) as TenantDirectoryFilters["modules"],
                  })
                }
                aria-label="Filter by modules"
              >
                <option value="">All modules</option>
                <option value="e_approval_only">E-Approval only</option>
                <option value="project_one">Includes Project-One</option>
                <option value="ticketing">Includes Ticketing</option>
              </Select>
              <Select
                className="h-9"
                value={filters.plan_tier ?? ""}
                onChange={(event) =>
                  onFiltersChange({ ...filters, plan_tier: event.target.value || undefined })
                }
                aria-label="Filter by plan"
              >
                <option value="">All plans</option>
                <option value="starter">Starter</option>
                <option value="professional">Professional</option>
                <option value="enterprise">Enterprise</option>
              </Select>
              <Select
                className="h-9"
                value={filters.subscription_status ?? ""}
                onChange={(event) =>
                  onFiltersChange({
                    ...filters,
                    subscription_status: event.target.value || undefined,
                  })
                }
                aria-label="Filter by subscription status"
              >
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="trial">Trial</option>
                <option value="past_due">Past due</option>
                <option value="canceled">Canceled</option>
              </Select>
              <Select
                className="h-9"
                value={filters.access_mode ?? ""}
                onChange={(event) =>
                  onFiltersChange({
                    ...filters,
                    access_mode: (event.target.value || undefined) as TenantDirectoryFilters["access_mode"],
                  })
                }
                aria-label="Filter by access mode"
              >
                <option value="">All access</option>
                <option value="grace">Grace period</option>
                <option value="read_only">Read-only</option>
                <option value="blocked">Blocked</option>
              </Select>
            </div>
          </div>
          <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
            <Badge variant="secondary">
              {total} tenant{total === 1 ? "" : "s"}
            </Badge>
            {rows.length > 0 ? (
              <>
                <span className="hidden sm:inline">·</span>
                <span>{mfaOnCount} MFA on (page)</span>
                <span className="hidden sm:inline">·</span>
                <span>{eaOnlyCount} E-Approval only (page)</span>
                {blockedCount > 0 ? (
                  <>
                    <span className="hidden sm:inline">·</span>
                    <span>{blockedCount} blocked (page)</span>
                  </>
                ) : null}
                {readOnlyCount > 0 ? (
                  <>
                    <span className="hidden sm:inline">·</span>
                    <span>{readOnlyCount} read-only (page)</span>
                  </>
                ) : null}
                {upgradeCount > 0 ? (
                  <>
                    <span className="hidden md:inline">·</span>
                    <span className="hidden md:inline">{upgradeCount} playbook upgrade (page)</span>
                  </>
                ) : null}
                <span className="hidden md:inline">·</span>
                <span className="hidden md:inline">{localCount} local (page)</span>
              </>
            ) : null}
          </div>
        </CardContent>
      </Card>

      <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
        <RegistryDataTableView
          columns={columns}
          data={rows}
          getRowId={(row) => row.id}
          isLoading={isLoading}
          isEmpty={!isLoading && rows.length === 0}
          loadingRowCount={8}
          scrollClassName="max-h-none"
          sorting={sorting}
          onSortingChange={onSortingChange}
          manualSorting={manualSorting}
          getRowClassName={(row) => (row.original.parent_tenant_id ? "bg-muted/15" : undefined)}
          emptyContent={
            <div className="mx-auto max-w-sm px-4 py-12 text-center">
              <p className="text-sm font-medium text-foreground">
                {searchEmpty ? "No tenants match your search" : "No tenants yet"}
              </p>
              <p className="mt-1 text-sm text-muted-foreground">
                {searchEmpty
                  ? "Try a different filter or search term."
                  : "Create the first organization tenant to provision a database and workspace."}
              </p>
              {!searchEmpty ? (
                <Link href="/platform/tenants/create" className={buttonVariants({ className: "mt-4" })}>
                  Create tenant
                </Link>
              ) : null}
            </div>
          }
          enableColumnVisibility
          columnVisibilityStorageKey="toweros.table.columns.platform.tenants"
        />
      </div>

      {lastPage > 1 ? (
        <div className="flex flex-wrap items-center justify-between gap-3 text-sm">
          <p className="text-muted-foreground">
            Page {page} of {lastPage} · {perPage} per page
          </p>
          <div className="flex items-center gap-2">
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={page <= 1 || isLoading}
              onClick={() => onPageChange(page - 1)}
            >
              Previous
            </Button>
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={page >= lastPage || isLoading}
              onClick={() => onPageChange(page + 1)}
            >
              Next
            </Button>
          </div>
        </div>
      ) : null}
    </div>
  );
}
