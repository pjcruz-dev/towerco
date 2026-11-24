"use client";

import { useQuery } from "@tanstack/react-query";

import { filterEApprovalPreviewNav, useEApprovalRolePreview } from "@/components/e-approval/e-approval-role-field-access";
import { filterDocumentRegisterPreviewNav, useDocumentRegisterRolePreview } from "@/components/documents/document-register-role-field-access";
import { filterTicketingPreviewNav, useTicketingRolePreview } from "@/components/ticketing/ticketing-role-field-access";
import { useMemo } from "react";
import { LayoutDashboard } from "lucide-react";

import {
  EAPPROVAL_FORM_WORKSPACES_QUERY_KEY,
  fetchEApprovalFormWorkspaces,
} from "@/lib/api/modules/e-approval-api";
import { fetchWorkspaceSidebar } from "@/lib/api/modules/sidebar-nav-api";
import { filterByTenantModules, filterTop } from "@/lib/navigation/workspace-command-index";
import { resolveLucideIcon } from "@/lib/navigation/lucide-icon-registry";
import { workspaceNavGroups, type WorkspaceTopNavItem } from "@/lib/navigation/workspace-nav-config";
import {
  notificationsModuleEnabled,
  resolveEnabledModulesForUser,
} from "@/lib/tenant/enabled-modules";
import { hasPermission, permissions } from "@/lib/rbac/permissions";
import { useAuthStore } from "@/stores/auth-store";
import { useTenantNotificationUnreadCount } from "@/hooks/use-tenant-notifications";

export type WorkspaceNavGroupView = {
  group: string;
  items: WorkspaceTopNavItem[];
};

function withFormWorkspaces(
  source: WorkspaceNavGroupView[],
  workspaces: Array<{ title: string; slug: string }> | undefined,
): WorkspaceNavGroupView[] {
  const workspaceItems: WorkspaceTopNavItem[] =
    workspaces?.map((workspace) => ({
      title: workspace.title,
      href: `/e-approval/w/${workspace.slug}`,
      icon: LayoutDashboard,
      permissions: [permissions.eApprovalView],
      module: "e_approval",
    })) ?? [];

  if (workspaceItems.length === 0) {
    return source;
  }

  return source.map((group) => {
    if (group.group !== "Operations") {
      return group;
    }

    return {
      ...group,
      items: group.items.map((item) => {
        if (item.title !== "E-Forms" && item.title !== "E-Approval") {
          return item;
        }
        const existing = item.items ?? [];
        const withoutDup = existing.filter((sub) => !workspaceItems.some((ws) => ws.href === sub.href));
        return {
          ...item,
          items: [
            ...withoutDup,
            ...workspaceItems.map((ws) => ({
              title: ws.title,
              href: ws.href!,
              section: "Workspaces",
              permissions: ws.permissions,
              module: ws.module,
            })),
          ],
        };
      }),
    };
  });
}

/**
 * Shared filtered workspace navigation for sidebar and top navbar layouts.
 * Prefers tenant Manage Sidebar DB tree; falls back to static config.
 */
export function useWorkspaceNavGroups(options?: { enabled?: boolean }): {
  groups: WorkspaceNavGroupView[];
  /** Permission-filtered product nav. Ignores the saved sidebar tree so app lists match the sidebar. */
  catalogGroups: WorkspaceNavGroupView[];
  notificationUnread: number;
} {
  const enabled = options?.enabled ?? true;
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

  const sidebarQuery = useQuery({
    queryKey: ["workspace-sidebar", activeTenantId],
    queryFn: fetchWorkspaceSidebar,
    enabled: enabled && Boolean(user && activeTenantId),
    staleTime: 60_000,
    retry: 1,
  });

  const staticGroups = useMemo(() => {
    const can = (perms: string[]) => hasPermission(scopedUser, perms);
    const accessMatrix = scopedUser?.accessMatrix;
    return workspaceNavGroups
      .map((group) => ({
        group: group.group,
        items: filterTop(
          filterByTenantModules(group.items, enabledModules),
          can,
          enabledModules,
          accessMatrix,
        ),
      }))
      .filter((group) => group.items.length > 0);
  }, [enabledModules, scopedUser]);

  const dbGroups = useMemo((): WorkspaceNavGroupView[] | null => {
    const payload = sidebarQuery.data;
    if (!payload?.groups?.length) return null;

    const mapItem = (item: {
      title: string;
      icon?: string | null;
      href?: string | null;
      module?: string | null;
      exact?: boolean;
      items?: Array<{
        title: string;
        icon?: string | null;
        href?: string | null;
        module?: string | null;
        exact?: boolean;
        items?: unknown[];
      }>;
    }): WorkspaceTopNavItem | null => {
      const children = (item.items ?? [])
        .map((child) => {
          const href = (child.href ?? "").trim();
          if (!href) return null;
          return {
            title: child.title,
            href,
            exact: Boolean(child.exact),
            permissions: [] as string[],
            module: child.module ?? undefined,
          };
        })
        .filter(Boolean) as NonNullable<WorkspaceTopNavItem["items"]>;

      const href = (item.href ?? "").trim() || undefined;
      if (!href && children.length === 0) {
        return null;
      }

      return {
        title: item.title,
        icon: resolveLucideIcon(item.icon),
        href,
        exact: Boolean(item.exact),
        permissions: [],
        module: item.module ?? undefined,
        ...(children.length > 0 ? { items: children } : {}),
      };
    };

    const mapped = payload.groups
      .map((group) => ({
        group: group.group,
        items: (group.items ?? []).map(mapItem).filter(Boolean) as WorkspaceTopNavItem[],
      }))
      .filter((group) => group.items.length > 0);

    // Empty mapping must not wipe the aside — fall back to static config.
    return mapped.length > 0 ? mapped : null;
  }, [sidebarQuery.data]);

  // Prefer DB Manage Sidebar tree; never replace static with an empty list.
  const groups = dbGroups && dbGroups.length > 0 ? dbGroups : staticGroups;

  const canViewNotifications = useMemo(
    () =>
      notificationsModuleEnabled(enabledModules) &&
      hasPermission(scopedUser, [permissions.eApprovalView]),
    [enabledModules, scopedUser],
  );
  const unreadQuery = useTenantNotificationUnreadCount(enabled && canViewNotifications);
  const notificationUnread = unreadQuery.data ?? 0;

  const canViewEApproval = useMemo(
    () => hasPermission(scopedUser, [permissions.eApprovalView]),
    [scopedUser],
  );
  const workspacesQuery = useQuery({
    queryKey: [...EAPPROVAL_FORM_WORKSPACES_QUERY_KEY],
    queryFn: fetchEApprovalFormWorkspaces,
    enabled: enabled && canViewEApproval,
    staleTime: 60_000,
  });

  const groupsWithWorkspaces = useMemo(() => {
    // When DB sidebar is active, e-approval workspaces are managed as nav items separately.
    if (dbGroups) {
      return groups;
    }

    return withFormWorkspaces(groups, workspacesQuery.data);
  }, [dbGroups, groups, workspacesQuery.data]);

  const catalogWithWorkspaces = useMemo(
    () => withFormWorkspaces(staticGroups, workspacesQuery.data),
    [staticGroups, workspacesQuery.data],
  );

  const eApprovalPreview = useEApprovalRolePreview();
  const documentPreview = useDocumentRegisterRolePreview();
  const ticketingPreview = useTicketingRolePreview();
  const previewedGroups = useMemo(
    () =>
      filterTicketingPreviewNav(
        filterDocumentRegisterPreviewNav(
          filterEApprovalPreviewNav(
            groupsWithWorkspaces,
            eApprovalPreview.allowsHref,
            eApprovalPreview.active,
          ),
          documentPreview.allowsHref,
          documentPreview.active,
        ),
        ticketingPreview.allowsHref,
        ticketingPreview.active,
      ),
    [
      documentPreview.active,
      documentPreview.allowsHref,
      eApprovalPreview.active,
      eApprovalPreview.allowsHref,
      groupsWithWorkspaces,
      ticketingPreview.active,
      ticketingPreview.allowsHref,
    ],
  );

  const catalogGroups = useMemo(
    () =>
      filterTicketingPreviewNav(
        filterDocumentRegisterPreviewNav(
          filterEApprovalPreviewNav(
            catalogWithWorkspaces,
            eApprovalPreview.allowsHref,
            eApprovalPreview.active,
          ),
          documentPreview.allowsHref,
          documentPreview.active,
        ),
        ticketingPreview.allowsHref,
        ticketingPreview.active,
      ),
    [
      catalogWithWorkspaces,
      documentPreview.active,
      documentPreview.allowsHref,
      eApprovalPreview.active,
      eApprovalPreview.allowsHref,
      ticketingPreview.active,
      ticketingPreview.allowsHref,
    ],
  );

  return { groups: previewedGroups, catalogGroups, notificationUnread };
}
