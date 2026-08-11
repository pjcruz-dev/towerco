"use client";

import Link from "next/link";
import { useMemo } from "react";
import type { ColumnDef } from "@tanstack/react-table";

import { AcronymLabel } from "@/components/help/acronym-label";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import type { RolloutDetail } from "@/modules/rollout/types";

type Props = {
  detail: RolloutDetail | undefined;
};

type ColocationTenant = NonNullable<RolloutDetail["colocation_tenants"]>[number];

export function RolloutColocationTenantsPanel({ detail }: Props) {
  const tenants = detail?.colocation_tenants ?? [];

  const columns = useMemo<ColumnDef<ColocationTenant>[]>(
    () => [
      {
        accessorKey: "rollout_ref",
        header: "Rollout",
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
        header: "MNO",
        cell: ({ row }) => <span className="uppercase">{row.original.mno}</span>,
      },
      {
        accessorKey: "site_name",
        header: "Site name",
        cell: ({ row }) => row.original.site_name ?? "—",
      },
      {
        accessorKey: "tco_site_id",
        header: () => <AcronymLabel term="TCO ID">TCO Site ID</AcronymLabel>,
        cell: ({ row }) => (
          <span className="font-mono text-xs">{row.original.tco_site_id ?? "—"}</span>
        ),
      },
      {
        accessorKey: "actual_rfi_date",
        header: () => <AcronymLabel term="RFI / RFTI">RFI date</AcronymLabel>,
        cell: ({ row }) => row.original.actual_rfi_date ?? "—",
      },
      {
        accessorKey: "site_license_remarks",
        header: "SL remarks",
        cell: ({ row }) => (
          <span className="max-w-xs truncate text-muted-foreground">
            {row.original.site_license_remarks ?? "—"}
          </span>
        ),
      },
    ],
    [],
  );

  if (tenants.length === 0) {
    return null;
  }

  return (
    <section className="space-y-3 rounded-xl border border-border bg-card p-4 shadow-sm">
      <div>
        <h2 className="text-base font-medium text-foreground">Colocation tenants</h2>
        <p className="mt-1 text-xs text-muted-foreground">
          Each additional MNO tenant is a child rollout — not duplicate columns on the anchor site.
        </p>
      </div>

      <RegistryDataTableView
        columns={columns}
        data={tenants}
        getRowId={(row) => row.id}
        scrollClassName="max-h-none"
        isEmpty={tenants.length === 0}
        emptyMessage="No colocation tenants on this rollout."
        enableColumnVisibility
        columnVisibilityStorageKey="toweros.table.columns.project-one.rollout-colocation-tenants"
      />
    </section>
  );
}
