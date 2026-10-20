"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import { AlertTriangle, Plus, Shield, Trash2, Users } from "lucide-react";

import { RoleAccessMatrixPanels } from "@/components/admin/role-access-matrix-panels";
import { RoleAiAccessPanel } from "@/components/admin/role-ai-access-panel";
import { RoleModuleAccessGrid } from "@/components/admin/role-module-access-grid";
import { PermissionGate } from "@/components/layout/permission-gate";
import { WorkspacePageHeader } from "@/components/layout/workspace-page-header";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
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
} from "@/lib/api/modules/dynamic-entities-api";
import { permissions } from "@/lib/rbac/permissions";
import { roleDisplayLabel } from "@/lib/rbac/role-display-labels";
import { GENERAL_SYSTEM_GROUPS } from "@/lib/rbac/role-management-layout";
import { roleAllowsPermissionEdit, type RoleAiAccess } from "@/lib/rbac/role-ai-access";
import { ATC_OPERATIONAL_ROLE_ORDER, filterRolesForEnabledModules } from "@/lib/rbac/role-groups";
import { useNotificationStore } from "@/stores/notification-store";
import { cn } from "@/lib/utils";

type PermTab = "general" | "data" | "fields" | "workflow" | "status" | "ai";

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
  const [entityGroup, setEntityGroup] = useState("all");
  const [groupByPack, setGroupByPack] = useState(false);
  const [dirty, setDirty] = useState(false);
  const [createOpen, setCreateOpen] = useState(false);
  const [createName, setCreateName] = useState("");
  const [entityDetails, setEntityDetails] = useState<Record<string, DynEntityDetail | undefined>>({});
  const dynEnabled = (enabledModules ?? []).includes("dynamic_entities");
  const entitiesQuery = useQuery({
    queryKey: ["dynamic-entities", "entities", "role-acl"],
    queryFn: () => fetchDynEntities({ active_only: false }),
    enabled: dynEnabled,
    staleTime: 60_000,
    retry: 1,
  });
  const dynEntities = entitiesQuery.data ?? [];

  const selectedRole = roles.find((r) => r.id === selectedRoleId) ?? null;
  const canEditSelected = Boolean(selectedRole && roleAllowsPermissionEdit(selectedRole.name));

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
    if (!dynEnabled && (permTab === "fields" || permTab === "workflow" || permTab === "status")) {
      setPermTab("data");
    }
  }, [dynEnabled, permTab]);

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
    onSuccess: (updated, variables) => {
      void queryClient.invalidateQueries({ queryKey: ["admin", "roles"] });
      setDirty(false);
      if (selectedRole?.id === variables.roleId) {
        setDraftPermissions(updated.permissions);
        setDraftMatrix(updated.access_matrix ?? variables.accessMatrix);
      }
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
    if (!selectedRole || !canEditSelected || updateMutation.isPending) return;
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

  function setGroupPermissions(names: string[], grant: boolean) {
    if (!canEditSelected || updateMutation.isPending) return;
    const next = new Set(draftPermissions);
    for (const name of names) {
      if (!permissionSet.has(name)) continue;
      if (grant) next.add(name);
      else next.delete(name);
    }
    const list = [...next];
    if (list.length === 0) {
      notify({
        level: "warning",
        title: "Keep one permission",
        message: "A role needs at least one permission.",
      });
      return;
    }
    persistPermissions(list);
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

  const generalGroups = useMemo(() => {
    return GENERAL_SYSTEM_GROUPS.map((group) => ({
      ...group,
      cards: group.cards.filter((card) => permissionSet.has(card.permission)).filter(
        (card) =>
          !filterNeedle ||
          card.title.toLowerCase().includes(filterNeedle) ||
          card.permission.toLowerCase().includes(filterNeedle) ||
          group.title.toLowerCase().includes(filterNeedle),
      ),
    })).filter((group) => group.cards.length > 0);
  }, [permissionSet, filterNeedle]);

  const entityPacks = useMemo(() => {
    const packs = new Set(dynEntities.map((entity) => entity.module_pack).filter(Boolean));
    return [...packs].sort();
  }, [dynEntities]);

  const tabs: Array<{ id: PermTab; label: string }> = [
    { id: "general", label: "General System" },
    { id: "data", label: dynEnabled ? "Data Access (Entities)" : "Data Access" },
    ...(dynEnabled
      ? [
          { id: "fields" as const, label: "Field Level Security" },
          { id: "workflow" as const, label: "Workflow Approvals" },
          { id: "status" as const, label: "Status Monitoring" },
        ]
      : []),
    { id: "ai", label: "AI Assistant" },
  ];

  return (
    <PermissionGate requiredPermissions={[permissions.roleManage]}>
      <div className="flex h-[calc(100dvh-9.5rem)] min-h-[28rem] flex-col gap-4 lg:h-[calc(100dvh-10.5rem)]">
        <WorkspacePageHeader
          title="Role Management"
          description="Define permissions and access controls for system roles."
          actions={
            <>
              <Button type="button" size="sm" variant="outline" render={<Link href="/users" prefetch={false} />}>
                Back to users
              </Button>
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
            </>
          }
        />

        {catalogQuery.isError ? (
          <div className="flex flex-wrap items-center gap-3 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2">
            <p className="min-w-0 flex-1 text-sm text-destructive">
              Could not load roles. {getErrorMessage(catalogQuery.error)}
            </p>
            <Button
              type="button"
              size="sm"
              variant="outline"
              onClick={() => {
                void catalogQuery.refetch();
              }}
            >
              Retry
            </Button>
          </div>
        ) : null}

        <div className="grid min-h-0 flex-1 grid-rows-[minmax(11rem,14rem)_minmax(0,1fr)] overflow-hidden rounded-xl border border-border bg-card shadow-sm lg:grid-cols-[16rem_minmax(0,1fr)] lg:grid-rows-1">
          {/* Available roles */}
          <aside className="flex min-h-0 flex-col overflow-hidden border-b border-border lg:border-b-0 lg:border-r">
            <div className="flex items-center gap-2 border-b border-border px-4 py-3">
              <Users className="size-4 text-muted-foreground" />
              <h2 className="text-sm font-medium text-foreground">Available Roles</h2>
            </div>
            <div className="min-h-0 flex-1 overflow-y-auto p-2">
              {catalogQuery.isLoading ? (
                <p className="px-2 py-6 text-center text-sm text-muted-foreground">Loading roles…</p>
              ) : catalogQuery.isError ? (
                <p className="px-2 py-6 text-center text-sm text-muted-foreground">
                  Role list unavailable until the request succeeds.
                </p>
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
                            "group flex items-center gap-1 rounded-md",
                            active
                              ? "bg-muted text-foreground"
                              : "text-muted-foreground hover:bg-muted/60 hover:text-foreground",
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
                                  "truncate text-sm capitalize",
                                  active ? "font-medium text-foreground" : "font-normal",
                                )}
                              >
                                {roleLabel(role.name)}
                              </span>
                              {role.is_baseline ? (
                                <Shield
                                  className="size-3.5 shrink-0 text-amber-600 dark:text-amber-400"
                                  aria-label="Built-in role"
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
          <section className="flex min-h-0 min-w-0 flex-col overflow-hidden">
            <div className="shrink-0 border-b border-border px-4 py-3">
              <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0">
                  <h2 className="text-sm font-medium text-foreground">
                    Permissions for{" "}
                    <span className="capitalize">{selectedRole ? roleLabel(selectedRole.name) : "…"}</span>
                  </h2>
                  {selectedRole && !canEditSelected ? (
                    <p className="mt-0.5 text-xs text-muted-foreground">
                      Workspace owner stays unrestricted.
                    </p>
                  ) : (
                    <p className="mt-0.5 text-xs text-muted-foreground">
                      Check a permission to make it applicable for this role. Changes save when you toggle.
                    </p>
                  )}
                </div>
                <div className="flex shrink-0 flex-wrap items-center gap-2">
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
              <div className="mt-3 flex flex-wrap items-center gap-2">
                {dynEnabled && permTab !== "general" && permTab !== "ai" ? (
                  <>
                    <select
                      className="h-8 rounded-md border border-input bg-background px-2 text-xs"
                      value={entityGroup}
                      aria-label="Entity group"
                      onChange={(event) => setEntityGroup(event.target.value)}
                    >
                      <option value="all">All entity groups</option>
                      {entityPacks.map((pack) => (
                        <option key={pack} value={pack}>
                          {pack.replace(/_/g, " ")}
                        </option>
                      ))}
                    </select>
                    <Button
                      type="button"
                      size="sm"
                      variant={groupByPack ? "secondary" : "outline"}
                      onClick={() => setGroupByPack((current) => !current)}
                    >
                      Groups
                    </Button>
                  </>
                ) : null}
                <Input
                  value={permFilter}
                  onChange={(e) => setPermFilter(e.target.value)}
                  placeholder="Filter permissions…"
                  className="h-8 w-full max-w-xs text-xs"
                />
              </div>
            </div>

            <Tabs
              value={permTab}
              onValueChange={(value) => setPermTab(value as PermTab)}
              className="shrink-0 border-b border-border px-2"
            >
              <TabsList
                variant="line"
                className="h-auto w-full flex-wrap justify-start gap-x-0 gap-y-0 rounded-none bg-transparent p-0 group-data-horizontal/tabs:h-auto"
              >
                {tabs.map((tab) => (
                  <TabsTrigger key={tab.id} value={tab.id} className="h-9 flex-none px-3 py-2 text-xs">
                    {tab.label}
                  </TabsTrigger>
                ))}
              </TabsList>
            </Tabs>

            <div className="flex min-h-0 flex-1 flex-col overflow-hidden p-4">
              {!selectedRole ? (
                <p className="text-sm text-muted-foreground">Select a role to manage permissions.</p>
              ) : null}

              {selectedRole && permTab === "general" ? (
                <div className="min-h-0 flex-1 space-y-3 overflow-y-auto">
                  {generalGroups.length === 0 ? (
                    <p className="text-sm text-muted-foreground">No matching permissions.</p>
                  ) : (
                    generalGroups.map((group) => {
                      const granted = group.cards.filter((card) => draftPermissions.includes(card.permission)).length;
                      return (
                        <section key={group.id} className="rounded-xl border border-border bg-card">
                          <div className="flex flex-wrap items-start justify-between gap-2 border-b border-border px-3 py-2.5">
                            <div>
                              <h3 className="text-sm font-medium text-foreground">{group.title}</h3>
                              <p className="mt-0.5 text-xs text-muted-foreground">{group.description}</p>
                            </div>
                            <div className="flex items-center gap-2 text-xs">
                              <span className="tabular-nums text-muted-foreground">
                                {granted} of {group.cards.length}
                              </span>
                              <button
                                type="button"
                                className="font-medium text-foreground hover:underline disabled:opacity-50"
                                disabled={!canEditSelected || updateMutation.isPending}
                                onClick={() => setGroupPermissions(group.cards.map((card) => card.permission), true)}
                              >
                                Grant all
                              </button>
                              <button
                                type="button"
                                className="text-muted-foreground hover:underline disabled:opacity-50"
                                disabled={!canEditSelected || updateMutation.isPending}
                                onClick={() => setGroupPermissions(group.cards.map((card) => card.permission), false)}
                              >
                                Clear
                              </button>
                            </div>
                          </div>
                          <div className="grid gap-1 p-2 sm:grid-cols-2">
                            {group.cards.map((card) => {
                              const checked = draftPermissions.includes(card.permission);
                              return (
                                <label
                                  key={card.permission}
                                  className={cn(
                                    "flex cursor-pointer gap-3 rounded-lg px-2 py-2 hover:bg-muted/40",
                                    !canEditSelected && "cursor-default",
                                  )}
                                >
                                  <Checkbox
                                    checked={checked}
                                    disabled={!canEditSelected}
                                    onCheckedChange={() => togglePermission(card.permission)}
                                    className="mt-0.5"
                                  />
                                  <span className="min-w-0">
                                    <span className="flex items-center gap-1.5 text-sm font-medium text-foreground">
                                      {card.title}
                                      {card.danger ? (
                                        <AlertTriangle className="size-3.5 text-warning" aria-label="Wide-reaching permission" />
                                      ) : null}
                                    </span>
                                    <span className="mt-0.5 block text-xs text-muted-foreground">{card.description}</span>
                                  </span>
                                </label>
                              );
                            })}
                          </div>
                        </section>
                      );
                    })
                  )}
                </div>
              ) : null}

              {selectedRole && permTab === "data" && !dynEnabled ? (
                <div className="min-h-0 flex-1 overflow-y-auto">
                <RoleModuleAccessGrid
                  enabledModules={enabledModules}
                  granted={draftPermissions}
                  filter={permFilter}
                  disabled={!canEditSelected || updateMutation.isPending}
                  onToggle={togglePermission}
                />
                </div>
              ) : null}

              {selectedRole && dynEnabled && (permTab === "data" || permTab === "fields" || permTab === "workflow" || permTab === "status") && entitiesQuery.isError ? (
                <div className="mb-3 flex flex-wrap items-center gap-3 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2">
                  <p className="min-w-0 flex-1 text-sm text-destructive">
                    Could not load Dynamic Entities. {getErrorMessage(entitiesQuery.error)}
                  </p>
                  <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => {
                      void entitiesQuery.refetch();
                    }}
                  >
                    Retry
                  </Button>
                </div>
              ) : null}

              {selectedRole && dynEnabled && (permTab === "data" || permTab === "fields" || permTab === "workflow" || permTab === "status") && entitiesQuery.isLoading ? (
                <p className="text-sm text-muted-foreground">Loading entities…</p>
              ) : null}

              {selectedRole && dynEnabled && (permTab === "data" || permTab === "fields" || permTab === "workflow" || permTab === "status") && !entitiesQuery.isLoading ? (
                <RoleAccessMatrixPanels
                  tab={permTab}
                  roleName={selectedRole.name}
                  entities={dynEntities}
                  entityDetails={entityDetails}
                  loadEntityDetail={loadEntityDetail}
                  matrix={draftMatrix}
                  filter={permFilter}
                  entityGroup={entityGroup}
                  groupByPack={groupByPack}
                  disabled={!canEditSelected || updateMutation.isPending}
                  onChange={persistMatrix}
                />
              ) : null}

              {selectedRole && permTab === "ai" ? (
                <div className="min-h-0 flex-1 overflow-y-auto">
                <RoleAiAccessPanel
                  key={selectedRole.id}
                  value={draftMatrix.ai}
                  disabled={!canEditSelected || updateMutation.isPending}
                  saving={updateMutation.isPending}
                  onSave={(next: RoleAiAccess) => persistMatrix({ ...draftMatrix, ai: next })}
                />
                </div>
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
