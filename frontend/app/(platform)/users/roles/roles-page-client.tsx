"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import { Plus, Shield, Trash2, Users } from "lucide-react";

import { RoleAccessMatrixPanels } from "@/components/admin/role-access-matrix-panels";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import { useAdminRoleCatalog } from "@/hooks/use-admin-role-catalog";
import { getErrorMessage } from "@/lib/api/error";
import {
  cloneAdminRole,
  createAdminRole,
  deleteAdminRole,
  suggestRoleCloneName,
  updateAdminRole,
  type AdminRoleRow,
  type RoleAccessMatrix,
} from "@/lib/api/modules/admin-roles-api";
import {
  fetchDynEntities,
  fetchDynEntity,
  type DynEntityDetail,
  type DynEntitySummary,
} from "@/lib/api/modules/dynamic-entities-api";
import { permissions } from "@/lib/rbac/permissions";
import { roleDisplayLabel } from "@/lib/rbac/role-display-labels";
import { GENERAL_SYSTEM_PERMISSIONS } from "@/lib/rbac/role-management-layout";
import { ATC_OPERATIONAL_ROLE_ORDER, filterRolesForEnabledModules } from "@/lib/rbac/role-groups";
import { useNotificationStore } from "@/stores/notification-store";
import { cn } from "@/lib/utils";

type PermTab = "general" | "data" | "fields" | "workflow";

function roleLabel(name: string): string {
  return roleDisplayLabel(name);
}

function sortRolesForList(roles: AdminRoleRow[]): AdminRoleRow[] {
  const order = new Map(ATC_OPERATIONAL_ROLE_ORDER.map((name, index) => [name, index]));
  return [...roles].sort((a, b) => {
    const ai = order.has(a.name) ? order.get(a.name)! : 1000;
    const bi = order.has(b.name) ? order.get(b.name)! : 1000;
    if (ai !== bi) return ai - bi;
    return roleLabel(a.name).localeCompare(roleLabel(b.name));
  });
}

export function RolesPageClient() {
  const queryClient = useQueryClient();
  const notify = useNotificationStore((state) => state.push);
  const catalogQuery = useAdminRoleCatalog();

  const enabledModules = catalogQuery.data?.enabled_modules;
  const roles = useMemo(
    () =>
      sortRolesForList(
        filterRolesForEnabledModules(catalogQuery.data?.roles ?? [], {
          enabledModules,
        }),
      ),
    [catalogQuery.data?.roles, enabledModules],
  );
  const allPermissions = catalogQuery.data?.permissions ?? [];
  const permissionSet = useMemo(() => new Set(allPermissions), [allPermissions]);

  const [selectedRoleId, setSelectedRoleId] = useState<number | null>(null);
  const [draftPermissions, setDraftPermissions] = useState<string[]>([]);
  const [draftMatrix, setDraftMatrix] = useState<RoleAccessMatrix>({});
  const [permTab, setPermTab] = useState<PermTab>("general");
  const [permFilter, setPermFilter] = useState("");
  const [dirty, setDirty] = useState(false);
  const [createOpen, setCreateOpen] = useState(false);
  const [createName, setCreateName] = useState("");
  const [dynEntities, setDynEntities] = useState<DynEntitySummary[]>([]);
  const [entityDetails, setEntityDetails] = useState<Record<string, DynEntityDetail | undefined>>({});

  const selectedRole = roles.find((r) => r.id === selectedRoleId) ?? null;
  const canEditSelected = Boolean(selectedRole && !selectedRole.is_system);

  useEffect(() => {
    if (roles.length === 0) {
      setSelectedRoleId(null);
      return;
    }
    if (selectedRoleId === null || !roles.some((r) => r.id === selectedRoleId)) {
      setSelectedRoleId(roles[0]!.id);
    }
  }, [roles, selectedRoleId]);

  useEffect(() => {
    if (!selectedRole) {
      setDraftPermissions([]);
      setDraftMatrix({});
      setDirty(false);
      return;
    }
    setDraftPermissions([...selectedRole.permissions]);
    setDraftMatrix(selectedRole.access_matrix ?? {});
    setDirty(false);
  }, [selectedRole?.id, selectedRole?.permissions, selectedRole?.access_matrix]);

  useEffect(() => {
    fetchDynEntities({ active_only: true })
      .then(setDynEntities)
      .catch(() => setDynEntities([]));
  }, []);

  function loadEntityDetail(slug: string) {
    if (entityDetails[slug]) return;
    fetchDynEntity(slug)
      .then((detail) => setEntityDetails((prev) => ({ ...prev, [slug]: detail })))
      .catch(() => undefined);
  }

  const invalidate = () => {
    void queryClient.invalidateQueries({ queryKey: ["admin", "roles"] });
  };

  const createMutation = useMutation({
    mutationFn: createAdminRole,
    onSuccess: (created) => {
      invalidate();
      setCreateOpen(false);
      setCreateName("");
      setSelectedRoleId(created.id);
      notify({ level: "success", title: "Role created", message: "Custom role is ready to assign." });
    },
    onError: (error) => {
      notify({ level: "error", title: "Create failed", message: getErrorMessage(error) });
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({
      roleId,
      permissions: perms,
      accessMatrix,
    }: {
      roleId: number;
      permissions: string[];
      accessMatrix: RoleAccessMatrix;
    }) => updateAdminRole(roleId, perms, accessMatrix),
    onSuccess: (_data, variables) => {
      void queryClient.invalidateQueries({ queryKey: ["admin", "roles"] });
      setDirty(false);
      setDraftPermissions(variables.permissions);
      setDraftMatrix(variables.accessMatrix);
      notify({
        level: "success",
        title: "Permission updated successfully",
        message: "Role access saved.",
      });
    },
    onError: (error) => {
      notify({ level: "error", title: "Save failed", message: getErrorMessage(error) });
      if (selectedRole) {
        setDraftPermissions([...selectedRole.permissions]);
        setDraftMatrix(selectedRole.access_matrix ?? {});
        setDirty(false);
      }
    },
  });

  const cloneMutation = useMutation({
    mutationFn: ({ roleId, roleName }: { roleId: number; roleName: string }) =>
      cloneAdminRole(roleId, roleName),
    onSuccess: (created) => {
      invalidate();
      setSelectedRoleId(created.id);
      notify({
        level: "success",
        title: "Role cloned",
        message: `${roleLabel(created.name)} is ready to customize.`,
      });
    },
    onError: (error) => {
      notify({ level: "error", title: "Clone failed", message: getErrorMessage(error) });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: deleteAdminRole,
    onSuccess: () => {
      invalidate();
      setSelectedRoleId(null);
      notify({ level: "success", title: "Role deleted", message: "Custom role removed." });
    },
    onError: (error) => {
      notify({ level: "error", title: "Delete failed", message: getErrorMessage(error) });
    },
  });

  function persist(nextPermissions: string[], nextMatrix: RoleAccessMatrix) {
    if (!selectedRole || !canEditSelected) return;
    setDraftPermissions(nextPermissions);
    setDraftMatrix(nextMatrix);
    setDirty(false);
    updateMutation.mutate({
      roleId: selectedRole.id,
      permissions: nextPermissions,
      accessMatrix: nextMatrix,
    });
  }

  function persistPermissions(next: string[]) {
    persist(next, draftMatrix);
  }

  function persistMatrix(next: RoleAccessMatrix) {
    persist(draftPermissions, next);
  }

  function togglePermission(permission: string) {
    if (!canEditSelected || !permissionSet.has(permission) || updateMutation.isPending) return;
    const next = draftPermissions.includes(permission)
      ? draftPermissions.filter((p) => p !== permission)
      : [...draftPermissions, permission];
    persistPermissions(next);
  }

  function confirmDelete(role: AdminRoleRow) {
    if (role.is_system || role.is_baseline) {
      notify({
        level: "warning",
        title: "Protected role",
        message: "System and core baseline roles cannot be deleted. Clone to customize.",
      });
      return;
    }
    if (role.user_count > 0) {
      notify({
        level: "warning",
        title: "Cannot delete role",
        message: `Reassign ${role.user_count} user(s) before deleting this role.`,
      });
      return;
    }
    if (window.confirm(`Delete role "${roleLabel(role.name)}"? This cannot be undone.`)) {
      deleteMutation.mutate(role.id);
    }
  }

  const filterNeedle = permFilter.trim().toLowerCase();

  const generalCards = useMemo(() => {
    return GENERAL_SYSTEM_PERMISSIONS.filter((card) => permissionSet.has(card.permission)).filter(
      (card) =>
        !filterNeedle ||
        card.title.toLowerCase().includes(filterNeedle) ||
        card.permission.toLowerCase().includes(filterNeedle),
    );
  }, [permissionSet, filterNeedle]);

  const tabs: Array<{ id: PermTab; label: string }> = [
    { id: "general", label: "General System" },
    { id: "data", label: "Data Access (Entities)" },
    { id: "fields", label: "Field Level Security" },
    { id: "workflow", label: "Workflow Approvals" },
  ];

  return (
    <PermissionGate requiredPermissions={[permissions.roleManage]}>
      <div className="space-y-4">
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight text-foreground">Role Management</h1>
            <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
              Define permissions and access controls for system roles.
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <Link
              href="/users"
              prefetch={false}
              className={cn(
                "inline-flex h-8 items-center justify-center gap-2 rounded-md border border-border bg-background px-3 text-sm font-medium shadow-sm hover:bg-muted",
              )}
            >
              Back to users
            </Link>
            <Button
              type="button"
              size="sm"
              onClick={() => {
                setCreateName("");
                setCreateOpen(true);
              }}
            >
              <Plus className="size-3.5" />
              Create New Role
            </Button>
          </div>
        </header>

        {catalogQuery.isError ? (
          <p className="text-sm text-destructive">
            Could not load roles. {getErrorMessage(catalogQuery.error)}
          </p>
        ) : null}

        <div className="grid min-h-[34rem] overflow-hidden rounded-xl border border-border bg-card shadow-sm lg:grid-cols-[minmax(16rem,20rem)_1fr]">
          {/* Available roles */}
          <aside className="flex flex-col border-b border-border lg:border-b-0 lg:border-r">
            <div className="flex items-center gap-2 border-b border-border px-4 py-3">
              <Users className="size-4 text-muted-foreground" />
              <h2 className="text-sm font-medium text-foreground">Available Roles</h2>
            </div>
            <div className="flex-1 overflow-y-auto p-2">
              {catalogQuery.isLoading ? (
                <p className="px-2 py-6 text-center text-sm text-muted-foreground">Loading roles…</p>
              ) : roles.length === 0 ? (
                <p className="px-2 py-6 text-center text-sm text-muted-foreground">No roles found.</p>
              ) : (
                <ul className="space-y-0.5">
                  {roles.map((role) => {
                    const active = role.id === selectedRoleId;
                    return (
                      <li key={role.id}>
                        <div
                          className={cn(
                            "group flex items-center gap-1 rounded-lg",
                            active ? "bg-sky-50 dark:bg-sky-950/40" : "hover:bg-muted/60",
                          )}
                        >
                          <button
                            type="button"
                            className="min-w-0 flex-1 px-3 py-2.5 text-left"
                            onClick={() => {
                              if (dirty && !window.confirm("Discard unsaved permission changes?")) {
                                return;
                              }
                              setSelectedRoleId(role.id);
                            }}
                          >
                            <div className="flex items-center gap-2">
                              <span
                                className={cn(
                                  "truncate text-sm font-medium capitalize",
                                  active ? "text-sky-900 dark:text-sky-100" : "text-foreground",
                                )}
                              >
                                {roleLabel(role.name)}
                              </span>
                              {role.is_baseline ? (
                                <Shield
                                  className="size-3.5 shrink-0 text-amber-500"
                                  aria-label="Protected role"
                                />
                              ) : null}
                            </div>
                            <p className="mt-0.5 text-[11px] text-muted-foreground">
                              {role.user_count} user{role.user_count === 1 ? "" : "s"}
                            </p>
                          </button>
                          {!role.is_system && !role.is_baseline ? (
                            <button
                              type="button"
                              className="mr-2 rounded p-1.5 text-muted-foreground opacity-0 hover:bg-destructive/10 hover:text-destructive group-hover:opacity-100"
                              title="Delete role"
                              onClick={() => confirmDelete(role)}
                            >
                              <Trash2 className="size-3.5" />
                            </button>
                          ) : null}
                        </div>
                      </li>
                    );
                  })}
                </ul>
              )}
            </div>
          </aside>

          {/* Permissions pane */}
          <section className="flex min-w-0 flex-col">
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border px-4 py-3">
              <div>
                <h2 className="text-sm font-medium text-foreground">
                  Permissions for{" "}
                  <span className="capitalize">{selectedRole ? roleLabel(selectedRole.name) : "…"}</span>
                </h2>
                {selectedRole?.is_system ? (
                  <p className="mt-0.5 text-xs text-muted-foreground">
                    Protected system role — clone to customize permissions.
                  </p>
                ) : (
                  <p className="mt-0.5 text-xs text-muted-foreground">
                    Changes save automatically when you toggle a permission.
                  </p>
                )}
              </div>
              <div className="flex flex-wrap items-center gap-2">
                <Input
                  value={permFilter}
                  onChange={(e) => setPermFilter(e.target.value)}
                  placeholder="Filter permissions…"
                  className="h-8 w-48 text-xs"
                />
                {selectedRole?.is_system ? (
                  <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={!selectedRole || cloneMutation.isPending}
                    onClick={() => {
                      if (!selectedRole) return;
                      cloneMutation.mutate({
                        roleId: selectedRole.id,
                        roleName: suggestRoleCloneName(selectedRole.name),
                      });
                    }}
                  >
                    Clone role
                  </Button>
                ) : null}
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  disabled={!canEditSelected || updateMutation.isPending || !selectedRole}
                  onClick={() => {
                    if (!selectedRole) return;
                    persist(draftPermissions, draftMatrix);
                  }}
                >
                  {updateMutation.isPending ? "Saving…" : "Save"}
                </Button>
              </div>
            </div>

            <div className="flex flex-wrap gap-1 border-b border-border px-4 pt-2">
              {tabs.map((tab) => (
                <button
                  key={tab.id}
                  type="button"
                  onClick={() => setPermTab(tab.id)}
                  className={cn(
                    "border-b-2 px-3 py-2 text-xs font-medium transition-colors",
                    permTab === tab.id
                      ? "border-sky-600 text-sky-700 dark:text-sky-300"
                      : "border-transparent text-muted-foreground hover:text-foreground",
                  )}
                >
                  {tab.label}
                </button>
              ))}
            </div>

            <div className="flex-1 overflow-y-auto p-4">
              {!selectedRole ? (
                <p className="text-sm text-muted-foreground">Select a role to manage permissions.</p>
              ) : null}

              {selectedRole && permTab === "general" ? (
                <div className="space-y-3">
                  <p className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                    Global permissions
                  </p>
                  {generalCards.length === 0 ? (
                    <p className="text-sm text-muted-foreground">No matching permissions.</p>
                  ) : (
                    <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                      {generalCards.map((card) => {
                        const checked = draftPermissions.includes(card.permission);
                        return (
                          <label
                            key={card.permission}
                            className={cn(
                              "flex cursor-pointer gap-3 rounded-lg border border-border p-3 transition-colors",
                              checked ? "border-sky-300 bg-sky-50/80 dark:border-sky-800 dark:bg-sky-950/30" : "bg-card hover:bg-muted/40",
                              !canEditSelected && "cursor-default opacity-80",
                            )}
                          >
                            <input
                              type="checkbox"
                              className="mt-0.5 size-4 rounded border-input"
                              checked={checked}
                              disabled={!canEditSelected}
                              onChange={() => togglePermission(card.permission)}
                            />
                            <span className="min-w-0">
                              <span className="block text-sm font-medium text-foreground">{card.title}</span>
                              <span className="mt-0.5 block text-xs text-muted-foreground">
                                {card.description}
                              </span>
                            </span>
                          </label>
                        );
                      })}
                    </div>
                  )}
                </div>
              ) : null}

              {selectedRole && (permTab === "data" || permTab === "fields" || permTab === "workflow") ? (
                <RoleAccessMatrixPanels
                  tab={permTab}
                  roleName={selectedRole.name}
                  entities={dynEntities}
                  entityDetails={entityDetails}
                  loadEntityDetail={loadEntityDetail}
                  matrix={draftMatrix}
                  filter={permFilter}
                  disabled={!canEditSelected || updateMutation.isPending}
                  onChange={persistMatrix}
                />
              ) : null}
            </div>
          </section>
        </div>

        <Sheet open={createOpen} onOpenChange={setCreateOpen}>
          <SheetContent className="w-full overflow-y-auto sm:max-w-md">
            <SheetHeader>
              <SheetTitle>Create New Role</SheetTitle>
              <SheetDescription>
                Use lowercase names with underscores. Starts with dashboard access — refine permissions
                in the tabs after create.
              </SheetDescription>
            </SheetHeader>
            <div className="mt-6 space-y-4 px-1">
              <label className="block space-y-1.5">
                <span className="text-xs font-medium text-muted-foreground">Role name</span>
                <Input
                  value={createName}
                  onChange={(e) => setCreateName(e.target.value)}
                  placeholder="finance_officer"
                />
              </label>
              <Button
                className="w-full"
                disabled={createMutation.isPending || !createName.trim()}
                onClick={() =>
                  createMutation.mutate({
                    name: createName.trim(),
                    permissions: permissionSet.has("dashboard:view")
                      ? ["dashboard:view"]
                      : allPermissions.slice(0, 1),
                  })
                }
              >
                {createMutation.isPending ? "Creating…" : "Create role"}
              </Button>
            </div>
          </SheetContent>
        </Sheet>
      </div>
    </PermissionGate>
  );
}
