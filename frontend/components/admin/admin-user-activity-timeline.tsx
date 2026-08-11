"use client";

import { Badge } from "@/components/ui/badge";
import type { AdminUserActivityEntry } from "@/lib/api/modules/admin-users-api";
import { formatTimestamp } from "@/lib/admin/user-display";

type Props = {
  entries: AdminUserActivityEntry[];
  isLoading?: boolean;
};

function riskBadgeClass(level: string): string {
  if (level === "high" || level === "medium") {
    return "border-warning/30 bg-warning/10 text-warning";
  }

  return "text-muted-foreground";
}

export function AdminUserActivityTimeline({ entries, isLoading = false }: Props) {
  if (isLoading) {
    return <p className="text-sm text-muted-foreground">Loading activity…</p>;
  }

  if (entries.length === 0) {
    return (
      <p className="text-sm text-muted-foreground">
        No sign-in activity recorded yet for this user.
      </p>
    );
  }

  return (
    <ul className="space-y-3">
      {entries.map((entry) => (
        <li key={entry.id} className="rounded-lg border border-border/60 bg-muted/10 px-3 py-3">
          <div className="flex flex-wrap items-start justify-between gap-2">
            <div>
              <p className="text-sm font-medium text-foreground">{entry.label}</p>
              <p className="mt-0.5 text-xs text-muted-foreground">{formatTimestamp(entry.created_at)}</p>
            </div>
            <Badge variant="secondary" className={riskBadgeClass(entry.risk_level)}>
              {entry.risk_level}
            </Badge>
          </div>
          <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
            {entry.ip_address ? <span>IP {entry.ip_address}</span> : null}
            <span className="font-mono">{entry.event}</span>
          </div>
        </li>
      ))}
    </ul>
  );
}
