"use client";

import { useMemo } from "react";

import { useAuthStore } from "@/stores/auth-store";

export function TenantSwitcher() {
  const user = useAuthStore((state) => state.user);
  const activeTenantId = useAuthStore((state) => state.activeTenantId);
  const setActiveTenantId = useAuthStore((state) => state.setActiveTenantId);

  const options = useMemo(() => user?.tenantAccesses ?? [], [user?.tenantAccesses]);

  if (!options.length) {
    return null;
  }

  return (
    <select
      aria-label="Select tenant"
      value={activeTenantId ?? ""}
      onChange={(event) => setActiveTenantId(event.target.value || null)}
      className="h-8 rounded-md border bg-background px-2 text-xs"
    >
      {options.map((tenant) => (
        <option key={tenant.tenantId} value={tenant.tenantId}>
          {tenant.tenantName}
        </option>
      ))}
    </select>
  );
}
