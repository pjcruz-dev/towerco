"use client";

import { useQuery } from "@tanstack/react-query";
import { useMemo } from "react";

import { Badge } from "@/components/ui/badge";
import { compareAdminRoles } from "@/lib/api/modules/admin-roles-api";
import { groupPermissionsByModule, permissionLabel } from "@/lib/rbac/permission-groups";

type Props = {
  leftId: number | null;
  rightId: number | null;
};

function roleLabel(name: string): string {
  return name.replace(/_/g, " ");
}

function PermissionList({ title, permissions }: { title: string; permissions: string[] }) {
  const groups = useMemo(() => groupPermissionsByModule(permissions), [permissions]);

  return (
    <section className="space-y-2">
      <h4 className="text-sm font-medium text-foreground">
        {title} <span className="text-muted-foreground">({permissions.length})</span>
      </h4>
      {permissions.length === 0 ? (
        <p className="text-sm text-muted-foreground">None</p>
      ) : (
        groups.map((group) => (
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
    </section>
  );
}

export function AdminRoleComparePanel({ leftId, rightId }: Props) {
  const compareQuery = useQuery({
    queryKey: ["admin", "roles", "compare", leftId, rightId],
    queryFn: () => compareAdminRoles(leftId!, rightId!),
    enabled: leftId !== null && rightId !== null && leftId !== rightId,
  });

  if (leftId === null || rightId === null) {
    return <p className="text-sm text-muted-foreground">Select two roles to compare permissions.</p>;
  }

  if (leftId === rightId) {
    return <p className="text-sm text-muted-foreground">Choose two different roles.</p>;
  }

  if (compareQuery.isLoading) {
    return <p className="text-sm text-muted-foreground">Comparing roles…</p>;
  }

  if (compareQuery.isError || !compareQuery.data) {
    return <p className="text-sm text-destructive">Could not compare roles.</p>;
  }

  const data = compareQuery.data;

  return (
    <div className="space-y-5">
      <div className="grid gap-3 sm:grid-cols-2">
        <div className="rounded-lg border border-border bg-muted/20 px-3 py-2">
          <p className="text-xs text-muted-foreground">Left role</p>
          <p className="text-sm font-medium capitalize">{roleLabel(data.left.name)}</p>
        </div>
        <div className="rounded-lg border border-border bg-muted/20 px-3 py-2">
          <p className="text-xs text-muted-foreground">Right role</p>
          <p className="text-sm font-medium capitalize">{roleLabel(data.right.name)}</p>
        </div>
      </div>

      <PermissionList
        title={`Only in ${roleLabel(data.left.name)}`}
        permissions={data.only_left}
      />
      <PermissionList title="Shared permissions" permissions={data.shared} />
      <PermissionList
        title={`Only in ${roleLabel(data.right.name)}`}
        permissions={data.only_right}
      />
    </div>
  );
}
