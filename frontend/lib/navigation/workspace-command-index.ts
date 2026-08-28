import type { LucideIcon } from "lucide-react";

import type { AuthUser } from "@/types/auth";
import type { ProcurementPlanFeatures } from "@/modules/procurement-one/types";
import { hasPermission } from "@/lib/rbac/permissions";
import {
  isProcurementPlanFeatureEnabled,
} from "@/lib/procurement/procurement-plan-features";
import {
  isTenantModuleEnabled,
  notificationsModuleEnabled,
  TENANT_MODULE_LABELS,
} from "@/lib/tenant/enabled-modules";
import {
  workspaceNavGroups,
  workspaceQuickActions,
  type WorkspaceNavGroup,
  type WorkspaceQuickAction,
  type WorkspaceSubNavItem,
  type WorkspaceTopNavItem,
} from "@/lib/navigation/workspace-nav-config";

export type WorkspaceCommandKind = "navigate" | "action" | "recent" | "entity";

export type WorkspaceCommandItem = {
  id: string;
  kind: WorkspaceCommandKind;
  title: string;
  description?: string;
  href: string;
  icon: LucideIcon;
  group: string;
  parent?: string;
  section?: string;
  module?: string;
  keywords?: string[];
  /** Raw status code from entity search (e.g. returned). */
  status?: string | null;
  /** Friendly status chip label (e.g. Needs revision). */
  statusLabel?: string | null;
};

const RECENT_STORAGE_KEY = "toweros.workspace.command-recent";
const RECENT_LIMIT = 5;

type StoredRecentItem = {
  id: string;
  title: string;
  description?: string;
  href: string;
  group: string;
  parent?: string;
  section?: string;
  module?: string;
  kind: WorkspaceCommandKind;
};

function canAccessAny(can: (perms: string[]) => boolean, permissionsList: string[]): boolean {
  return permissionsList.some((permission) => can([permission]));
}

function filterSub(
  items: WorkspaceSubNavItem[],
  can: (p: string[]) => boolean,
  enabledModules: string[],
  procurementPlanFeatures?: ProcurementPlanFeatures | null,
): WorkspaceSubNavItem[] {
  return items.filter((item) => {
    if (item.module && !isTenantModuleEnabled(enabledModules, item.module)) {
      return false;
    }

    if (
      item.procurementPlanFeature &&
      procurementPlanFeatures &&
      !isProcurementPlanFeatureEnabled(procurementPlanFeatures, item.procurementPlanFeature)
    ) {
      return false;
    }

    return item.permissionsMatch === "any"
      ? canAccessAny(can, item.permissions)
      : can(item.permissions);
  });
}

function filterTop(
  items: WorkspaceTopNavItem[],
  can: (p: string[]) => boolean,
  enabledModules: string[],
  procurementPlanFeatures?: ProcurementPlanFeatures | null,
): WorkspaceTopNavItem[] {
  return items
    .map((item) => {
      if (item.items) {
        if (!canAccessAny(can, item.permissions)) {
          return null;
        }
        const sub = filterSub(item.items, can, enabledModules, procurementPlanFeatures);
        if (sub.length === 0) {
          return null;
        }
        return { ...item, items: sub };
      }
      if (!item.href) {
        return null;
      }

      const allowed =
        item.permissionsMatch === "any"
          ? canAccessAny(can, item.permissions)
          : can(item.permissions);

      return allowed ? item : null;
    })
    .filter((item): item is WorkspaceTopNavItem => item !== null);
}

function filterByTenantModules(items: WorkspaceTopNavItem[], enabledModules: string[]): WorkspaceTopNavItem[] {
  if (enabledModules.length === 0) {
    return items;
  }

  return items.filter((item) => {
    if (item.moduleGate === "notifications") {
      return notificationsModuleEnabled(enabledModules);
    }

    return isTenantModuleEnabled(enabledModules, item.module);
  });
}

function moduleLabel(module: string | undefined): string | undefined {
  if (!module) {
    return undefined;
  }

  return TENANT_MODULE_LABELS[module] ?? module.replace(/_/g, " ");
}

function flattenNavGroups(groups: WorkspaceNavGroup[]): WorkspaceCommandItem[] {
  const items: WorkspaceCommandItem[] = [];

  for (const { group, items: topItems } of groups) {
    for (const top of topItems) {
      if (top.items) {
        for (const sub of top.items) {
          items.push({
            id: `nav:${sub.href}`,
            kind: "navigate",
            title: sub.title,
            description: [top.title, sub.section, moduleLabel(top.module)].filter(Boolean).join(" · "),
            href: sub.href,
            icon: top.icon,
            group,
            parent: top.title,
            section: sub.section,
            module: top.module,
            keywords: [top.title, sub.title, sub.section ?? "", group, top.module ?? ""],
          });
        }
        continue;
      }

      if (!top.href) {
        continue;
      }

      items.push({
        id: `nav:${top.href}`,
        kind: "navigate",
        title: top.title,
        description: [group, moduleLabel(top.module)].filter(Boolean).join(" · "),
        href: top.href,
        icon: top.icon,
        group,
        module: top.module,
        keywords: [top.title, group, top.module ?? ""],
      });
    }
  }

  return items;
}

function dedupeNavigateItems(items: WorkspaceCommandItem[]): WorkspaceCommandItem[] {
  const byHref = new Map<string, WorkspaceCommandItem>();

  for (const item of items) {
    const existing = byHref.get(item.href);
    if (!existing) {
      byHref.set(item.href, item);
      continue;
    }

    byHref.set(item.href, {
      ...existing,
      keywords: [
        ...new Set([
          ...(existing.keywords ?? []),
          ...(item.keywords ?? []),
          existing.title,
          item.title,
          existing.parent ?? "",
          item.parent ?? "",
        ].filter(Boolean)),
      ],
    });
  }

  return Array.from(byHref.values());
}

function filterQuickActions(
  actions: WorkspaceQuickAction[],
  enabledModules: string[],
  can: (perms: string[]) => boolean,
): WorkspaceCommandItem[] {
  return actions
    .filter((action) => {
      if (action.moduleGate === "notifications") {
        if (!notificationsModuleEnabled(enabledModules)) {
          return false;
        }
      } else if (!isTenantModuleEnabled(enabledModules, action.module)) {
        return false;
      }

      return action.permissionsMatch === "any"
        ? canAccessAny(can, action.permissions)
        : can(action.permissions);
    })
    .map((action) => ({
      id: `action:${action.id}`,
      kind: "action" as const,
      title: action.title,
      description: action.description,
      href: action.href,
      icon: action.icon,
      group: "Quick actions",
      module: action.module,
      keywords: action.keywords,
    }));
}

export function buildWorkspaceCommandIndex(
  user: AuthUser | null,
  enabledModules: string[],
): { navigate: WorkspaceCommandItem[]; actions: WorkspaceCommandItem[] } {
  const can = (perms: string[]) => hasPermission(user, perms);

  const filteredGroups = workspaceNavGroups
    .map((group) => ({
      group: group.group,
      items: filterTop(filterByTenantModules(group.items, enabledModules), can, enabledModules),
    }))
    .filter((group) => group.items.length > 0);

  return {
    navigate: dedupeNavigateItems(flattenNavGroups(filteredGroups)),
    actions: filterQuickActions(workspaceQuickActions, enabledModules, can),
  };
}

export function matchesWorkspaceCommandQuery(item: WorkspaceCommandItem, query: string): boolean {
  const normalized = query.trim().toLowerCase();
  if (!normalized) {
    return true;
  }

  const haystack = [
    item.title,
    item.description,
    item.group,
    item.parent,
    item.section,
    item.module ? moduleLabel(item.module) : undefined,
    ...(item.keywords ?? []),
  ]
    .filter(Boolean)
    .join(" ")
    .toLowerCase();

  return normalized.split(/\s+/).every((token) => haystack.includes(token));
}

function hydrateRecentItem(
  stored: StoredRecentItem,
  lookup: Map<string, WorkspaceCommandItem>,
): WorkspaceCommandItem | null {
  const match = lookup.get(stored.href);
  if (!match) {
    return null;
  }

  return {
    ...match,
    kind: "recent",
    group: "Recent",
    title: stored.title || match.title,
    description: stored.description ?? match.description,
  };
}

export function readWorkspaceCommandRecent(
  lookupItems: WorkspaceCommandItem[] = [],
): WorkspaceCommandItem[] {
  if (typeof window === "undefined") {
    return [];
  }

  const lookup = new Map(lookupItems.map((item) => [item.href, item]));

  try {
    const raw = window.localStorage.getItem(RECENT_STORAGE_KEY);
    if (!raw) {
      return [];
    }

    const parsed = JSON.parse(raw) as StoredRecentItem[];
    if (!Array.isArray(parsed)) {
      return [];
    }

    return parsed
      .map((row) => hydrateRecentItem(row, lookup))
      .filter((row): row is WorkspaceCommandItem => row !== null)
      .slice(0, RECENT_LIMIT);
  } catch {
    return [];
  }
}

export function rememberWorkspaceCommandItem(item: WorkspaceCommandItem): void {
  if (typeof window === "undefined") {
    return;
  }

  const storedEntry: StoredRecentItem = {
    id: item.id,
    kind: item.kind === "recent" ? "navigate" : item.kind,
    title: item.title,
    description: item.description,
    href: item.href,
    group: item.group,
    parent: item.parent,
    section: item.section,
    module: item.module,
  };

  let existing: StoredRecentItem[] = [];
  try {
    const raw = window.localStorage.getItem(RECENT_STORAGE_KEY);
    existing = raw ? (JSON.parse(raw) as StoredRecentItem[]) : [];
  } catch {
    existing = [];
  }

  const next = [storedEntry, ...existing.filter((row) => row.href !== item.href)].slice(0, RECENT_LIMIT);

  try {
    window.localStorage.setItem(RECENT_STORAGE_KEY, JSON.stringify(next));
  } catch {
    // Ignore quota errors.
  }
}

/** Re-export nav filter helpers for the sidebar. */
export { filterByTenantModules, filterTop, filterSub, canAccessAny };
