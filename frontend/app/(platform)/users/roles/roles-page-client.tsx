"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useMemo, useState } from "react";

import { AdminRoleComparePanel } from "@/components/admin/admin-role-compare-panel";
import { AdminRoleDetailDrawer } from "@/components/admin/admin-role-detail-drawer";
import { GroupedPermissionPicker } from "@/components/admin/grouped-permission-picker";
import { createRolesTableColumns } from "@/components/admin/roles-table-columns";
import { PermissionGate } from "@/components/layout/permission-gate";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { Button, buttonVariants } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
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
  fetchAdminRole,
  suggestRoleCloneName,
  updateAdminRole,
  type AdminRoleDetail,
  type AdminRoleRow,
} from "@/lib/api/modules/admin-roles-api";
import { permissions } from "@/lib/rbac/permissions";
import { filterRolesForEnabledModules } from "@/lib/rbac/role-groups";
import { useNotificationStore } from "@/stores/notification-store";
import { cn } from "@/lib/utils";

function roleLabel(name: string): string {
  return name.replace(/_/g, " ");
}

export function RolesPageClient() {
  const queryClient = useQueryClient();
  const notify = useNotificationStore((state) => state.push);

  const catalogQuery = useAdminRoleCatalog();

  const enabledModules = catalogQuery.data?.enabled_modules;
  const roles = useMemo(
    () =>
      filterRolesForEnabledModules(catalogQuery.data?.roles ?? [], {
        enabledModules,
      }),
    [catalogQuery.data?.roles, enabledModules],
  );
  const allPermissions = catalogQuery.data?.permissions ?? [];

  const [createOpen, setCreateOpen] = useState(false);
  const [editRole, setEditRole] = useState<AdminRoleRow | null>(null);
  const [detailOpen, setDetailOpen] = useState(false);
  const [detailRoleId, setDetailRoleId] = useState<number | null>(null);
  const [cloneRole, setCloneRole] = useState<AdminRoleRow | null>(null);
  const [cloneName, setCloneName] = useState("");
  const [compareOpen, setCompareOpen] = useState(false);
  const [compareLeftId, setCompareLeftId] = useState<number | null>(null);
  const [compareRightId, setCompareRightId] = useState<number | null>(null);
  const [name, setName] = useState("");
  const [selectedPermissions, setSelectedPermissions] = useState<string[]>([]);

  const detailQuery = useQuery({
    queryKey: ["admin", "roles", detailRoleId],
    queryFn: () => fetchAdminRole(detailRoleId!),
    enabled: detailOpen && detailRoleId !== null,
    staleTime: 60_000,
  });

  const invalidate = () => {
    void queryClient.invalidateQueries({ queryKey: ["admin", "roles"] });
  };

  const createMutation = useMutation({
    mutationFn: createAdminRole,
    onSuccess: () => {
      invalidate();
      setCreateOpen(false);
      setName("");
      setSelectedPermissions([]);
      notify({ level: "success", title: "Role created", message: "Custom role is ready to assign." });
    },
    onError: (error) => {
      notify({ level: "error", title: "Create failed", message: getErrorMessage(error) });
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ roleId, permissions: perms }: { roleId: number; permissions: string[] }) =>
      updateAdminRole(roleId, perms),
    onSuccess: () => {
      invalidate();
      setEditRole(null);
      setSelectedPermissions([]);
      notify({ level: "success", title: "Role updated", message: "Permissions saved." });
    },
    onError: (error) => {
      notify({ level: "error", title: "Update failed", message: getErrorMessage(error) });
    },
  });

  const cloneMutation = useMutation({
    mutationFn: ({ roleId, roleName }: { roleId: number; roleName: string }) => cloneAdminRole(roleId, roleName),
    onSuccess: (created) => {
      invalidate();
      setCloneRole(null);
      setCloneName("");
      setDetailOpen(false);
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
      setDetailOpen(false);
      setDetailRoleId(null);
      notify({ level: "success", title: "Role deleted", message: "Custom role removed." });
    },
    onError: (error) => {
      notify({ level: "error", title: "Delete failed", message: getErrorMessage(error) });
    },
  });

  const togglePermission = (permission: string) => {
    setSelectedPermissions((prev) =>
      prev.includes(permission) ? prev.filter((p) => p !== permission) : [...prev, permission],
    );
  };

  const openView = (role: AdminRoleRow) => {
    setDetailRoleId(role.id);
    setDetailOpen(true);
  };

  const openEdit = (role: AdminRoleRow) => {
    setDetailOpen(false);
    setEditRole(role);
    setSelectedPermissions([...role.permissions]);
  };

  const openClone = (role: AdminRoleRow) => {
    setCloneRole(role);
    setCloneName(suggestRoleCloneName(role.name));
  };

  const confirmDelete = (role: AdminRoleRow) => {
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
  };

  const permissionPicker = (
    <GroupedPermissionPicker
      allPermissions={allPermissions}
      permissionGroups={catalogQuery.data?.permission_groups}
      selectedPermissions={selectedPermissions}
      onToggle={togglePermission}
      maxHeightClassName="max-h-72"
    />
  );

  const detailRole: AdminRoleDetail | AdminRoleRow | null =
    detailQuery.data ?? roles.find((role) => role.id === detailRoleId) ?? null;

  const columns = useMemo(
    () =>
      createRolesTableColumns({
        onView: openView,
        onClone: openClone,
        onEdit: openEdit,
        onDelete: confirmDelete,
        deletePending: deleteMutation.isPending,
      }),
    [deleteMutation.isPending],
  );

  return (
    <PermissionGate requiredPermissions={[permissions.roleManage]}>
      <div className="space-y-5">
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight text-foreground">Roles & permissions</h1>
            <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
              Manage baseline and custom roles. Clone baseline roles to customize access, or create roles from scratch.
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <Link
              href="/users"
              prefetch={false}
              className={cn(buttonVariants({ variant: "outline", size: "sm" }))}
            >
              Back to users
            </Link>
            <Button
              size="sm"
              variant="outline"
              disabled={roles.length < 2}
              onClick={() => {
                setCompareLeftId(roles[0]?.id ?? null);
                setCompareRightId(roles[1]?.id ?? null);
                setCompareOpen(true);
              }}
            >
              Compare roles
            </Button>
            <Button
              size="sm"
              onClick={() => {
                setName("");
                setSelectedPermissions(["dashboard:view"]);
                setCreateOpen(true);
              }}
            >
              New role
            </Button>
          </div>
        </header>

        <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
          <RegistryDataTableView
            columns={columns}
            data={roles}
            getRowId={(row) => String(row.id)}
            isLoading={catalogQuery.isLoading}
            isEmpty={!catalogQuery.isLoading && roles.length === 0}
            emptyMessage="No roles found."
            enableColumnVisibility
            columnVisibilityStorageKey="toweros.table.columns.admin.roles"
            manualSorting={false}
          />
        </div>

        {catalogQuery.isError ? (
          <p className="text-sm text-destructive">
            Could not load roles. {getErrorMessage(catalogQuery.error)} Only tenant administrators with{" "}
            <span className="font-medium">role:manage</span> can manage roles.
          </p>
        ) : null}

        <AdminRoleDetailDrawer
          role={detailRole}
          open={detailOpen}
          isLoading={detailOpen && detailQuery.isLoading}
          onOpenChange={setDetailOpen}
          onEdit={openEdit}
          onClone={openClone}
          onDelete={confirmDelete}
        />

        <Sheet open={createOpen} onOpenChange={setCreateOpen}>
          <SheetContent className="w-full overflow-y-auto sm:max-w-md">
            <SheetHeader>
              <SheetTitle>New custom role</SheetTitle>
              <SheetDescription>Use lowercase names with underscores. Permissions are required.</SheetDescription>
            </SheetHeader>
            <div className="mt-6 space-y-4 px-1">
              <label className="block space-y-1.5">
                <span className="text-xs font-medium text-muted-foreground">Role name</span>
                <Input value={name} onChange={(e) => setName(e.target.value)} placeholder="field_supervisor" />
              </label>
              {permissionPicker}
              <Button
                className="w-full"
                disabled={createMutation.isPending || !name.trim() || selectedPermissions.length === 0}
                onClick={() =>
                  createMutation.mutate({
                    name: name.trim(),
                    permissions: selectedPermissions,
                  })
                }
              >
                {createMutation.isPending ? "Creating…" : "Create role"}
              </Button>
            </div>
          </SheetContent>
        </Sheet>

        <Sheet
          open={editRole !== null}
          onOpenChange={(open) => {
            if (!open) {
              setEditRole(null);
              setSelectedPermissions([]);
            }
          }}
        >
          <SheetContent className="w-full overflow-y-auto sm:max-w-md">
            <SheetHeader>
              <SheetTitle>Edit {editRole ? roleLabel(editRole.name) : "role"}</SheetTitle>
              <SheetDescription>Update permission assignments for this custom role.</SheetDescription>
            </SheetHeader>
            <div className="mt-6 space-y-4 px-1">
              {permissionPicker}
              <Button
                className="w-full"
                disabled={updateMutation.isPending || !editRole || selectedPermissions.length === 0}
                onClick={() => {
                  if (!editRole) return;
                  updateMutation.mutate({ roleId: editRole.id, permissions: selectedPermissions });
                }}
              >
                {updateMutation.isPending ? "Saving…" : "Save permissions"}
              </Button>
            </div>
          </SheetContent>
        </Sheet>

        <Sheet
          open={cloneRole !== null}
          onOpenChange={(open) => {
            if (!open) {
              setCloneRole(null);
              setCloneName("");
            }
          }}
        >
          <SheetContent className="w-full overflow-y-auto sm:max-w-md">
            <SheetHeader>
              <SheetTitle>Clone {cloneRole ? roleLabel(cloneRole.name) : "role"}</SheetTitle>
              <SheetDescription>
                Create a new custom role with the same permissions. You can edit permissions after cloning.
              </SheetDescription>
            </SheetHeader>
            <div className="mt-6 space-y-4 px-1">
              <label className="block space-y-1.5">
                <span className="text-xs font-medium text-muted-foreground">New role name</span>
                <Input value={cloneName} onChange={(e) => setCloneName(e.target.value)} placeholder="manager_copy" />
              </label>
              <Button
                className="w-full"
                disabled={cloneMutation.isPending || !cloneRole || !cloneName.trim()}
                onClick={() => {
                  if (!cloneRole) return;
                  cloneMutation.mutate({ roleId: cloneRole.id, roleName: cloneName.trim() });
                }}
              >
                {cloneMutation.isPending ? "Cloning…" : "Clone role"}
              </Button>
            </div>
          </SheetContent>
        </Sheet>

        <Sheet open={compareOpen} onOpenChange={setCompareOpen}>
          <SheetContent className="w-full overflow-y-auto sm:max-w-xl">
            <SheetHeader>
              <SheetTitle>Compare roles</SheetTitle>
              <SheetDescription>See permission differences between two roles.</SheetDescription>
            </SheetHeader>
            <div className="mt-6 space-y-4 px-1">
              <div className="grid gap-3 sm:grid-cols-2">
                <label className="block space-y-1.5">
                  <Label htmlFor="compare-left-role">Left role</Label>
                  <Select
                    id="compare-left-role"
                    className="h-9"
                    value={compareLeftId ?? ""}
                    onChange={(e) => setCompareLeftId(e.target.value ? Number(e.target.value) : null)}
                  >
                    <option value="">Select role</option>
                    {roles.map((role) => (
                      <option key={role.id} value={role.id}>
                        {roleLabel(role.name)}
                      </option>
                    ))}
                  </Select>
                </label>
                <label className="block space-y-1.5">
                  <Label htmlFor="compare-right-role">Right role</Label>
                  <Select
                    id="compare-right-role"
                    className="h-9"
                    value={compareRightId ?? ""}
                    onChange={(e) => setCompareRightId(e.target.value ? Number(e.target.value) : null)}
                  >
                    <option value="">Select role</option>
                    {roles.map((role) => (
                      <option key={role.id} value={role.id}>
                        {roleLabel(role.name)}
                      </option>
                    ))}
                  </Select>
                </label>
              </div>
              <AdminRoleComparePanel leftId={compareLeftId} rightId={compareRightId} />
            </div>
          </SheetContent>
        </Sheet>
      </div>
    </PermissionGate>
  );
}
