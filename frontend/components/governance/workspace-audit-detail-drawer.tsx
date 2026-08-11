"use client";

import Link from "next/link";
import type { ReactNode } from "react";
import { ExternalLink } from "lucide-react";

import { Badge } from "@/components/ui/badge";
import { buttonVariants } from "@/components/ui/button";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import { formatTimestamp } from "@/lib/admin/user-display";
import type { WorkspaceAuditChange, WorkspaceAuditRow } from "@/lib/api/modules/workspace-audit-api";
import { TENANT_MODULE_LABELS } from "@/lib/tenant/enabled-modules";
import {
  auditCategoryLabel,
  auditSeverityClassName,
  auditSeverityLabel,
} from "@/lib/workspace/audit-display";
import { cn } from "@/lib/utils";

type Props = {
  row: WorkspaceAuditRow | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

function moduleLabel(module: string): string {
  return TENANT_MODULE_LABELS[module] ?? module.replace(/_/g, " ");
}

function sourceLabel(source: string): string {
  switch (source) {
    case "workspace":
      return "Workspace";
    case "e_approval":
      return "E-Approval";
    case "auth":
      return "Authentication";
    default:
      return source.replace(/_/g, " ");
  }
}

function fieldLabel(field: string): string {
  return field.replace(/_/g, " ");
}

function formatChangeValue(value: unknown): string {
  if (value === null || value === undefined) {
    return "—";
  }
  if (typeof value === "boolean") {
    return value ? "true" : "false";
  }
  if (typeof value === "number") {
    return String(value);
  }
  if (typeof value === "string") {
    return value.trim() === "" ? "—" : value;
  }
  if (Array.isArray(value)) {
    return value.length === 0 ? "[]" : value.map((item) => String(item)).join(", ");
  }
  try {
    return JSON.stringify(value);
  } catch {
    return String(value);
  }
}

function DetailField({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="space-y-1">
      <p className="text-xs font-medium text-muted-foreground">{label}</p>
      <div className="text-sm text-foreground break-words">{children}</div>
    </div>
  );
}

function ChangesTable({ changes }: { changes: Record<string, WorkspaceAuditChange> }) {
  const entries = Object.entries(changes);
  if (entries.length === 0) {
    return null;
  }

  return (
    <div className="overflow-hidden rounded-lg border border-border">
      <table className="w-full text-left text-sm">
        <thead className="bg-muted/40 text-xs text-muted-foreground">
          <tr>
            <th className="px-3 py-2 font-medium">Field</th>
            <th className="px-3 py-2 font-medium">From</th>
            <th className="px-3 py-2 font-medium">To</th>
          </tr>
        </thead>
        <tbody>
          {entries.map(([field, change]) => (
            <tr key={field} className="border-t border-border align-top">
              <td className="px-3 py-2 font-medium capitalize text-foreground">{fieldLabel(field)}</td>
              <td className="px-3 py-2 font-mono text-xs text-muted-foreground">
                {formatChangeValue(change.from)}
              </td>
              <td className="px-3 py-2 font-mono text-xs text-foreground">{formatChangeValue(change.to)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function metadataPreview(metadata: Record<string, unknown> | null): string | null {
  if (!metadata || Object.keys(metadata).length === 0) {
    return null;
  }

  try {
    return JSON.stringify(metadata, null, 2);
  } catch {
    return String(metadata);
  }
}

export function WorkspaceAuditDetailDrawer({ row, open, onOpenChange }: Props) {
  if (!row) {
    return null;
  }

  const changes = row.changes && Object.keys(row.changes).length > 0 ? row.changes : null;
  const metaText = metadataPreview(row.metadata);
  const entityDisplay = row.entity_label ?? row.entity_id ?? null;

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="flex w-full flex-col gap-0 overflow-hidden p-0 sm:max-w-lg">
        <SheetHeader className="border-b border-border px-4 pb-4">
          <SheetTitle>{row.action_label || row.action}</SheetTitle>
          <SheetDescription>
            {formatTimestamp(row.created_at)} · {moduleLabel(row.module)}
          </SheetDescription>
        </SheetHeader>

        <div className="flex-1 space-y-5 overflow-y-auto px-4 py-4">
          <div className="flex flex-wrap gap-2">
            <Badge variant="secondary">{moduleLabel(row.module)}</Badge>
            <Badge variant="outline">{sourceLabel(row.source)}</Badge>
            {row.category ? (
              <Badge variant="outline">{auditCategoryLabel(row.category)}</Badge>
            ) : null}
            {row.severity ? (
              <Badge variant="outline" className={auditSeverityClassName(row.severity)}>
                {auditSeverityLabel(row.severity)}
              </Badge>
            ) : null}
            {changes ? <Badge variant="outline">{Object.keys(changes).length} change(s)</Badge> : null}
          </div>

          <DetailField label="Summary">{row.summary?.trim() ? row.summary : "—"}</DetailField>

          {row.reason ? <DetailField label="Reason">{row.reason}</DetailField> : null}

          {changes ? (
            <div className="space-y-1.5">
              <p className="text-xs font-medium text-muted-foreground">Changes</p>
              <ChangesTable changes={changes} />
            </div>
          ) : null}

          <div className="grid gap-4 sm:grid-cols-2">
            <DetailField label="Actor">{row.actor?.name ?? "—"}</DetailField>
            <DetailField label="Actor email">{row.actor?.email ?? "—"}</DetailField>
            <DetailField label="IP address">{row.ip_address ?? "—"}</DetailField>
            <DetailField label="When">{formatTimestamp(row.created_at)}</DetailField>
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <DetailField label="Entity type">{row.entity_type ?? "—"}</DetailField>
            <DetailField label="Entity">
              {entityDisplay ? (
                row.href ? (
                  <Link
                    href={row.href}
                    className={cn(buttonVariants({ variant: "link" }), "h-auto p-0 text-sm")}
                  >
                    {entityDisplay}
                    <ExternalLink className="ml-1 inline h-3.5 w-3.5" />
                  </Link>
                ) : (
                  entityDisplay
                )
              ) : (
                "—"
              )}
            </DetailField>
          </div>

          <DetailField label="Action code">
            <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">{row.action}</code>
          </DetailField>

          {metaText ? (
            <DetailField label="Metadata">
              <pre className="max-h-64 overflow-auto rounded-lg border border-border bg-muted/30 p-3 font-mono text-xs leading-relaxed whitespace-pre-wrap">
                {metaText}
              </pre>
            </DetailField>
          ) : null}
        </div>

        {row.href ? (
          <div className="border-t border-border px-4 py-3">
            <Link
              href={row.href}
              className={cn(buttonVariants({ variant: "outline", size: "sm" }), "w-full sm:w-auto")}
            >
              Open related record
              <ExternalLink className="ml-1.5 h-3.5 w-3.5" />
            </Link>
          </div>
        ) : null}
      </SheetContent>
    </Sheet>
  );
}
