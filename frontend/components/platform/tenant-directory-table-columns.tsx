"use client";

import {
  Copy,
  ExternalLink,
  CreditCard,
  LayoutGrid,
  Package,
  Palette,
  Plus,
  Settings2,
  Shield,
  Trash2,
} from "lucide-react";
import Link from "next/link";
import type { ColumnDef } from "@tanstack/react-table";

import { Badge } from "@/components/ui/badge";
import { buttonVariants } from "@/components/ui/button";
import { DataTableColumnHeader } from "@/components/ui/data-table-column-header";
import { environmentBadgeClass } from "@/components/platform/tenant-environment-sheet";
import { RowActionsMenu } from "@/components/ui/row-actions-menu";
import type { PlatformTenantRow } from "@/lib/api/modules/platform-api";
import { formatTenantModuleBadges, moduleBadgeClass } from "@/lib/platform/tenant-directory-utils";
import { tenantLoginUrl } from "@/lib/tenant/resolve-tenant-domain";
import { cn } from "@/lib/utils";

function formatCreatedAt(iso: string | null): string {
  if (!iso) {
    return "-";
  }
  try {
    return new Intl.DateTimeFormat(undefined, {
      dateStyle: "medium",
      timeStyle: "short",
    }).format(new Date(iso));
  } catch {
    return iso;
  }
}

function shortTenantId(id: string): string {
  return id.length > 12 ? `${id.slice(0, 8)}...${id.slice(-4)}` : id;
}

const accessBadgeClass: Record<string, string> = {
  full: "bg-emerald-500/10 text-emerald-700 dark:text-emerald-400",
  grace: "bg-amber-500/10 text-amber-700 dark:text-amber-400",
  read_only: "bg-amber-500/10 text-amber-700 dark:text-amber-400",
  blocked: "bg-destructive/10 text-destructive",
};

function formatAccessLabel(mode: string | null | undefined): string {
  if (!mode || mode === "full") {
    return "Full";
  }
  return mode.replace(/_/g, " ");
}

const planBadgeClass: Record<string, string> = {
  starter: "bg-muted text-muted-foreground",
  professional: "border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950/30 dark:text-sky-400",
  enterprise: "bg-violet-500/10 text-violet-700 dark:text-violet-300",
};

function TenantRowActionsMenu({
  row,
  loginUrl,
  onCopyId,
  onBranding,
  onBilling,
  onModules,
  onPlaybook,
  onAddEnv,
  onDelete,
}: {
  row: PlatformTenantRow;
  loginUrl: string | null;
  onCopyId: (id: string) => void;
  onBranding: (row: PlatformTenantRow) => void;
  onBilling: (row: PlatformTenantRow) => void;
  onModules: (row: PlatformTenantRow) => void;
  onPlaybook?: (row: PlatformTenantRow) => void;
  onAddEnv: (row: PlatformTenantRow) => void;
  onDelete: (row: PlatformTenantRow) => void;
}) {
  return (
    <RowActionsMenu
      leading={
        <Link
          href={`/platform/tenants/${row.id}`}
          className={buttonVariants({ variant: "outline", size: "sm", className: "h-8" })}
        >
          Manage
        </Link>
      }
      items={[
        {
          key: "manage",
          label: (
            <>
              <Settings2 className="size-3.5 text-muted-foreground" />
              Manage tenant
            </>
          ),
          href: `/platform/tenants/${row.id}`,
        },
        {
          key: "open",
          label: (
            <>
              <ExternalLink className="size-3.5 text-muted-foreground" />
              Open tenant
            </>
          ),
          hidden: !loginUrl,
          href: loginUrl ?? undefined,
        },
        {
          key: "copy",
          label: (
            <>
              <Copy className="size-3.5 text-muted-foreground" />
              Copy tenant ID
            </>
          ),
          onSelect: () => onCopyId(row.id),
        },
        {
          key: "billing",
          label: (
            <>
              <CreditCard className="size-3.5 text-muted-foreground" />
              Billing & plan
            </>
          ),
          onSelect: () => onBilling(row),
        },
        {
          key: "modules",
          label: (
            <>
              <LayoutGrid className="size-3.5 text-muted-foreground" />
              Workspace modules
            </>
          ),
          onSelect: () => onModules(row),
        },
        {
          key: "playbook",
          label: (
            <>
              <Package className="size-3.5 text-muted-foreground" />
              Rollout policy
            </>
          ),
          hidden: !onPlaybook,
          onSelect: () => onPlaybook?.(row),
        },
        {
          key: "branding",
          label: (
            <>
              <Palette className="size-3.5 text-muted-foreground" />
              Branding
            </>
          ),
          onSelect: () => onBranding(row),
        },
        {
          key: "add-env",
          label: (
            <>
              <Plus className="size-3.5 text-muted-foreground" />
              Add environment
            </>
          ),
          onSelect: () => onAddEnv(row),
        },
        { type: "separator", key: "sep" },
        {
          key: "delete",
          label: (
            <>
              <Trash2 className="size-3.5" />
              Delete tenant
            </>
          ),
          destructive: true,
          onSelect: () => onDelete(row),
        },
      ]}
    />
  );
}

export function createTenantDirectoryTableColumns(options: {
  mfaPendingTenantId?: string;
  onCopyId: (id: string) => void;
  onBranding: (row: PlatformTenantRow) => void;
  onBilling: (row: PlatformTenantRow) => void;
  onModules: (row: PlatformTenantRow) => void;
  onPlaybook?: (row: PlatformTenantRow) => void;
  onAddEnv: (row: PlatformTenantRow) => void;
  onDelete: (row: PlatformTenantRow) => void;
  onMfaToggle: (row: PlatformTenantRow) => void;
}): ColumnDef<PlatformTenantRow>[] {
  return [
    {
      id: "organization",
      header: "Organization",
      cell: ({ row }) => {
        const tenant = row.original;
        const primaryDomain = tenant.domains[0];
        const isChild = Boolean(tenant.parent_tenant_id);

        return (
          <div className="min-w-0">
            <div className="flex flex-wrap items-center gap-1.5">
              <Link
                href={`/platform/tenants/${tenant.id}`}
                className="text-sm font-medium text-foreground hover:underline"
              >
                {tenant.slug ?? shortTenantId(tenant.id)}
              </Link>
              <span
                className={cn(
                  "rounded-full px-2 py-0.5 text-[10px] font-medium capitalize",
                  environmentBadgeClass(tenant.environment),
                )}
              >
                {tenant.environment ?? "production"}
              </span>
              {isChild ? (
                <Badge variant="outline" className="px-1.5 py-0 text-[10px]">
                  Linked
                </Badge>
              ) : null}
            </div>
            {primaryDomain ? (
              <p className="mt-0.5 truncate font-mono text-xs text-muted-foreground">{primaryDomain}</p>
            ) : null}
          </div>
        );
      },
      meta: { className: "min-w-[200px] px-4 py-2.5 whitespace-normal" },
    },
    {
      id: "modules",
      header: "Modules",
      cell: ({ row }) => {
        const moduleBadges = formatTenantModuleBadges(row.original);
        return (
          <div className="flex flex-wrap gap-1">
            {moduleBadges.map((badge) => (
              <span
                key={badge}
                className={cn(
                  "inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium",
                  moduleBadgeClass(badge),
                )}
              >
                {badge}
              </span>
            ))}
          </div>
        );
      },
      meta: { className: "min-w-[120px] px-4 py-2.5 whitespace-normal" },
    },
    {
      id: "created",
      accessorFn: (row) => row.created_at ?? "",
      enableSorting: true,
      header: ({ column }) => <DataTableColumnHeader column={column} title="Created" />,
      cell: ({ row }) => (
        <span className="text-xs text-muted-foreground">{formatCreatedAt(row.original.created_at)}</span>
      ),
      meta: { className: "hidden px-4 py-2.5 lg:table-cell" },
    },
    {
      id: "plan_usage",
      accessorFn: (row) => row.plan_tier ?? "",
      enableSorting: true,
      header: ({ column }) => <DataTableColumnHeader column={column} title="Plan & usage" />,
      cell: ({ row }) => {
        const tenant = row.original;
        return (
          <div className="space-y-0.5">
            <span
              className={cn(
                "inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium capitalize",
                planBadgeClass[tenant.plan_tier ?? "starter"] ?? planBadgeClass.starter,
              )}
            >
              {tenant.plan_tier ?? "starter"}
            </span>
            <p className="text-[11px] tabular-nums text-muted-foreground">
              {tenant.effective_seat_limit ?? tenant.seat_limit ?? "-"} seats
              {tenant.effective_rfi_limit != null
                ? `  |  ${tenant.rfi_units_used ?? 0}/${tenant.effective_rfi_limit} RFI`
                : ""}
            </p>
          </div>
        );
      },
      meta: { className: "hidden w-[140px] px-4 py-2.5 md:table-cell" },
    },
    {
      id: "playbook",
      header: "Playbook",
      cell: ({ row }) => {
        const tenant = row.original;
        if (tenant.assigned_playbook_version) {
          return (
            <div className="space-y-0.5">
              {options.onPlaybook ? (
                <button
                  type="button"
                  className="text-left text-xs font-medium text-primary hover:underline"
                  onClick={() => options.onPlaybook?.(tenant)}
                >
                  v{tenant.assigned_playbook_version}
                </button>
              ) : (
                <p className="text-xs font-medium text-foreground">v{tenant.assigned_playbook_version}</p>
              )}
              {tenant.assigned_rollout_policy_code ? (
                <p className="font-mono text-[10px] text-muted-foreground">
                  {tenant.assigned_rollout_policy_code}
                </p>
              ) : null}
              {tenant.playbook_upgrade_available ? (
                <Badge variant="outline" className="text-[10px] text-amber-700">
                  Upgrade
                </Badge>
              ) : null}
            </div>
          );
        }
        if (options.onPlaybook) {
          return (
            <button
              type="button"
              className="text-xs text-primary hover:underline"
              onClick={() => options.onPlaybook?.(tenant)}
            >
              Assign
            </button>
          );
        }
        return <span className="text-xs text-muted-foreground">-</span>;
      },
      meta: { className: "hidden w-[100px] px-4 py-2.5 xl:table-cell" },
    },
    {
      id: "access",
      header: "Access",
      cell: ({ row }) => {
        const tenant = row.original;
        return (
          <>
            <span
              className={cn(
                "inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium capitalize",
                accessBadgeClass[tenant.access_mode ?? "full"] ?? accessBadgeClass.full,
              )}
            >
              {formatAccessLabel(tenant.access_mode)}
            </span>
            {tenant.operator_access_mode ? (
              <p className="mt-1 text-[10px] text-muted-foreground">
                Op: {formatAccessLabel(tenant.operator_access_mode)}
              </p>
            ) : null}
          </>
        );
      },
      meta: { className: "hidden w-[96px] px-4 py-2.5 lg:table-cell" },
    },
    {
      id: "mfa",
      header: "MFA",
      cell: ({ row }) => {
        const tenant = row.original;
        return (
          <button
            type="button"
            role="switch"
            aria-checked={tenant.mfa_required}
            aria-label={`MFA ${tenant.mfa_required ? "on" : "off"} for ${tenant.slug ?? tenant.id}`}
            disabled={options.mfaPendingTenantId === tenant.id}
            onClick={() => options.onMfaToggle(tenant)}
            className={cn(
              "inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-medium transition-colors",
              tenant.mfa_required
                ? "border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-400"
                : "border-border bg-background text-muted-foreground hover:bg-muted/50",
            )}
          >
            <Shield className="size-3.5" />
            {tenant.mfa_required ? "On" : "Off"}
          </button>
        );
      },
      meta: { className: "w-[88px] px-4 py-2.5" },
    },
    {
      id: "actions",
      header: () => <span className="block w-full text-right">Actions</span>,
      cell: ({ row }) => {
        const tenant = row.original;
        const primaryDomain = tenant.domains[0];
        const loginUrl = primaryDomain ? tenantLoginUrl(primaryDomain) : null;
        return (
          <TenantRowActionsMenu
            row={tenant}
            loginUrl={loginUrl}
            onCopyId={options.onCopyId}
            onBranding={options.onBranding}
            onBilling={options.onBilling}
            onModules={options.onModules}
            onPlaybook={options.onPlaybook}
            onAddEnv={options.onAddEnv}
            onDelete={options.onDelete}
          />
        );
      },
      meta: { className: "w-[200px] overflow-visible px-4 py-2.5 text-right" },
    },
  ];
}
