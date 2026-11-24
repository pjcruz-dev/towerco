"use client";

import { useQuery } from "@tanstack/react-query";
import { useEffect, useMemo, useState, type ReactNode } from "react";
import { usePathname } from "next/navigation";

import { RoleFieldAccessProvider, RoleRowActionsMenuItems, useRoleFieldAccessContext } from "@/components/dynamic-entities/dyn-role-field-access";
import { ContextMenu, ContextMenuContent, ContextMenuItem, ContextMenuLabel, ContextMenuSeparator, ContextMenuSub, ContextMenuTrigger } from "@/components/ui/context-menu";
import { EyeOff, Lock } from "lucide-react";
import { usePermission } from "@/hooks/use-permission";
import { fetchAdminRoleCatalog, type AdminRoleRow } from "@/lib/api/modules/admin-roles-api";
import type { EntityAccessAction } from "@/lib/rbac/entity-access";
import { permissions } from "@/lib/rbac/permissions";
import {
  TICKETING_LIST_FIELDS,
  TICKETING_NAV_TARGETS,
  TICKETING_TICKET_ENTITY,
  TICKETING_VIEWING_AS_KEY,
  filterNavGroupsForTicketingPreview,
  ticketingEntityAllows,
  ticketingHasHiddenNavTargets,
  ticketingNavTargetForPath,
  ticketingNavTargetHidden,
  ticketingNavTargetKey,
  ticketingPageListState,
  ticketingPreviewAllowsPath,
} from "@/lib/ticketing/ticket-role-access";
import { useAuthStore } from "@/stores/auth-store";
import { cn } from "@/lib/utils";

export function TicketingRoleAccessProvider({ children }: { children: ReactNode }) {
  return (
    <RoleFieldAccessProvider entitySlug={TICKETING_TICKET_ENTITY} storageKey={TICKETING_VIEWING_AS_KEY}>
      {children}
    </RoleFieldAccessProvider>
  );
}

function previewRoleIsOwner(name: string | undefined): boolean {
  return name === "tenant_admin" || name === "administrator";
}

function readStoredRoleId(): number | null {
  if (typeof window === "undefined") return null;
  const raw = window.sessionStorage.getItem(TICKETING_VIEWING_AS_KEY);
  if (!raw) return null;
  const parsed = Number(raw);
  return Number.isFinite(parsed) ? parsed : null;
}

/** The Ticketing preview role, including when the menu lives outside the page provider. */
export function useTicketingRolePreview() {
  const canManageRoles = usePermission([permissions.roleManage]);
  const accessMatrix = useAuthStore((state) => state.user?.accessMatrix);
  const [roleId, setRoleId] = useState<number | null>(null);
  const catalog = useQuery({
    queryKey: ["admin", "roles"],
    queryFn: fetchAdminRoleCatalog,
    enabled: canManageRoles,
    staleTime: 30_000,
  });

  useEffect(() => {
    setRoleId(readStoredRoleId());
    function onPreview(event: Event) {
      const detail = (event as CustomEvent<{ storageKey?: string; roleId?: number | null }>).detail;
      if (detail?.storageKey !== TICKETING_VIEWING_AS_KEY) return;
      const next = detail.roleId == null ? null : Number(detail.roleId);
      setRoleId(next != null && Number.isFinite(next) ? next : null);
    }
    window.addEventListener("toweros:viewing-as", onPreview);
    return () => window.removeEventListener("toweros:viewing-as", onPreview);
  }, []);

  useEffect(() => {
    if (roleId != null) return;
    const stored = readStoredRoleId();
    if (stored != null) setRoleId(stored);
  }, [catalog.data, roleId]);

  const selected = catalog.data?.roles.find((role) => String(role.id) === String(roleId)) ?? null;
  const previewing = Boolean(selected) && !previewRoleIsOwner(selected?.name);
  const matrix = previewing ? selected?.access_matrix : accessMatrix;
  const filtering = previewing || ticketingHasHiddenNavTargets(matrix);

  return useMemo(() => {
    const allowsHref = (href: string) => {
      if (ticketingNavTargetHidden(matrix, href)) return false;
      if (!previewing || !selected) return true;
      return ticketingPreviewAllowsPath(selected.permissions, selected.access_matrix, href);
    };
    return { active: filtering, allowsHref, selected: selected as AdminRoleRow | null };
  }, [filtering, matrix, previewing, selected]);
}

export function filterTicketingPreviewNav<
  T extends { title: string; href?: string | null; items?: Array<{ href?: string | null }> },
>(
  groups: Array<{ group: string; items: T[] }>,
  allowsHref: (href: string) => boolean,
  active: boolean,
) {
  if (!active) return groups;
  return filterNavGroupsForTicketingPreview(groups, allowsHref);
}

/** Same menu on the page and in either navigation layout. */
export function TicketingRoleMenuItems({ href }: { href?: string }) {
  return (
    <>
      <RoleRowActionsMenuItems
        columns={TICKETING_LIST_FIELDS}
        actions={[
          { action: "view", label: "View" },
          { action: "create", label: "Add new" },
          { action: "export", label: "Export" },
        ]}
        always
      />
      <TicketingPagesMenuItems href={href} />
    </>
  );
}

export function TicketingAnywhereMenu({ className, children }: { className?: string; children: ReactNode }) {
  const access = useRoleFieldAccessContext();
  const pathname = usePathname();
  if (!access?.canManageRoles) return <>{children}</>;

  return (
    <ContextMenu>
      <ContextMenuTrigger className={cn("select-text", className)} render={<div />}>
        {children}
      </ContextMenuTrigger>
      <ContextMenuContent className="min-w-[14rem]">
        <TicketingRoleMenuItems href={pathname} />
      </ContextMenuContent>
    </ContextMenu>
  );
}

export function TicketingModuleFrame({ children }: { children: ReactNode }) {
  const pathname = usePathname();
  const gate = useTicketingRolePreview();
  const allowed = gate.allowsHref(pathname);
  const body = allowed ? (
    children
  ) : (
    <div className="rounded-xl border border-border bg-card px-6 py-10 text-center">
      <h1 className="text-base font-medium text-foreground">This role cannot open this page</h1>
      <p className="mx-auto mt-2 max-w-md text-sm text-muted-foreground">
        Right-click and choose another role, or open a page that role is allowed to use.
      </p>
    </div>
  );

  return <TicketingAnywhereMenu className="block min-h-[calc(100vh-8rem)]">{body}</TicketingAnywhereMenu>;
}

export function useTicketingTicketAccess() {
  const preview = useRoleFieldAccessContext();
  const accessMatrix = useAuthStore((state) => state.user?.accessMatrix);
  const canManageRoles = usePermission([permissions.roleManage]);
  const previewing = Boolean(preview?.selected);
  const matrix = previewing ? preview?.selected?.access_matrix : accessMatrix;
  const configuring = canManageRoles && !previewing;

  return useMemo(
    () => ({
      allows(action: EntityAccessAction) {
        if (configuring) return true;
        return ticketingEntityAllows(matrix, action);
      },
    }),
    [configuring, matrix],
  );
}

function pageLocked(access: NonNullable<ReturnType<typeof useRoleFieldAccessContext>>): boolean {
  const selected = access.selected;
  if (!selected) return true;
  return selected.name === "tenant_admin" || selected.name === "administrator" || selected.permissions.length === 0 || access.saving;
}

export function TicketingPagesMenuItems({ href }: { href?: string }) {
  const access = useRoleFieldAccessContext();
  if (!access?.canManageRoles) return null;

  const locked = pageLocked(access);
  const current = href ? ticketingNavTargetForPath(href) : null;
  const stateFor = (targetHref: string) =>
    access.selected
      ? ticketingPageListState(access.selected.permissions, access.selected.access_matrix, targetHref)
      : "shown";
  const currentState = current ? stateFor(current.href) : "shown";

  return (
    <>
      <ContextMenuSeparator />
      <ContextMenuLabel className="uppercase tracking-wide">Pages</ContextMenuLabel>
      {current ? (
        <ContextMenuItem
          disabled={locked || currentState === "no-access"}
          onClick={() =>
            access.setLevel(ticketingNavTargetKey(current.href), currentState === "hidden" ? "full" : "hide")
          }
        >
          {currentState === "shown" ? (
            <Lock className="size-3.5 text-emerald-600" />
          ) : (
            <EyeOff className="size-3.5 text-destructive" />
          )}
          <span className="flex-1">
            {currentState === "no-access"
              ? current.label
              : currentState === "hidden"
                ? `Show ${current.label}`
                : `Hide ${current.label}`}
          </span>
          <span className="text-xs text-muted-foreground">
            {currentState === "no-access" ? "No access" : currentState === "hidden" ? "Hidden" : "Shown"}
          </span>
        </ContextMenuItem>
      ) : null}
      <ContextMenuSub label="All pages">
        {TICKETING_NAV_TARGETS.map((target) => {
          const state = stateFor(target.href);
          return (
            <ContextMenuItem
              key={target.href}
              disabled={locked || state === "no-access"}
              onClick={() => access.setLevel(ticketingNavTargetKey(target.href), state === "hidden" ? "full" : "hide")}
            >
              <span className="flex-1">{target.label}</span>
              <span className="text-xs text-muted-foreground">
                {state === "no-access" ? "No access" : state === "hidden" ? "Hidden" : "Shown"}
              </span>
            </ContextMenuItem>
          );
        })}
      </ContextMenuSub>
    </>
  );
}

/** Right-click a Ticketing navigation item to hide that page. */
export function TicketingNavTargetFrame({ href, children }: { href: string; children: ReactNode }) {
  const access = useRoleFieldAccessContext();
  if (!access?.canManageRoles) return <>{children}</>;

  return (
    <ContextMenu>
      <ContextMenuTrigger className="block select-text" title="Right-click to hide this page from the role" render={<div />}>
        {children}
      </ContextMenuTrigger>
      <ContextMenuContent className="min-w-[14rem]">
        <TicketingRoleMenuItems href={href} />
      </ContextMenuContent>
    </ContextMenu>
  );
}
