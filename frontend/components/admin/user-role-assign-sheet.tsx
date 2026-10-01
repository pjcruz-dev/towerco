"use client";

import { useEffect, useMemo, useState } from "react";
import { useMutation } from "@tanstack/react-query";

import { Button } from "@/components/ui/button";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import { getErrorMessage } from "@/lib/api/error";
import { updateAdminUser, type AdminUserRow } from "@/lib/api/modules/admin-users-api";
import type { AdminRoleRow } from "@/lib/api/modules/admin-roles-api";
import { groupRolesByType } from "@/lib/rbac/role-groups";
import { roleDisplayLabel } from "@/lib/rbac/role-display-labels";
import { getTenantRoleGuide } from "@/lib/rbac/tenant-role-guides";
import { useNotificationStore } from "@/stores/notification-store";
import { cn } from "@/lib/utils";

type Props = {
  user: AdminUserRow | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  roleCatalog: AdminRoleRow[];
  enabledModules?: string[];
  onSaved: () => void;
};

export function UserRoleAssignSheet({
  user,
  open,
  onOpenChange,
  roleCatalog,
  enabledModules,
  onSaved,
}: Props) {
  const notify = useNotificationStore((state) => state.push);
  const [roles, setRoles] = useState<string[]>([]);

  useEffect(() => {
    if (!open || !user) return;
    setRoles(user.roles.length > 0 ? [...user.roles] : ["viewer"]);
  }, [open, user]);

  const groups = useMemo(() => {
    if (!user) return [];
    const catalogNames = new Set(roleCatalog.map((role) => role.name));
    const extras = user.roles
      .filter((name) => !catalogNames.has(name))
      .map((name) => ({
        id: 0,
        name,
        is_baseline: false,
        is_system: true,
        permissions: [] as string[],
        user_count: 0,
      }));
    return groupRolesByType([...roleCatalog, ...extras], {
      enabledModules,
      alwaysIncludeRoleNames: user.roles,
    });
  }, [enabledModules, roleCatalog, user]);

  const saveMutation = useMutation({
    mutationFn: async () => {
      if (!user) return null;
      return updateAdminUser(user.id, { roles });
    },
    onSuccess: () => {
      onSaved();
      notify({
        level: "success",
        title: "Roles updated",
        message: user ? user.email : "Saved.",
      });
      onOpenChange(false);
    },
    onError: (error) => {
      notify({ level: "error", title: "Could not update roles", message: getErrorMessage(error) });
    },
  });

  const toggle = (name: string) => {
    setRoles((current) => {
      if (current.includes(name)) {
        const next = current.filter((role) => role !== name);
        return next.length > 0 ? next : current;
      }
      return [...current, name];
    });
  };

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="flex w-full flex-col p-0 sm:max-w-md">
        <SheetHeader className="border-b border-border px-4 pb-4">
          <SheetTitle>Roles for {user?.name ?? "user"}</SheetTitle>
          <SheetDescription>
            Choose the roles this person holds. Profile details stay on Edit user.
          </SheetDescription>
        </SheetHeader>
        <div className="flex-1 space-y-4 overflow-y-auto px-4 py-5">
          {groups.map((group) => (
            <div key={group.id} className="space-y-2">
              <p className="text-xs font-medium text-muted-foreground">{group.label}</p>
              {group.roles.map((role) => {
                const active = roles.includes(role.name);
                const guide = getTenantRoleGuide(role.name);
                return (
                  <button
                    key={role.name}
                    type="button"
                    onClick={() => toggle(role.name)}
                    className={cn(
                      "w-full rounded-lg border px-3 py-2.5 text-left text-sm transition-colors",
                      active
                        ? "border-primary bg-primary/10 text-primary"
                        : "border-border bg-background text-muted-foreground hover:border-primary/40 hover:text-foreground",
                    )}
                  >
                    <span className="font-medium text-foreground">{roleDisplayLabel(role.name)}</span>
                    {guide ? (
                      <span className="mt-1 block text-xs leading-snug opacity-90">{guide.summary}</span>
                    ) : null}
                  </button>
                );
              })}
            </div>
          ))}
          {roles.length === 1 ? (
            <p className="text-xs text-muted-foreground">At least one role is required.</p>
          ) : null}
        </div>
        <SheetFooter className="border-t border-border px-4 py-3">
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button
            type="button"
            disabled={!user || roles.length === 0 || saveMutation.isPending}
            onClick={() => saveMutation.mutate()}
          >
            {saveMutation.isPending ? "Saving…" : "Save roles"}
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
