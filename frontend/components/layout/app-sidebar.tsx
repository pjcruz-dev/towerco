"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useMemo } from "react";
import { LayoutDashboard } from "lucide-react";
import type { LucideIcon } from "lucide-react";

import { SidebarBrand } from "@/components/layout/sidebar-brand";
import { SidebarNavGroup, type SidebarSubNavItem } from "@/components/layout/sidebar-nav-group";
import {
  Sidebar,
  SidebarContent,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  useSidebar,
} from "@/components/ui/sidebar";
import { useQuery } from "@tanstack/react-query";

import {
  fetchGateApprovalsAwaitingMeCount,
  GATE_APPROVALS_AWAITING_ME_COUNT_QUERY_KEY,
} from "@/lib/api/modules/rollout-api";
import {
  EAPPROVAL_FORM_WORKSPACES_QUERY_KEY,
  fetchEApprovalFormWorkspaces,
} from "@/lib/api/modules/e-approval-api";
import { useTenantNotificationUnreadCount } from "@/hooks/use-tenant-notifications";
import { useProcurementPlanFeatures } from "@/hooks/use-procurement-plan-features";
import { isNavActive } from "@/lib/navigation/is-nav-active";
import { filterByTenantModules, filterTop } from "@/lib/navigation/workspace-command-index";
import { workspaceNavGroups } from "@/lib/navigation/workspace-nav-config";
import {
  isTenantModuleEnabled,
  notificationsModuleEnabled,
  resolveEnabledModulesForUser,
} from "@/lib/tenant/enabled-modules";
import { hasPermission, permissions } from "@/lib/rbac/permissions";
import { useAuthStore } from "@/stores/auth-store";

type SubNav = SidebarSubNavItem & { permissions: string[]; section?: string };

function resolveNavGroupHomeHref(items: SubNav[]): string {
  const overview = items.find((item) => item.exact);
  return overview?.href ?? items[0]!.href;
}

const navButtonClass =
  "text-sidebar-foreground/70 transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground data-active:bg-sidebar-accent data-active:text-sidebar-accent-foreground";

function SidebarNavLink({
  title,
  href,
  exact,
  icon: Icon,
  badge,
}: {
  title: string;
  href: string;
  exact?: boolean;
  icon: LucideIcon;
  badge?: number;
}) {
  const pathname = usePathname();
  const { isMobile, setOpenMobile } = useSidebar();
  const active = isNavActive(pathname, href, exact);

  return (
    <SidebarMenuItem>
      <SidebarMenuButton
        render={
          <Link
            href={href}
            prefetch={false}
            onClick={() => {
              if (isMobile) {
                setOpenMobile(false);
              }
            }}
          />
        }
        tooltip={title}
        isActive={active}
        className={navButtonClass}
      >
        <Icon className="h-4 w-4" />
        <span>{title}</span>
        {badge !== undefined && badge > 0 ? (
          <span className="ml-auto inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-sidebar-primary px-1.5 text-[10px] font-medium text-sidebar-primary-foreground group-data-[collapsible=icon]:hidden">
            {badge > 99 ? "99+" : badge}
          </span>
        ) : null}
      </SidebarMenuButton>
    </SidebarMenuItem>
  );
}

export function AppSidebar() {
  const user = useAuthStore((state) => state.user);
  const activeTenantId = useAuthStore((state) => state.activeTenantId);
  const effectivePermissions = useAuthStore((state) => state.effectivePermissions);

  const scopedUser = useMemo(() => {
    if (!user || !activeTenantId) {
      return user;
    }
    return { ...user, permissions: effectivePermissions() };
  }, [activeTenantId, effectivePermissions, user]);

  const enabledModules = useMemo(
    () => resolveEnabledModulesForUser(user, activeTenantId),
    [activeTenantId, user],
  );
  const procurementModuleEnabled = isTenantModuleEnabled(enabledModules, "procurement_one");
  const procurementPlanQuery = useProcurementPlanFeatures({ enabled: procurementModuleEnabled });
  const procurementPlanFeatures = procurementModuleEnabled ? procurementPlanQuery.data : null;

  const groups = useMemo(() => {
    const can = (perms: string[]) => hasPermission(scopedUser, perms);
    return workspaceNavGroups
      .map((group) => ({
        group: group.group,
        items: filterTop(
          filterByTenantModules(group.items, enabledModules),
          can,
          enabledModules,
          procurementPlanFeatures,
        ),
      }))
      .filter((group) => group.items.length > 0);
  }, [enabledModules, procurementPlanFeatures, scopedUser]);

  const canViewNotifications = useMemo(
    () =>
      notificationsModuleEnabled(enabledModules) &&
      (hasPermission(scopedUser, [permissions.eApprovalView]) ||
        hasPermission(scopedUser, [permissions.rolloutView]) ||
        hasPermission(scopedUser, [permissions.rolloutGateApprove])),
    [enabledModules, scopedUser],
  );
  const unreadQuery = useTenantNotificationUnreadCount(canViewNotifications);
  const notificationUnread = unreadQuery.data ?? 0;

  const canViewGateApprovals = useMemo(
    () =>
      hasPermission(scopedUser, [permissions.rolloutView]) ||
      hasPermission(scopedUser, [permissions.rolloutGateApprove]),
    [scopedUser],
  );
  const gateAwaitingQuery = useQuery({
    queryKey: [...GATE_APPROVALS_AWAITING_ME_COUNT_QUERY_KEY],
    queryFn: fetchGateApprovalsAwaitingMeCount,
    enabled: canViewGateApprovals,
    staleTime: 30_000,
    // No background polling: rollout Echo events invalidate the "project-one/gate-approvals"
    // prefix (this key included) and we still refetch on window focus.
    refetchOnWindowFocus: true,
  });
  const gateApprovalsAwaitingMe = gateAwaitingQuery.data ?? 0;

  const canViewEApproval = useMemo(
    () => hasPermission(scopedUser, [permissions.eApprovalView]),
    [scopedUser],
  );
  const workspacesQuery = useQuery({
    queryKey: [...EAPPROVAL_FORM_WORKSPACES_QUERY_KEY],
    queryFn: fetchEApprovalFormWorkspaces,
    enabled: canViewEApproval,
    staleTime: 60_000,
  });

  const groupsWithBadges = useMemo(() => {
    const workspaceTopLevelItems: Array<{
      title: string;
      href: string;
      icon: LucideIcon;
    }> =
      workspacesQuery.data?.map((workspace) => ({
        title: workspace.title,
        href: `/e-approval/w/${workspace.slug}`,
        icon: LayoutDashboard,
      })) ?? [];

    return groups.map((group) => {
      let items = group.items.map((item) => {
        if (!item.items) {
          return item;
        }

        if (item.title !== "Project-One" || gateApprovalsAwaitingMe <= 0) {
          return item;
        }

        return {
          ...item,
          items: item.items.map((sub) =>
            sub.href.startsWith("/project-one/gate-approvals")
              ? { ...sub, badge: gateApprovalsAwaitingMe }
              : sub,
          ),
        };
      });

      if (group.group === "Operations" && workspaceTopLevelItems.length > 0) {
        const eApprovalIndex = items.findIndex((item) => item.title === "E-Approval");
        const insertAt = eApprovalIndex >= 0 ? eApprovalIndex : items.length;
        items = [
          ...items.slice(0, insertAt),
          ...workspaceTopLevelItems,
          ...items.slice(insertAt),
        ];
      }

      return { ...group, items };
    });
  }, [gateApprovalsAwaitingMe, groups, workspacesQuery.data]);

  return (
    <Sidebar variant="sidebar" collapsible="icon" className="border-r border-sidebar-border bg-sidebar text-sidebar-foreground">
      <SidebarHeader className="border-b border-sidebar-border p-4 group-data-[collapsible=icon]:p-2">
        <SidebarBrand variant="tenant" />
      </SidebarHeader>
      <SidebarContent className="scrollbar-hide gap-1 px-2 py-2">
        {groupsWithBadges.map((group) => (
          <div key={group.group} className="mt-2 first:mt-0">
            <div className="px-3 py-2 text-xs font-medium text-sidebar-foreground/45 group-data-[collapsible=icon]:hidden">
              {group.group}
            </div>
            <SidebarMenu>
              {group.items.map((item) =>
                item.items ? (
                  <SidebarNavGroup
                    key={item.title}
                    title={item.title}
                    icon={item.icon}
                    href={resolveNavGroupHomeHref(item.items as SubNav[])}
                    items={item.items.map(({ title, href, exact, section, badge }) => ({
                      title,
                      href,
                      exact,
                      section,
                      badge,
                    }))}
                    buttonClassName={navButtonClass}
                  />
                ) : (
                  <SidebarNavLink
                    key={item.title}
                    title={item.title}
                    href={item.href!}
                    exact={item.exact}
                    icon={item.icon}
                    badge={item.title === "Notifications" ? notificationUnread : undefined}
                  />
                ),
              )}
            </SidebarMenu>
          </div>
        ))}
      </SidebarContent>
    </Sidebar>
  );
}
