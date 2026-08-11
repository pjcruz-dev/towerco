"use client";

import type { ColumnDef } from "@tanstack/react-table";

import {
  createActionsColumn,
  createDateColumn,
  createTextColumn,
  formatTableDate,
} from "@/components/ui/data-table-column-helpers";
import { RowActionsMenu } from "@/components/ui/row-actions-menu";
import type { AssistantKnowledgeRow } from "@/lib/api/modules/assistant-api";
import { cn } from "@/lib/utils";

function statusBadge(status: string) {
  const tone =
    status === "published"
      ? "bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200"
      : status === "archived"
        ? "bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200"
        : "bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200";

  return (
    <span
      className={cn(
        "inline-flex rounded-md px-2 py-0.5 text-[11px] font-medium capitalize",
        tone,
      )}
    >
      {status}
    </span>
  );
}

export function createAssistantKnowledgeTableColumns(options: {
  onEdit: (row: AssistantKnowledgeRow) => void;
  onPublish: (row: AssistantKnowledgeRow) => void;
  onArchive: (row: AssistantKnowledgeRow) => void;
  onReindex: (row: AssistantKnowledgeRow) => void;
  onDelete: (row: AssistantKnowledgeRow) => void;
  actionPending: boolean;
}): ColumnDef<AssistantKnowledgeRow>[] {
  return [
    createTextColumn("title", "Title", (row) => (
      <div className="min-w-0">
        <p className="truncate text-sm font-medium text-foreground">{row.title}</p>
        <p className="truncate font-mono text-[11px] text-muted-foreground">{row.slug ?? "—"}</p>
      </div>
    )),
    createTextColumn("status", "Status", (row) => statusBadge(row.status)),
    createTextColumn("version", "Ver", (row) => (
      <span className="font-mono text-xs text-muted-foreground">v{row.version}</span>
    )),
    createTextColumn("chunk_count", "Chunks", (row) => (
      <span className="text-xs text-muted-foreground">{row.chunk_count}</span>
    )),
    createTextColumn("last_indexed_at", "Last indexed", (row) => (
      <span className="whitespace-nowrap text-xs text-muted-foreground">
        {formatTableDate(row.last_indexed_at)}
      </span>
    )),
    createDateColumn("updated_at", "Updated", (row) => row.updated_at),
    createActionsColumn("Actions", (row) => {
      const source = row.original;
      const archived = source.status === "archived";
      const published = source.status === "published";

      return (
        <RowActionsMenu
          disabled={options.actionPending}
          items={[
            {
              key: "edit",
              label: "Edit",
              disabled: archived,
              onSelect: () => options.onEdit(source),
            },
            {
              key: "publish",
              label: "Publish",
              hidden: published || archived,
              onSelect: () => options.onPublish(source),
            },
            {
              key: "reindex",
              label: "Re-index",
              hidden: !published,
              onSelect: () => options.onReindex(source),
            },
            {
              key: "archive",
              label: "Archive",
              hidden: !published,
              onSelect: () => options.onArchive(source),
            },
            { type: "separator", key: "sep" },
            {
              key: "delete",
              label: "Delete",
              destructive: true,
              onSelect: () => options.onDelete(source),
            },
          ]}
        />
      );
    }),
  ];
}
