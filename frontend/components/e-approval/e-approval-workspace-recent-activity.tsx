"use client";

import Link from "next/link";

import { EApprovalStatusBadge } from "@/components/e-approval/e-approval-status-badge";
import type { EApprovalFormWorkspaceDashboard } from "@/modules/e-approval/form-workspace-types";

type Props = {
  items: EApprovalFormWorkspaceDashboard["recent_activity"];
};

export function EApprovalWorkspaceRecentActivity({ items }: Props) {
  if (items.length === 0) {
    return (
      <div className="rounded-xl border border-border bg-card px-4 py-6 text-sm text-muted-foreground">
        No recent activity in your current scope.
      </div>
    );
  }

  return (
    <div className="rounded-xl border border-border bg-card p-4">
      <p className="text-base font-medium text-foreground">Recent activity</p>
      <ul className="mt-3 divide-y divide-border">
        {items.map((item) => (
          <li key={item.id} className="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
            <div className="min-w-0">
              <Link href={`/e-approval/submissions/${item.id}`} className="text-sm font-medium text-primary hover:underline">
                {item.document_no}
              </Link>
              <p className="truncate text-xs text-muted-foreground">
                {item.form_name ? `${item.form_name} · ` : ""}
                {item.requestor_name || "Unknown requestor"}
                {item.created_at ? ` · ${new Date(item.created_at).toLocaleDateString()}` : ""}
              </p>
            </div>
            <EApprovalStatusBadge status={item.status} kind="submission" />
          </li>
        ))}
      </ul>
    </div>
  );
}
