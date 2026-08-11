"use client";

import { useMemo, useState } from "react";

import { Input } from "@/components/ui/input";
import { groupPermissionsByModule, permissionLabel } from "@/lib/rbac/permission-groups";
import type { AdminRolePermissionGroup } from "@/lib/api/modules/admin-roles-api";

type Props = {
  allPermissions: string[];
  permissionGroups?: Record<string, AdminRolePermissionGroup>;
  selectedPermissions: string[];
  onToggle: (permission: string) => void;
  maxHeightClassName?: string;
};

export function GroupedPermissionPicker({
  allPermissions,
  permissionGroups,
  selectedPermissions,
  onToggle,
  maxHeightClassName = "max-h-64",
}: Props) {
  const [query, setQuery] = useState("");
  const groups = useMemo(() => {
    if (permissionGroups && Object.keys(permissionGroups).length > 0) {
      return Object.entries(permissionGroups).map(([id, group]) => ({
        id,
        label: group.label,
        permissions: group.permissions.filter((permission) => allPermissions.includes(permission)),
      }));
    }

    return groupPermissionsByModule(allPermissions);
  }, [allPermissions, permissionGroups]);

  const filteredGroups = useMemo(() => {
    const needle = query.trim().toLowerCase();
    if (needle === "") {
      return groups;
    }

    return groups
      .map((group) => ({
        ...group,
        permissions: group.permissions.filter((permission) => {
          const label = permissionLabel(permission).toLowerCase();
          return permission.toLowerCase().includes(needle) || label.includes(needle);
        }),
      }))
      .filter((group) => group.permissions.length > 0);
  }, [groups, query]);

  return (
    <div className="space-y-2">
      <Input
        value={query}
        onChange={(event) => setQuery(event.target.value)}
        placeholder="Search permissions…"
        className="h-9 text-sm"
      />
      <div className={`space-y-3 overflow-y-auto rounded-md border border-border p-2 ${maxHeightClassName}`}>
        {filteredGroups.length === 0 ? (
          <p className="px-2 py-4 text-center text-xs text-muted-foreground">No permissions match your search.</p>
        ) : (
          filteredGroups.map((group) => (
            <section key={group.id}>
              <p className="px-2 pb-1 text-[11px] font-medium text-muted-foreground">{group.label}</p>
              <div className="space-y-0.5">
                {group.permissions.map((permission) => {
                  const active = selectedPermissions.includes(permission);
                  return (
                    <button
                      key={permission}
                      type="button"
                      onClick={() => onToggle(permission)}
                      className={`flex w-full items-center justify-between rounded px-2 py-1.5 text-left text-xs transition-colors ${
                        active ? "bg-primary/10 text-primary" : "text-muted-foreground hover:bg-muted"
                      }`}
                    >
                      <span>{permissionLabel(permission)}</span>
                      {active ? <span className="text-[10px] font-medium uppercase">On</span> : null}
                    </button>
                  );
                })}
              </div>
            </section>
          ))
        )}
      </div>
    </div>
  );
}
