"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Check, Eye, EyeOff, FileText, Lock, PenLine, Table2 } from "lucide-react";
import { createContext, useContext, useState, type ReactNode } from "react";

import {
  ContextMenuItem,
  ContextMenuLabel,
  ContextMenuSeparator,
  ContextMenuSub,
} from "@/components/ui/context-menu";
import { usePermission } from "@/hooks/use-permission";
import { getErrorMessage } from "@/lib/api/error";
import {
  fetchAdminRoleCatalog,
  updateAdminRole,
  type AdminRoleCatalog,
  type AdminRoleRow,
  type FieldAccessLevel,
  type RoleAccessMatrix,
} from "@/lib/api/modules/admin-roles-api";
import { canEntityAction, type EntityAccessAction } from "@/lib/rbac/entity-access";
import { FIELD_ACCESS_LEVELS, fieldVisibleOnSurface } from "@/lib/rbac/field-surface-access";
import { permissions } from "@/lib/rbac/permissions";
import { roleDisplayLabel } from "@/lib/rbac/role-display-labels";
import { notify } from "@/lib/ui/notify";
import { cn } from "@/lib/utils";

const ICONS: Record<FieldAccessLevel, typeof Lock> = {
  full: Lock,
  view: Eye,
  table_record: Table2,
  record: FileText,
  form: PenLine,
  hide: EyeOff,
};

const ICON_CLASS: Record<FieldAccessLevel, string> = {
  full: "text-emerald-600",
  view: "text-amber-500",
  table_record: "text-sky-600",
  record: "text-sky-600",
  form: "text-sky-600",
  hide: "text-destructive",
};

type RoleFieldAccessValue = {
  canManageRoles: boolean;
  roles: AdminRoleRow[];
  selected: AdminRoleRow | null;
  setRoleId: (roleId: number | null) => void;
  levelFor: (fieldName: string) => FieldAccessLevel;
  setLevel: (fieldName: string, level: FieldAccessLevel) => void;
  entityAllows: (action: EntityAccessAction) => boolean;
  setEntityAction: (action: EntityAccessAction, allowed: boolean) => void;
  saving: boolean;
};

const RoleFieldAccessContext = createContext<RoleFieldAccessValue | null>(null);

function ownerRole(role: AdminRoleRow): boolean {
  return role.name === "tenant_admin" || role.name === "administrator";
}

export function useRoleFieldAccessContext(): RoleFieldAccessValue | null {
  return useContext(RoleFieldAccessContext);
}

export function RoleFieldAccessProvider({
  entitySlug,
  children,
}: {
  entitySlug: string;
  children: ReactNode;
}) {
  const value = useRoleFieldAccess(entitySlug);
  return <RoleFieldAccessContext.Provider value={value}>{children}</RoleFieldAccessContext.Provider>;
}

function useRoleFieldAccess(entitySlug: string): RoleFieldAccessValue {
  const canManageRoles = usePermission([permissions.roleManage]);
  const queryClient = useQueryClient();
  const [roleId, setRoleId] = useState<number | null>(null);
  const catalog = useQuery({
    queryKey: ["admin", "roles"],
    queryFn: fetchAdminRoleCatalog,
    enabled: canManageRoles && entitySlug !== "",
    staleTime: 30_000,
  });
  const roles = catalog.data?.roles ?? [];
  const selected = roles.find((role) => role.id === roleId) ?? null;

  const save = useMutation({
    mutationFn: async (input: { role: AdminRoleRow; matrix: RoleAccessMatrix }) => {
      return updateAdminRole(input.role.id, input.role.permissions, input.matrix);
    },
    onSuccess: () => {
      notify.success("Field access saved", "This role now uses the access you set.");
    },
    onError: (error) => {
      notify.error("Could not save field access", getErrorMessage(error));
      void queryClient.invalidateQueries({ queryKey: ["admin", "roles"] });
    },
  });

  function levelFor(fieldName: string): FieldAccessLevel {
    if (!selected || entitySlug === "") return "full";
    return selected.access_matrix?.fields?.[entitySlug]?.[fieldName] ?? "full";
  }

  function setLevel(fieldName: string, level: FieldAccessLevel) {
    if (!selected || entitySlug === "" || ownerRole(selected) || selected.permissions.length === 0) return;
    if (levelFor(fieldName) === level) return;
    const matrix: RoleAccessMatrix = {
      ...(selected.access_matrix ?? {}),
      fields: {
        ...(selected.access_matrix?.fields ?? {}),
        [entitySlug]: {
          ...(selected.access_matrix?.fields?.[entitySlug] ?? {}),
          [fieldName]: level,
        },
      },
    };
    queryClient.setQueryData<AdminRoleCatalog>(["admin", "roles"], (current) => {
      if (!current) return current;
      return {
        ...current,
        roles: current.roles.map((role) =>
          role.id === selected.id ? { ...role, access_matrix: matrix } : role,
        ),
      };
    });
    save.mutate({ role: selected, matrix });
  }

  function entityAllows(action: EntityAccessAction): boolean {
    if (!selected || entitySlug === "") return true;
    return canEntityAction(selected.access_matrix, entitySlug, action);
  }

  function setEntityAction(action: EntityAccessAction, allowed: boolean) {
    if (!selected || entitySlug === "" || ownerRole(selected) || selected.permissions.length === 0) return;
    if (entityAllows(action) === allowed) return;
    const current = selected.access_matrix?.entities?.[entitySlug];
    const row = {
      view: true,
      view_own: false,
      create: true,
      edit: true,
      delete: true,
      export: true,
      import: true,
      smart_scan: true,
      undo: true,
      truncate: true,
      duplicate: true,
      ...(current ?? {}),
      [action]: allowed,
    };
    if (action === "view") row.view_own = false;
    if (action === "view_own" && allowed) row.view = false;
    const matrix: RoleAccessMatrix = {
      ...(selected.access_matrix ?? {}),
      entities: {
        ...(selected.access_matrix?.entities ?? {}),
        [entitySlug]: row,
      },
    };
    queryClient.setQueryData<AdminRoleCatalog>(["admin", "roles"], (currentCatalog) => {
      if (!currentCatalog) return currentCatalog;
      return {
        ...currentCatalog,
        roles: currentCatalog.roles.map((role) =>
          role.id === selected.id ? { ...role, access_matrix: matrix } : role,
        ),
      };
    });
    save.mutate({ role: selected, matrix });
  }

  return {
    canManageRoles,
    roles,
    selected,
    setRoleId,
    levelFor,
    setLevel,
    entityAllows,
    setEntityAction,
    saving: save.isPending,
  };
}

const quietSelectClass =
  "h-8 max-w-[12rem] rounded-md border border-border bg-background px-2 text-sm text-foreground outline-none focus-visible:ring-2 focus-visible:ring-ring";

/** Compact role preview. Sits in a toolbar, not its own banner. */
export function ViewingAsSelect() {
  const access = useRoleFieldAccessContext();
  if (!access?.canManageRoles) return null;

  return (
    <label className="inline-flex items-center gap-2 text-xs text-muted-foreground">
      <span className="whitespace-nowrap">Viewing as</span>
      <select
        id="dyn-viewing-as-role"
        className={quietSelectClass}
        title={
          access.selected && ownerRole(access.selected)
            ? "The workspace owner stays unrestricted."
            : "Preview as this role. Select a field to set its access."
        }
        value={access.selected?.id ?? ""}
        onChange={(event) => {
          const next = event.target.value;
          access.setRoleId(next === "" ? null : Number(next));
        }}
      >
        <option value="">My own access</option>
        {access.roles.map((role) => (
          <option key={role.id} value={role.id}>
            {roleDisplayLabel(role.name)}
          </option>
        ))}
      </select>
    </label>
  );
}

export function ViewingAsRoleBar() {
  return <ViewingAsSelect />;
}

/** Role list for the field and group context menu. */
export function ViewingAsMenuItems() {
  const access = useRoleFieldAccessContext();
  if (!access?.canManageRoles) return null;

  const current = access.selected ? roleDisplayLabel(access.selected.name) : "My own access";

  return (
    <ContextMenuSub
      label={
        <>
          <span>Viewing as</span>
          <span className="ml-auto max-w-28 truncate text-xs text-muted-foreground">{current}</span>
        </>
      }
    >
      <ContextMenuItem onClick={() => access.setRoleId(null)}>
        <span className="flex-1">My own access</span>
        {access.selected ? null : <Check className="size-3.5 text-emerald-600" aria-label="Selected" />}
      </ContextMenuItem>
      {access.roles.map((role) => {
        const selected = access.selected?.id === role.id;
        return (
          <ContextMenuItem key={role.id} onClick={() => access.setRoleId(role.id)}>
            <span className="flex-1">{roleDisplayLabel(role.name)}</span>
            {selected ? <Check className="size-3.5 text-emerald-600" aria-label="Selected" /> : null}
          </ContextMenuItem>
        );
      })}
    </ContextMenuSub>
  );
}

/** Quiet reminder while a role preview is on. Hidden for your own access. */
export function ViewingAsStatus() {
  const access = useRoleFieldAccessContext();
  if (!access?.selected) return null;
  return (
    <p className="text-xs text-muted-foreground">
      Viewing as <span className="font-medium text-foreground">{roleDisplayLabel(access.selected.name)}</span>
    </p>
  );
}

export function RoleAllColumnsMenuItems({
  columns,
}: {
  columns: ReadonlyArray<{ id: string; label: string }>;
}) {
  const access = useRoleFieldAccessContext();
  if (!access?.canManageRoles || !access.selected || columns.length === 0) return null;
  const locked = ownerRole(access.selected) || access.selected.permissions.length === 0 || access.saving;

  return (
    <ContextMenuSub label="Columns">
      {columns.map((column) => {
        const shown = fieldVisibleOnSurface(access.levelFor(column.id), "table");
        return (
          <ContextMenuItem
            key={column.id}
            disabled={locked}
            onClick={() => access.setLevel(column.id, shown ? "hide" : "full")}
          >
            <span className="flex-1 truncate">{column.label}</span>
            <span className="text-xs text-muted-foreground">{shown ? "Shown" : "Hidden"}</span>
          </ContextMenuItem>
        );
      })}
    </ContextMenuSub>
  );
}

export function RoleFieldAccessMenuItems({
  fieldName,
  fieldLabel,
  columns = [],
}: {
  fieldName: string;
  fieldLabel: string;
  columns?: ReadonlyArray<{ id: string; label: string }>;
}) {
  const access = useRoleFieldAccessContext();
  if (!access?.canManageRoles) return null;

  if (!access.selected) {
    return <ViewingAsMenuItems />;
  }

  const roleName = roleDisplayLabel(access.selected.name);
  const level = access.levelFor(fieldName);
  const locked = ownerRole(access.selected) || access.selected.permissions.length === 0 || access.saving;

  return (
    <>
      <ViewingAsMenuItems />
      <ContextMenuSeparator />
      <ContextMenuLabel className="uppercase tracking-wide">
        {roleName} · {fieldLabel}
      </ContextMenuLabel>
      {FIELD_ACCESS_LEVELS.map((option) => {
        const Icon = ICONS[option.value];
        const selected = level === option.value;
        return (
          <ContextMenuItem
            key={option.value}
            disabled={locked}
            onClick={() => access.setLevel(fieldName, option.value)}
          >
            <Icon className={cn("size-3.5", ICON_CLASS[option.value])} />
            <span className="flex-1">{option.label}</span>
            {selected ? <Check className="size-3.5 text-emerald-600" aria-label="Selected" /> : null}
          </ContextMenuItem>
        );
      })}
      {columns.length > 0 ? (
        <>
          <ContextMenuSeparator />
          <RoleAllColumnsMenuItems columns={columns} />
        </>
      ) : null}
    </>
  );
}

/** Right-click the Actions column to show or hide view, edit, clone, and delete for the preview role. */
export function RoleRowActionsMenuItems({
  columns = [],
}: {
  columns?: ReadonlyArray<{ id: string; label: string }>;
} = {}) {
  const access = useRoleFieldAccessContext();
  if (!access?.canManageRoles) return null;
  if (!access.selected) return <ViewingAsMenuItems />;

  const roleName = roleDisplayLabel(access.selected.name);
  const locked = ownerRole(access.selected) || access.selected.permissions.length === 0 || access.saving;
  const actions: Array<{ action: EntityAccessAction; label: string }> = [
    { action: "view", label: "View" },
    { action: "edit", label: "Edit" },
    { action: "duplicate", label: "Clone" },
    { action: "delete", label: "Delete" },
    { action: "create", label: "Add new" },
    { action: "import", label: "Import" },
    { action: "columns", label: "Table view" },
  ];

  return (
    <>
      <ViewingAsMenuItems />
      <ContextMenuSeparator />
      <ContextMenuLabel className="uppercase tracking-wide">{roleName} · Actions</ContextMenuLabel>
      {actions.map((item) => {
        const allowed = access.entityAllows(item.action);
        return (
          <ContextMenuItem
            key={item.action}
            disabled={locked}
            onClick={() => access.setEntityAction(item.action, !allowed)}
          >
            {allowed ? (
              <Lock className="size-3.5 text-emerald-600" />
            ) : (
              <EyeOff className="size-3.5 text-destructive" />
            )}
            <span className="flex-1">{item.label}</span>
            <span className="text-xs text-muted-foreground">{allowed ? "Shown" : "Hidden"}</span>
          </ContextMenuItem>
        );
      })}
      {columns.length > 0 ? (
        <>
          <ContextMenuSeparator />
          <RoleAllColumnsMenuItems columns={columns} />
        </>
      ) : null}
    </>
  );
}

/** Right-click an action (view, edit, clone, delete) to show or hide it for the preview role. */
export function RoleEntityActionMenuItems({
  action,
  label,
}: {
  action: EntityAccessAction;
  label: string;
}) {
  const access = useRoleFieldAccessContext();
  if (!access?.canManageRoles) return null;
  if (!access.selected) {
    return <ViewingAsMenuItems />;
  }

  const roleName = roleDisplayLabel(access.selected.name);
  const allowed = access.entityAllows(action);
  const locked = ownerRole(access.selected) || access.selected.permissions.length === 0 || access.saving;

  return (
    <>
      <ViewingAsMenuItems />
      <ContextMenuSeparator />
      <ContextMenuLabel className="uppercase tracking-wide">
        {roleName} · {label}
      </ContextMenuLabel>
      <ContextMenuItem disabled={locked} onClick={() => access.setEntityAction(action, true)}>
        <Lock className="size-3.5 text-emerald-600" />
        <span className="flex-1">Full access</span>
        {allowed ? <Check className="size-3.5 text-emerald-600" aria-label="Selected" /> : null}
      </ContextMenuItem>
      <ContextMenuItem disabled={locked} onClick={() => access.setEntityAction(action, false)}>
        <EyeOff className="size-3.5 text-destructive" />
        <span className="flex-1">Hide from this role</span>
        {allowed ? null : <Check className="size-3.5 text-emerald-600" aria-label="Selected" />}
      </ContextMenuItem>
    </>
  );
}

export function RoleFieldAccessBadge({ fieldName }: { fieldName: string }) {
  const access = useRoleFieldAccessContext();
  if (!access?.selected) return null;
  const level = access.levelFor(fieldName);
  if (level === "full") return null;
  const label = FIELD_ACCESS_LEVELS.find((option) => option.value === level)?.label ?? level;
  return (
    <span className="ms-1.5 rounded-md bg-muted px-1.5 py-0.5 text-[10px] font-medium normal-case tracking-normal text-muted-foreground">
      {label}
    </span>
  );
}
