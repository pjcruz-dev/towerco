"use client";

import { useMemo, type ReactNode } from "react";
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
import {
  E_APPROVAL_LIST_FIELDS,
  E_APPROVAL_NAV_TARGETS,
  E_APPROVAL_SUBMISSION_ENTITY,
  E_APPROVAL_VIEWING_AS_KEY,
  eApprovalEntityAllows,
  eApprovalHasHiddenNavTargets,
  eApprovalNavTargetForPath,
  eApprovalNavTargetHidden,
  eApprovalNavTargetKey,
  eApprovalPreviewAllowsPath,
  filterNavGroupsForEApprovalPreview,
} from "@/lib/e-approval/submission-role-access";
import { fieldAccessLevel } from "@/lib/rbac/entity-access";
import { fieldEditableOnForm, fieldVisibleOnSurface, type FieldSurface } from "@/lib/rbac/field-surface-access";
import { permissions } from "@/lib/rbac/permissions";
import type { EntityAccessAction } from "@/lib/rbac/entity-access";
import { useAuthStore } from "@/stores/auth-store";
import { cn } from "@/lib/utils";

export function EApprovalRoleAccessProvider({ children }: { children: ReactNode }) {
  return (
    <RoleFieldAccessProvider entitySlug={E_APPROVAL_SUBMISSION_ENTITY} storageKey={E_APPROVAL_VIEWING_AS_KEY}>
      {children}
    </RoleFieldAccessProvider>
  );
}

function previewRoleIsOwner(name: string | undefined): boolean {
  return name === "tenant_admin" || name === "administrator";
}

/** The role selected in Viewing as. Inactive until an admin picks a role that is not the workspace owner. */
export function useEApprovalRolePreview() {
  const preview = useRoleFieldAccessContext();
  const accessMatrix = useAuthStore((state) => state.user?.accessMatrix);
  const selected = preview?.selected ?? null;
  const previewing = Boolean(selected) && !previewRoleIsOwner(selected?.name);
  const matrix = previewing ? selected?.access_matrix : accessMatrix;
  const filtering = previewing || eApprovalHasHiddenNavTargets(matrix);

  return useMemo(() => {
    const allowsHref = (href: string) => {
      if (eApprovalNavTargetHidden(matrix, href)) return false;
      if (!previewing || !selected) return true;
      return eApprovalPreviewAllowsPath(selected.permissions, selected.access_matrix, href);
    };
    const allowsPermission = (permission: string) => {
      if (!previewing || !selected) return true;
      return selected.permissions.includes(permission);
    };
    const allowsAny = (required: string[]) => {
      if (!previewing || !selected) return true;
      return required.some((permission) => selected.permissions.includes(permission));
    };
    return { active: filtering, allowsHref, allowsPermission, allowsAny };
  }, [filtering, matrix, previewing, selected]);
}

export function filterEApprovalPreviewNav<
  T extends { title: string; href?: string | null; items?: Array<{ href?: string | null }> },
>(
  groups: Array<{ group: string; items: T[] }>,
  allowsHref: (href: string) => boolean,
  active: boolean,
) {
  if (!active) return groups;
  return filterNavGroupsForEApprovalPreview(groups, allowsHref);
}

/** Same menu on the page and in the side navigation. */
export function EApprovalRoleMenuItems({ href }: { href?: string }) {
  return (
    <>
      <RoleRowActionsMenuItems columns={E_APPROVAL_LIST_FIELDS} always />
      <EApprovalPagesMenuItems href={href} />
    </>
  );
}

/** Right-click anywhere in this region to choose the preview role and what it can do. */
export function EApprovalAnywhereMenu({ className, children }: { className?: string; children: ReactNode }) {
  const access = useRoleFieldAccessContext();
  const pathname = usePathname();
  if (!access?.canManageRoles) return <>{children}</>;

  return (
    <ContextMenu>
      <ContextMenuTrigger className={cn("select-text", className)} render={<div />}>
        {children}
      </ContextMenuTrigger>
      <ContextMenuContent className="min-w-[14rem]">
        <EApprovalRoleMenuItems href={pathname} />
      </ContextMenuContent>
    </ContextMenu>
  );
}

/** Right-click the page to preview a role. Direct visits the role cannot open stay blocked. */
export function EApprovalModuleFrame({ children }: { children: ReactNode }) {
  const pathname = usePathname();
  const gate = useEApprovalRolePreview();
  const print = pathname.includes("/print");
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

  if (print) return <div className="space-y-4">{body}</div>;

  return (
    <EApprovalAnywhereMenu className="block min-h-[calc(100vh-8rem)] space-y-4">{body}</EApprovalAnywhereMenu>
  );
}

export function useEApprovalSubmissionAccess() {
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
      canManageRoles,
      visible(field: string, surface: FieldSurface) {
        if (configuring || field === "") return true;
        return fieldVisibleOnSurface(fieldAccessLevel(matrix, E_APPROVAL_SUBMISSION_ENTITY, field), surface);
      },
      editable(field: string) {
        if (configuring || field === "") return true;
        return fieldEditableOnForm(fieldAccessLevel(matrix, E_APPROVAL_SUBMISSION_ENTITY, field));
      },
      allows(action: EntityAccessAction) {
        if (configuring) return true;
        return eApprovalEntityAllows(matrix, action);
      },
    }),
    [canManageRoles, configuring, matrix, previewing],
  );
}

function pageLocked(access: NonNullable<ReturnType<typeof useRoleFieldAccessContext>>): boolean {
  const selected = access.selected;
  if (!selected) return true;
  return selected.name === "tenant_admin" || selected.name === "administrator" || selected.permissions.length === 0 || access.saving;
}

/** Show or hide one E-Forms page for the role in Viewing as. Hidden pages leave the nav and reject the URL. */
export function EApprovalPagesMenuItems({ href }: { href?: string }) {
  const access = useRoleFieldAccessContext();
  if (!access?.canManageRoles) return null;

  const locked = pageLocked(access);
  const current = href ? eApprovalNavTargetForPath(href) : null;
  const currentHidden = current ? access.levelFor(eApprovalNavTargetKey(current.href)) === "hide" : false;

  return (
    <>
      <ContextMenuSeparator />
      <ContextMenuLabel className="uppercase tracking-wide">Pages</ContextMenuLabel>
      {current ? (
        <ContextMenuItem
          disabled={locked}
          onClick={() => access.setLevel(eApprovalNavTargetKey(current.href), currentHidden ? "full" : "hide")}
        >
          {currentHidden ? <EyeOff className="size-3.5 text-destructive" /> : <Lock className="size-3.5 text-emerald-600" />}
          <span className="flex-1">{currentHidden ? `Show ${current.label}` : `Hide ${current.label}`}</span>
          <span className="text-xs text-muted-foreground">{currentHidden ? "Hidden" : "Shown"}</span>
        </ContextMenuItem>
      ) : null}
      <ContextMenuSub label="All pages">
        {E_APPROVAL_NAV_TARGETS.map((target) => {
          const hidden = access.levelFor(eApprovalNavTargetKey(target.href)) === "hide";
          return (
            <ContextMenuItem
              key={target.href}
              disabled={locked}
              onClick={() => access.setLevel(eApprovalNavTargetKey(target.href), hidden ? "full" : "hide")}
            >
              <span className="flex-1">{target.label}</span>
              <span className="text-xs text-muted-foreground">{hidden ? "Hidden" : "Shown"}</span>
            </ContextMenuItem>
          );
        })}
      </ContextMenuSub>
    </>
  );
}

/** Right-click a navigation item to hide that page from the role you are viewing as. */
export function EApprovalNavTargetFrame({
  href,
  children,
}: {
  href: string;
  children: ReactNode;
}) {
  const access = useRoleFieldAccessContext();
  if (!access?.canManageRoles) return <>{children}</>;

  return (
    <ContextMenu>
      <ContextMenuTrigger className="block select-text" title="Right-click to hide this page from the role" render={<div />}>
        {children}
      </ContextMenuTrigger>
      <ContextMenuContent className="min-w-[14rem]">
        <EApprovalRoleMenuItems href={href} />
      </ContextMenuContent>
    </ContextMenu>
  );
}

export function EApprovalRoleFieldFrame({
  fieldName,
  fieldLabel,
  columns = [],
  menu = "field",
  className,
  children,
}: {
  fieldName: string;
  fieldLabel: string;
  columns?: ReadonlyArray<{ id: string; label: string }>;
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
          <EApprovalRoleMenuItems href={pathname} />
        ) : (
          <>
            <RoleFieldAccessMenuItems fieldName={fieldName} fieldLabel={fieldLabel} columns={columns} />
            <EApprovalPagesMenuItems href={pathname} />
          </>
        )}
      </ContextMenuContent>
    </ContextMenu>
  );
}
