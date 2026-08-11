"use client";

import { useEffect, useMemo, useState } from "react";

import { TenantBrandMark } from "@/components/layout/tenant-brand-mark";
import { useOrganizationLabel } from "@/hooks/use-organization-label";
import { resolveEnvironmentBadgeLabel } from "@/lib/runtime/app-environment";
import { cn } from "@/lib/utils";

type Props = {
  variant: "tenant" | "platform";
  className?: string;
};

const badgeTone: Record<string, string> = {
  STAGING: "bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-100",
  LOCAL: "bg-sky-100 text-sky-900 dark:bg-sky-950 dark:text-sky-100",
  TEST: "bg-violet-100 text-violet-900 dark:bg-violet-950 dark:text-violet-100",
};

/** Sidebar header brand — organization logo/title from branding + auth context. */
export function SidebarBrand({ variant, className }: Props) {
  const organizationLabel = useOrganizationLabel();
  const [envBadge, setEnvBadge] = useState<string | null>(null);

  useEffect(() => {
    setEnvBadge(resolveEnvironmentBadgeLabel());
  }, []);

  const title = useMemo(() => {
    if (variant === "platform") {
      return "TowerOS";
    }
    return organizationLabel;
  }, [organizationLabel, variant]);

  const subtitle = variant === "platform" ? "Superadmin console" : "Workspace";

  return (
    <div
      className={cn(
        "flex min-w-0 items-center gap-3 group-data-[collapsible=icon]:justify-center",
        className,
      )}
    >
      <TenantBrandMark />
      <div className="flex min-w-0 flex-col overflow-hidden group-data-[collapsible=icon]:hidden">
        <div className="flex min-w-0 items-center gap-2">
          <span className="truncate text-base font-semibold tracking-tight text-sidebar-foreground">
            {title}
          </span>
          {envBadge ? (
            <span
              className={cn(
                "shrink-0 rounded px-1.5 py-0.5 text-[10px] font-semibold tracking-wide",
                badgeTone[envBadge] ?? "bg-muted text-muted-foreground",
              )}
            >
              {envBadge}
            </span>
          ) : null}
        </div>
        <span className="truncate text-xs text-sidebar-foreground/50">{subtitle}</span>
      </div>
    </div>
  );
}
