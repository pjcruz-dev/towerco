"use client";

import type { EApprovalFormWorkspaceDashboard } from "@/modules/e-approval/form-workspace-types";

type Props = {
  items: EApprovalFormWorkspaceDashboard["recent_audit"];
};

export function EApprovalWorkspaceAuditLog({ items }: Props) {
  if (items.length === 0) {
    return (
      <div className="rounded-xl border border-border bg-card px-4 py-6 text-sm text-muted-foreground">
        No recent workspace audit events.
      </div>
    );
  }

  return (
    <div className="rounded-xl border border-border bg-card p-4">
      <p className="text-base font-medium text-foreground">Workspace audit log</p>
      <ul className="mt-3 divide-y divide-border">
        {items.map((item) => (
          <li key={item.id} className="py-3 first:pt-0 last:pb-0">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <p className="text-sm font-medium text-foreground">{item.action}</p>
              <span className="text-xs text-muted-foreground">
                {item.created_at ? new Date(item.created_at).toLocaleString() : ""}
              </span>
            </div>
            <p className="mt-1 text-xs text-muted-foreground">
              {item.user_name || "System"}
              {item.remarks ? ` · ${item.remarks}` : ""}
            </p>
          </li>
        ))}
      </ul>
    </div>
  );
}
