"use client";

import { useQuery } from "@tanstack/react-query";
import Link from "next/link";

import { Badge } from "@/components/ui/badge";
import { formatTimestamp } from "@/lib/admin/user-display";
import { fetchWorkspaceAuditForEntity } from "@/lib/api/modules/workspace-audit-api";
import {
  auditCategoryLabel,
  auditSeverityClassName,
  auditSeverityLabel,
} from "@/lib/workspace/audit-display";
import { cn } from "@/lib/utils";
import { permissions } from "@/lib/rbac/permissions";
import { useAuthStore } from "@/stores/auth-store";

type Props = {
  entityType: string;
  entityId: string;
  title?: string;
  limit?: number;
  className?: string;
};

export function WorkspaceEntityActivityPanel({
  entityType,
  entityId,
  title = "Audit activity",
  limit = 12,
  className,
}: Props) {
  const canView = useAuthStore((state) =>
    state.effectivePermissions().includes(permissions.workspaceAuditView),
  );

  const { data, isFetching, isError } = useQuery({
    queryKey: ["workspace", "audit", "entity", entityType, entityId, limit],
    queryFn: () =>
      fetchWorkspaceAuditForEntity({
        entity_type: entityType,
        entity_id: entityId,
        limit,
      }),
    enabled: canView && entityType !== "" && entityId !== "",
  });

  if (!canView) {
    return null;
  }

  const rows = data ?? [];

  return (
    <section className={cn("rounded-xl border border-border bg-card shadow-sm", className)}>
      <div className="flex items-center justify-between gap-2 border-b border-border px-4 py-3">
        <h2 className="text-base font-medium text-foreground">{title}</h2>
        <Link
          href={`/governance/audit?entity_type=${encodeURIComponent(entityType)}&entity_id=${encodeURIComponent(entityId)}`}
          className="text-xs font-medium text-primary hover:underline"
        >
          Open full trail
        </Link>
      </div>

      {isFetching && rows.length === 0 ? (
        <p className="px-4 py-6 text-sm text-muted-foreground">Loading activity…</p>
      ) : null}

      {isError ? (
        <p className="px-4 py-6 text-sm text-muted-foreground">Could not load audit activity.</p>
      ) : null}

      {!isFetching && !isError && rows.length === 0 ? (
        <p className="px-4 py-6 text-sm text-muted-foreground">No audit events for this record yet.</p>
      ) : null}

      {rows.length > 0 ? (
        <ul className="divide-y divide-border">
          {rows.map((row) => (
            <li key={row.id} className="px-4 py-3">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm font-medium text-foreground">{row.action_label || row.action}</p>
                <span className="text-xs text-muted-foreground">{formatTimestamp(row.created_at)}</span>
              </div>
              <div className="mt-1.5 flex flex-wrap gap-1.5">
                {row.category ? (
                  <Badge variant="outline" className="font-normal">
                    {auditCategoryLabel(row.category)}
                  </Badge>
                ) : null}
                {row.severity ? (
                  <Badge variant="outline" className={cn("font-normal", auditSeverityClassName(row.severity))}>
                    {auditSeverityLabel(row.severity)}
                  </Badge>
                ) : null}
              </div>
              <p className="mt-1 text-xs text-muted-foreground">
                {row.actor?.name ?? "System"}
                {row.summary ? ` · ${row.summary}` : ""}
              </p>
              {row.reason ? (
                <p className="mt-1 text-xs text-muted-foreground">Reason: {row.reason}</p>
              ) : null}
            </li>
          ))}
        </ul>
      ) : null}
    </section>
  );
}
