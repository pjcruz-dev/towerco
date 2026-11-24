"use client";

import { useQuery } from "@tanstack/react-query";
import { useEffect, useMemo, useState, type ReactNode } from "react";
import { usePathname } from "next/navigation";

import {
  RoleFieldAccessMenuItems,
  RoleFieldAccessProvider,
  RoleRowActionsMenuItems,
  useRoleFieldAccessContext,
} from "@/components/dynamic-entities/dyn-role-field-access";
import { ContextMenu, ContextMenuContent, ContextMenuItem, ContextMenuLabel, ContextMenuSeparator, ContextMenuSub, ContextMenuTrigger } from "@/components/ui/context-menu";
import { EyeOff, Lock } from "lucide-react";
import { usePermission } from "@/hooks/use-permission";
import { fetchAdminRoleCatalog, type AdminRoleRow } from "@/lib/api/modules/admin-roles-api";
import {
  CONTROLLED_DOCUMENT_ENTITY,
  CONTROLLED_DOCUMENT_LIST_FIELDS,
  DOCUMENT_REGISTER_NAV_TARGETS,
  DOCUMENT_REGISTER_VIEWING_AS_KEY,
  controlledDocumentAllows,
  documentRegisterHasHiddenNavTargets,
  documentRegisterNavTargetForPath,
  documentRegisterNavTargetHidden,
  documentRegisterNavTargetKey,
  documentRegisterPageListState,
  documentRegisterPreviewAllowsPath,
  filterNavGroupsForDocumentRegisterPreview,
} from "@/lib/documents/register-role-access";
import { fieldAccessLevel } from "@/lib/rbac/entity-access";
import { fieldEditableOnForm, fieldVisibleOnSurface, type FieldSurface } from "@/lib/rbac/field-surface-access";
import { permissions } from "@/lib/rbac/permissions";
import type { EntityAccessAction } from "@/lib/rbac/entity-access";
import { useAuthStore } from "@/stores/auth-store";
import { cn } from "@/lib/utils";

export function DocumentRegisterRoleAccessProvider({ children }: { children: ReactNode }) {
  return (
    <RoleFieldAccessProvider entitySlug={CONTROLLED_DOCUMENT_ENTITY} storageKey={DOCUMENT_REGISTER_VIEWING_AS_KEY}>
      {children}
    </RoleFieldAccessProvider>
  );
}

function previewRoleIsOwner(name: string | undefined): boolean {
  return name === "tenant_admin" || name === "administrator";
}

function readStoredRoleId(): number | null {
  if (typeof window === "undefined") return null;
  const raw = window.sessionStorage.getItem(DOCUMENT_REGISTER_VIEWING_AS_KEY);
  if (!raw) return null;
  const parsed = Number(raw);
  return Number.isFinite(parsed) ? parsed : null;
}

/** The Document Register preview role, including when the menu lives outside the page provider. */
export function useDocumentRegisterRolePreview() {
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
      if (detail?.storageKey !== DOCUMENT_REGISTER_VIEWING_AS_KEY) return;
      setRoleId(detail.roleId ?? null);
    }
    window.addEventListener("toweros:viewing-as", onPreview);
    return () => window.removeEventListener("toweros:viewing-as", onPreview);
  }, []);

  const selected = catalog.data?.roles.find((role) => String(role.id) === String(roleId)) ?? null;

  useEffect(() => {
    if (roleId != null) return;
    const stored = readStoredRoleId();
    if (stored != null) setRoleId(stored);
  }, [catalog.data, roleId]);
  const previewing = Boolean(selected) && !previewRoleIsOwner(selected?.name);
  const matrix = previewing ? selected?.access_matrix : accessMatrix;
  const filtering = previewing || documentRegisterHasHiddenNavTargets(matrix);

  return useMemo(() => {
    const allowsHref = (href: string) => {
      if (documentRegisterNavTargetHidden(matrix, href)) return false;
      if (!previewing || !selected) return true;
      return documentRegisterPreviewAllowsPath(selected.permissions, selected.access_matrix, href);
    };
    const allowsPermission = (permission: string) => {
      if (!previewing || !selected) return true;
      return selected.permissions.includes(permission);
    };
    return { active: filtering, allowsHref, allowsPermission, selected: selected as AdminRoleRow | null };
  }, [filtering, matrix, previewing, selected]);
}

export function filterDocumentRegisterPreviewNav<
  T extends { title: string; href?: string | null; items?: Array<{ href?: string | null }> },
>(
  groups: Array<{ group: string; items: T[] }>,
  allowsHref: (href: string) => boolean,
  active: boolean,
) {
  if (!active) return groups;
  return filterNavGroupsForDocumentRegisterPreview(groups, allowsHref);
}

/** Same menu on the page and in the side navigation. */
export function DocumentRegisterRoleMenuItems({ href }: { href?: string }) {
  return (
    <>
      <RoleRowActionsMenuItems
        columns={CONTROLLED_DOCUMENT_LIST_FIELDS}
        actions={[
          { action: "view", label: "View" },
          { action: "create", label: "Add new" },
          { action: "import", label: "Import" },
          { action: "export", label: "Export" },
        ]}
        always
      />
      <DocumentRegisterPagesMenuItems href={href} />
    </>
  );
}

export function DocumentRegisterAnywhereMenu({ className, children }: { className?: string; children: ReactNode }) {
  const access = useRoleFieldAccessContext();
  const pathname = usePathname();
  if (!access?.canManageRoles) return <>{children}</>;

  return (
    <ContextMenu>
      <ContextMenuTrigger className={cn("select-text", className)} render={<div />}>
        {children}
      </ContextMenuTrigger>
      <ContextMenuContent className="min-w-[14rem]">
        <DocumentRegisterRoleMenuItems href={pathname} />
      </ContextMenuContent>
    </ContextMenu>
  );
}

export function DocumentRegisterModuleFrame({ children }: { children: ReactNode }) {
  const pathname = usePathname();
  const gate = useDocumentRegisterRolePreview();
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

  return (
    <DocumentRegisterAnywhereMenu className="block min-h-[calc(100vh-8rem)]">{body}</DocumentRegisterAnywhereMenu>
  );
}

export function useDocumentRegisterAccess() {
  const preview = useRoleFieldAccessContext();
  const accessMatrix = useAuthStore((state) => state.user?.accessMatrix);
  const canManageRoles = usePermission([permissions.roleManage]);
  const previewing = Boolean(preview?.selected);
  const matrix = previewing ? preview?.selected?.access_matrix : accessMatrix;
  const configuring = canManageRoles && !previewing;

  return useMemo(
    () => ({
      configuring,
      previewing,
      visible(field: string, surface: FieldSurface) {
        if (configuring || field === "") return true;
        return fieldVisibleOnSurface(fieldAccessLevel(matrix, CONTROLLED_DOCUMENT_ENTITY, field), surface);
      },
      editable(field: string) {
        if (configuring || field === "") return true;
        return fieldEditableOnForm(fieldAccessLevel(matrix, CONTROLLED_DOCUMENT_ENTITY, field));
      },
      allows(action: EntityAccessAction) {
        if (configuring) return true;
        return controlledDocumentAllows(matrix, action);
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

/** Show or hide one Document Register page for the role in Viewing as. */
export function DocumentRegisterPagesMenuItems({ href }: { href?: string }) {
  const access = useRoleFieldAccessContext();
  if (!access?.canManageRoles) return null;

  const locked = pageLocked(access);
  const current = href ? documentRegisterNavTargetForPath(href) : null;
  const stateFor = (targetHref: string) =>
    access.selected
      ? documentRegisterPageListState(access.selected.permissions, access.selected.access_matrix, targetHref)
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
            access.setLevel(
              documentRegisterNavTargetKey(current.href),
              currentState === "hidden" ? "full" : "hide",
            )
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
        {DOCUMENT_REGISTER_NAV_TARGETS.map((target) => {
          const state = stateFor(target.href);
          return (
            <ContextMenuItem
              key={target.href}
              disabled={locked || state === "no-access"}
              onClick={() =>
                access.setLevel(documentRegisterNavTargetKey(target.href), state === "hidden" ? "full" : "hide")
              }
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

/** Right-click a Document register navigation item to hide that page. */
export function DocumentRegisterNavTargetFrame({ href, children }: { href: string; children: ReactNode }) {
  const access = useRoleFieldAccessContext();
  if (!access?.canManageRoles) return <>{children}</>;

  return (
    <ContextMenu>
      <ContextMenuTrigger className="block select-text" title="Right-click to hide this page from the role" render={<div />}>
        {children}
      </ContextMenuTrigger>
      <ContextMenuContent className="min-w-[14rem]">
        <DocumentRegisterRoleMenuItems href={href} />
      </ContextMenuContent>
    </ContextMenu>
  );
}

export function DocumentRegisterRoleFieldFrame({
  fieldName,
  fieldLabel,
  menu = "field",
  className,
  children,
}: {
  fieldName: string;
  fieldLabel: string;
  menu?: "field" | "actions";
  className?: string;
  children: ReactNode;
}) {
  const access = useRoleFieldAccessContext();
  const pathname = usePathname();
  if (!access?.canManageRoles) return <>{children}</>;

  return (
    <ContextMenu>
      <ContextMenuTrigger className={cn("block select-text", className)} title="Right-click to set what this role can see" render={<div />}>
        {children}
      </ContextMenuTrigger>
      <ContextMenuContent className="min-w-[14rem]">
        {menu === "actions" ? (
          <DocumentRegisterRoleMenuItems href={pathname} />
        ) : (
          <>
            <RoleFieldAccessMenuItems
              fieldName={fieldName}
              fieldLabel={fieldLabel}
              columns={CONTROLLED_DOCUMENT_LIST_FIELDS}
            />
            <DocumentRegisterPagesMenuItems href={pathname} />
          </>
        )}
      </ContextMenuContent>
    </ContextMenu>
  );
}
