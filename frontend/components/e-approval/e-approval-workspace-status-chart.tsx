"use client";

import type { EApprovalFormWorkspaceDashboard } from "@/modules/e-approval/form-workspace-types";
import { cn } from "@/lib/utils";

type Props = {
  items: EApprovalFormWorkspaceDashboard["status_breakdown"];
};

const TONE_BY_STATUS: Record<string, string> = {
  pending: "bg-amber-500",
  returned: "bg-orange-500",
  approved: "bg-emerald-500",
  rejected: "bg-red-500",
  cancelled: "bg-slate-400",
};

export function EApprovalWorkspaceStatusChart({ items }: Props) {
  const total = items.reduce((sum, item) => sum + item.count, 0);

  if (total === 0) {
    return (
      <div className="rounded-xl border border-border bg-card px-4 py-6 text-sm text-muted-foreground">
        No submissions in your current scope yet.
      </div>
    );
  }

  return (
    <div className="rounded-xl border border-border bg-card p-4">
      <p className="text-base font-medium text-foreground">Status breakdown</p>
      <div className="mt-4 space-y-3">
        {items.map((item) => {
          const width = Math.max(8, Math.round((item.count / total) * 100));
          return (
            <div key={item.status} className="space-y-1">
              <div className="flex items-center justify-between text-sm">
                <span className="text-foreground">{item.label}</span>
                <span className="text-muted-foreground">{item.count}</span>
              </div>
              <div className="h-2 rounded-full bg-muted">
                <div
                  className={cn("h-2 rounded-full", TONE_BY_STATUS[item.status] ?? "bg-primary")}
                  style={{ width: `${width}%` }}
                />
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
