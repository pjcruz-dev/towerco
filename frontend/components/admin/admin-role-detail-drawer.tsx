"use client";

import Link from "next/link";
import { useEffect, useMemo, useState, type ReactNode } from "react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import type { AdminRoleDetail, AdminRoleRow } from "@/lib/api/modules/admin-roles-api";
import { groupPermissionsByModule, permissionLabel } from "@/lib/rbac/permission-groups";
import { getTenantRoleGuide } from "@/lib/rbac/tenant-role-guides";

type TabId = "permissions" | "users";

type Props = {
  role: AdminRoleDetail | AdminRoleRow | null;
  open: boolean;
  isLoading?: boolean;
  onOpenChange: (open: boolean) => void;
  onEdit?: (role: AdminRoleRow) => void;
  onClone?: (role: AdminRoleRow) => void;
  onDelete?: (role: AdminRoleRow) => void;
};

function roleLabel(name: string): string {
  return name.replace(/_/g, " ");
}

function DetailField({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="space-y-1">
      <p className="text-xs font-medium text-muted-foreground">{label}</p>
      <div className="text-sm text-foreground">{children}</div>
    </div>
  );
}

export function AdminRoleDetailDrawer({
  role,
  open,
  isLoading = false,
  onOpenChange,
  onEdit,
  onClone,
  onDelete,
}: Props) {
  const [tab, setTab] = useState<TabId>("permissions");

  useEffect(() => {
    if (open) {
      setTab("permissions");
    }
  }, [open, role?.id]);

  const permissionGroups = useMemo(
    () => groupPermissionsByModule(role?.permissions ?? []),
    [role?.permissions],
  );

  const assignedUsers = role && "users" in role ? role.users : [];

  if (!role) {
    return null;
  }

  const guide = getTenantRoleGuide(role.name);
  const canEdit = !role.is_system && !role.is_baseline && onEdit;
  const canDelete = !role.is_system && !role.is_baseline && onDelete && role.user_count === 0;

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="flex w-full flex-col p-0 sm:max-w-lg">
        <SheetHeader className="border-b border-border px-4 pb-4">
          <SheetTitle className="capitalize">{roleLabel(role.name)}</SheetTitle>
          <SheetDescription>
            {role.is_baseline ? "Core role" : role.is_system ? "System role" : "Custom role"} · {role.permissions.length} permissions ·{" "}
            {role.user_count} user{role.user_count === 1 ? "" : "s"}
          </SheetDescription>
        </SheetHeader>

        <div className="border-b border-border px-4 py-3">
          <div className="inline-flex rounded-lg border border-border bg-muted/20 p-1">
            {(
              [
                ["permissions", "Permissions"],
                ["users", `Users (${role.user_count})`],
              ] as const
            ).map(([id, label]) => (
              <button
                key={id}
                type="button"
                onClick={() => setTab(id)}
                className={`rounded-md px-3 py-1.5 text-xs font-medium transition-colors ${
                  tab === id ? "bg-background text-foreground shadow-sm" : "text-muted-foreground hover:text-foreground"
                }`}
              >
                {label}
              </button>
            ))}
          </div>
        </div>

        <div className="flex-1 space-y-5 overflow-y-auto px-4 py-5">
          {isLoading ? <p className="text-sm text-muted-foreground">Loading role details…</p> : null}

          {!isLoading && tab === "permissions" ? (
            <>
              <div className="flex flex-wrap gap-2">
                <Badge variant={role.is_baseline ? "outline" : role.is_system ? "secondary" : "outline"}>
                  {role.is_baseline ? "Core" : role.is_system ? "System" : "Custom"}
                </Badge>
                <Badge variant="secondary">{role.permissions.length} permissions</Badge>
              </div>

              {guide ? (
                <p className="text-sm text-muted-foreground">{guide.summary}</p>
              ) : null}

              {permissionGroups.length === 0 ? (
                <p className="text-sm text-muted-foreground">No permissions assigned.</p>
              ) : (
                permissionGroups.map((group) => (
                  <div key={group.id} className="rounded-lg border border-border/60 bg-muted/10 p-3">
                    <p className="text-xs font-medium text-muted-foreground">{group.label}</p>
                    <div className="mt-2 flex flex-wrap gap-1">
                      {group.permissions.map((permission) => (
                        <Badge key={permission} variant="ghost" className="font-normal">
                          {permissionLabel(permission)}
                        </Badge>
                      ))}
                    </div>
                  </div>
                ))
              )}
            </>
          ) : null}

          {!isLoading && tab === "users" ? (
            <>
              {assignedUsers.length === 0 ? (
                <p className="text-sm text-muted-foreground">No users are assigned to this role yet.</p>
              ) : (
                <ul className="divide-y divide-border rounded-lg border border-border">
                  {assignedUsers.map((user) => (
                    <li key={user.id} className="flex items-center justify-between gap-3 px-3 py-3">
                      <div className="min-w-0">
                        <p className="truncate text-sm font-medium text-foreground">{user.name}</p>
                        <p className="truncate text-xs text-muted-foreground">{user.email}</p>
                      </div>
                      <div className="flex shrink-0 items-center gap-2">
                        <Badge
                          variant="secondary"
                          className={
                            user.is_active
                              ? "border-success/30 bg-success/10 text-success"
                              : "text-muted-foreground"
                          }
                        >
                          {user.is_active ? "Active" : "Inactive"}
                        </Badge>
                        <Link
                          href={`/users?role=${encodeURIComponent(role.name)}`}
                          className="text-xs font-medium text-primary hover:underline"
                        >
                          Open
                        </Link>
                      </div>
                    </li>
                  ))}
                </ul>
              )}
              {role.user_count > assignedUsers.length ? (
                <p className="text-xs text-muted-foreground">
                  Showing first {assignedUsers.length} of {role.user_count} users. Filter users by role on the Users
                  page.
                </p>
              ) : null}
            </>
          ) : null}
        </div>

        <SheetFooter className="border-t border-border px-4 py-4 sm:flex-row sm:justify-end">
          {onClone ? (
            <Button variant="outline" onClick={() => onClone(role)}>
              Clone role
            </Button>
          ) : null}
          {canDelete ? (
            <Button variant="outline" className="text-destructive hover:text-destructive" onClick={() => onDelete(role)}>
              Delete
            </Button>
          ) : null}
          {canEdit ? <Button onClick={() => onEdit(role)}>Edit permissions</Button> : null}
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
