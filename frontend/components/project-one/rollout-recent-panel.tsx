"use client";

import Link from "next/link";
import { useMemo } from "react";
import type { ColumnDef } from "@tanstack/react-table";

import { AcronymLabel } from "@/components/help/acronym-label";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import type { ProjectOneDashboardResponse } from "@/modules/project-one/types";

type RecentRollout = NonNullable<
  NonNullable<ProjectOneDashboardResponse["rollouts"]>["recent_rollouts"]
>[number];

export function RolloutRecentPanel({ rollouts }: { rollouts: NonNullable<ProjectOneDashboardResponse["rollouts"]> }) {
  const rows = rollouts.recent_rollouts ?? [];

  const columns = useMemo<ColumnDef<RecentRollout>[]>(
    () => [
      {
        accessorKey: "rollout_ref",
        header: "Reference",
        cell: ({ row }) => (
          <Link
            className="font-medium text-primary underline-offset-4 hover:underline"
            href={`/project-one/rollouts/${row.original.id}`}
          >
            {row.original.rollout_ref}
          </Link>
        ),
      },
      {
        accessorKey: "mno",
        header: () => <AcronymLabel term="MNO" />,
        cell: ({ row }) => <span className="uppercase">{row.original.mno}</span>,
      },
      {
        accessorKey: "status",
        header: "Status",
        cell: ({ row }) => (
          <span className="capitalize">{row.original.status.replaceAll("_", " ")}</span>
        ),
      },
      {
        accessorKey: "target_rfi_working_date",
        header: () => <AcronymLabel term="RFI / RFTI">Target RFI</AcronymLabel>,
        cell: ({ row }) => row.original.target_rfi_working_date ?? "—",
      },
    ],
    [],
  );

  return (
    <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div>
          <h2 className="text-lg font-semibold text-foreground">Rollout programs</h2>
          <p className="text-xs text-muted-foreground">
            {rollouts.active_rollouts} active · {rollouts.awaiting_day_one} awaiting Day-1 · {rollouts.sla_at_risk}{" "}
            <AcronymLabel term="SLA" /> at risk
          </p>
        </div>
        <Link className="text-xs font-medium text-primary underline-offset-4 hover:underline" href="/project-one/rollouts">
          View all
        </Link>
      </div>

      <div className="mt-4 overflow-hidden rounded-lg border border-border">
        <RegistryDataTableView
          columns={columns}
          data={rows}
          getRowId={(row) => row.id}
          isEmpty={rows.length === 0}
          emptyMessage="No rollout programs yet."
          scrollClassName="max-h-none"
          enableColumnVisibility
          columnVisibilityStorageKey="toweros.table.columns.project-one.rollout-recent"
        />
      </div>
    </section>
  );
}
