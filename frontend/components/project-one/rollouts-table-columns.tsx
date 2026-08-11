"use client";

import Link from "next/link";
import { ChevronDown, ChevronRight } from "lucide-react";
import type { ColumnDef, OnChangeFn, RowSelectionState } from "@tanstack/react-table";
import { useMemo } from "react";

import { AcronymLabel } from "@/components/help/acronym-label";
import { Checkbox } from "@/components/ui/checkbox";
import { DataTableColumnHeader } from "@/components/ui/data-table-column-header";
import { createRowSelectionColumn } from "@/components/ui/data-table-row-selection";
import { statusToneClassName, rolloutStatusTone } from "@/lib/ui/status-tone";
import type { RolloutListChildRow, RolloutListRow } from "@/modules/rollout/types";

export type RolloutTableDisplayRow = {
  id: string;
  kind: "parent" | "child";
  parentId?: string;
  row: RolloutListRow | RolloutListChildRow;
};

function statusBadge(status: string) {
  const label =
    status === "saq" ? (
      <AcronymLabel term="SAQ">Site acquisition</AcronymLabel>
    ) : status === "permitting" ? (
      <AcronymLabel term="BP">Permitting</AcronymLabel>
    ) : (
      status.replaceAll("_", " ")
    );

  return <span className={statusToneClassName(rolloutStatusTone(status))}>{label}</span>;
}

export function flattenRolloutTableRows(
  rows: RolloutListRow[],
  expandedBatchIds: Set<string>,
): RolloutTableDisplayRow[] {
  const result: RolloutTableDisplayRow[] = [];
  for (const row of rows) {
    result.push({ id: row.id, kind: "parent", row });
    if (row.is_batch && expandedBatchIds.has(row.id)) {
      for (const child of row.batch_children ?? []) {
        result.push({ id: child.id, kind: "child", parentId: row.id, row: child });
      }
    }
  }
  return result;
}

export function selectedIdsToRowSelection(selectedIds: Set<string>): RowSelectionState {
  const next: RowSelectionState = {};
  for (const id of selectedIds) {
    next[id] = true;
  }
  return next;
}

export function applyRowSelectionToSelectedIds(
  updater: Parameters<OnChangeFn<RowSelectionState>>[0],
  current: Set<string>,
  onChange: (next: Set<string>) => void,
): void {
  const currentState = selectedIdsToRowSelection(current);
  const nextState = typeof updater === "function" ? updater(currentState) : updater;
  onChange(new Set(Object.keys(nextState).filter((id) => nextState[id])));
}

export function createRolloutsTableColumns(options: {
  canManage: boolean;
  expandedBatchIds: Set<string>;
  onToggleBatch: (batchId: string) => void;
}): ColumnDef<RolloutTableDisplayRow, unknown>[] {
  const columns: ColumnDef<RolloutTableDisplayRow, unknown>[] = [];

  if (options.canManage) {
    columns.push({
      ...createRowSelectionColumn<RolloutTableDisplayRow>(),
      cell: ({ row }) => {
        if (row.original.kind === "child") {
          return null;
        }
        const listRow = row.original.row as RolloutListRow;
        return (
          <Checkbox
            className="size-4"
            aria-label={`Select ${listRow.rollout_ref}`}
            checked={row.getIsSelected()}
            disabled={!row.getCanSelect()}
            onCheckedChange={(v) => row.toggleSelected(v === true)}
          />
        );
      },
    });
  }

  columns.push(
    {
      id: "reference",
      accessorFn: (row) => row.row.rollout_ref,
      enableSorting: true,
      header: ({ column }) => <DataTableColumnHeader column={column} title="Reference" />,
      cell: ({ row }) => {
        const display = row.original;
        const listRow = display.row;
        if (display.kind === "child") {
          return (
            <span className="pl-8">
              <span className="text-xs text-muted-foreground">↳</span>{" "}
              <Link
                className="font-medium text-primary underline-offset-4 hover:underline"
                href={`/project-one/rollouts/${listRow.id}`}
              >
                {listRow.rollout_ref}
              </Link>
              {"parent_rollout_ref" in listRow && listRow.parent_rollout_ref ? (
                <p className="mt-0.5 font-mono text-[11px] text-muted-foreground">
                  Batch {listRow.parent_rollout_ref}
                </p>
              ) : null}
            </span>
          );
        }

        const parent = listRow as RolloutListRow;
        const isBatch = Boolean(parent.is_batch);
        const children = parent.batch_children ?? [];
        const expanded = options.expandedBatchIds.has(parent.id);

        return (
          <div className="flex items-start gap-1">
            {isBatch && children.length > 0 ? (
              <button
                type="button"
                className="mt-0.5 inline-flex size-7 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
                aria-expanded={expanded}
                aria-label={expanded ? "Collapse batch sites" : "Expand batch sites"}
                onClick={() => options.onToggleBatch(parent.id)}
              >
                {expanded ? <ChevronDown className="size-4" aria-hidden /> : <ChevronRight className="size-4" aria-hidden />}
              </button>
            ) : (
              <span className="inline-block w-7 shrink-0" aria-hidden />
            )}
            <div className="min-w-0">
              <Link
                className="font-medium text-primary underline-offset-4 hover:underline"
                href={`/project-one/rollouts/${parent.id}`}
              >
                {parent.rollout_ref}
              </Link>
            </div>
          </div>
        );
      },
    },
    {
      id: "search_ring",
      header: () => <AcronymLabel term="SR">Search ring</AcronymLabel>,
      cell: ({ row }) => (
        <span className="max-w-[180px] truncate text-muted-foreground">
          {row.original.row.search_ring_name ?? "—"}
        </span>
      ),
    },
    {
      id: "mno",
      header: () => <AcronymLabel term="MNO" />,
      cell: ({ row }) => <span className="uppercase">{row.original.row.mno}</span>,
    },
    {
      id: "type",
      header: "Type",
      cell: ({ row }) => <span className="uppercase">{row.original.row.project_type}</span>,
    },
    {
      id: "status",
      header: "Status",
      cell: ({ row }) => {
        const listRow = row.original.row;
        const isBatch = "is_batch" in listRow && listRow.is_batch;
        if (isBatch) {
          return (
            <span className="inline-flex rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-800 dark:bg-violet-950 dark:text-violet-200">
              Batch · {listRow.child_count ?? 0} sites
            </span>
          );
        }
        return statusBadge(listRow.status);
      },
    },
    {
      id: "region",
      header: "Region",
      cell: ({ row }) => (
        <span className="uppercase text-muted-foreground">{row.original.row.region ?? "—"}</span>
      ),
    },
    {
      id: "tco",
      header: () => <AcronymLabel term="TCO ID">TCO Site ID</AcronymLabel>,
      cell: ({ row }) => (
        <span className="font-mono text-xs">{row.original.row.tco_site_id ?? "—"}</span>
      ),
    },
    {
      id: "sla",
      header: () => <AcronymLabel term="SLA">SLA (wd)</AcronymLabel>,
      cell: ({ row }) =>
        "sla_working_days" in row.original.row ? row.original.row.sla_working_days : "—",
    },
    {
      id: "target_rfi",
      accessorFn: (row) => row.row.target_rfi_working_date ?? "",
      enableSorting: true,
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title="Target RFI" />
      ),
      cell: ({ row }) => row.original.row.target_rfi_working_date ?? "—",
    },
    {
      id: "candidates",
      header: () => <span className="block w-full text-right">Candidates</span>,
      cell: ({ row }) => (
        <span className="block text-right">
          {"candidate_count" in row.original.row ? row.original.row.candidate_count : "—"}
        </span>
      ),
    },
  );

  return columns;
}

export function useRolloutsTableColumns(options: {
  canManage: boolean;
  expandedBatchIds: Set<string>;
  onToggleBatch: (batchId: string) => void;
}): ColumnDef<RolloutTableDisplayRow, unknown>[] {
  return useMemo(
    () =>
      createRolloutsTableColumns({
        canManage: options.canManage,
        expandedBatchIds: options.expandedBatchIds,
        onToggleBatch: options.onToggleBatch,
      }),
    [options.canManage, options.expandedBatchIds, options.onToggleBatch],
  );
}
