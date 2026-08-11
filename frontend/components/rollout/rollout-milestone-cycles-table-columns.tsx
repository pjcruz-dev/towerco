"use client";

import type { ColumnDef } from "@tanstack/react-table";

import { RolloutMilestoneCycleLabel } from "@/components/rollout/rollout-milestone-cycle-label";
import { createTextColumn } from "@/components/ui/data-table-column-helpers";
import type { RolloutMilestoneCycle } from "@/modules/rollout/types";

function MilestoneStatusBadge({ status }: { status: RolloutMilestoneCycle["status"] }) {
  const tone =
    status === "completed"
      ? "bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200"
      : status === "active"
        ? "bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200"
        : status === "at_risk"
          ? "bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200"
          : status === "overdue"
            ? "bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200"
            : "bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200";

  return (
    <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize ${tone}`}>
      {status.replaceAll("_", " ")}
    </span>
  );
}

function formatAnchor(anchor: string): string {
  return anchor === "day_one" ? "Day 1" : anchor.replaceAll("_", " ");
}

export const rolloutMilestoneCyclesTableColumns: ColumnDef<RolloutMilestoneCycle>[] = [
  createTextColumn("milestone", "Milestone", (row) => (
    <span className="font-medium">
      <RolloutMilestoneCycleLabel cycle={row} />
    </span>
  )),
  createTextColumn("anchor", "Anchor", (row) => (
    <span className="capitalize text-muted-foreground">{formatAnchor(row.anchor)}</span>
  )),
  createTextColumn("target_working_days", "Target WD", (row) => row.target_working_days),
  createTextColumn(
    "target_date",
    "Target date",
    (row) => <span className="font-mono text-xs">{row.target_date ?? "—"}</span>,
  ),
  createTextColumn("status", "Status", (row) => <MilestoneStatusBadge status={row.status} />),
  createTextColumn("variance_wd", "Variance (wd)", (row) => (
    <span className="font-mono text-xs">
      {row.variance_wd !== null && row.variance_wd !== undefined ? `+${row.variance_wd}` : "—"}
    </span>
  )),
];
