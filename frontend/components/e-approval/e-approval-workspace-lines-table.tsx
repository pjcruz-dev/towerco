"use client";

import Link from "next/link";
import { useMemo, type ReactNode } from "react";
import type { ColumnDef } from "@tanstack/react-table";

import { EApprovalStatusBadge } from "@/components/e-approval/e-approval-status-badge";
import { PaginatedListFooter } from "@/components/registry/paginated-list-footer";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { Button, buttonVariants } from "@/components/ui/button";
import type { PaginatedMeta } from "@/lib/api/paginated";
import type { EApprovalWorkspaceLineRow } from "@/lib/api/modules/e-approval-api";

export type WorkspaceListMode = "requests" | "lines";

type LineMeta = PaginatedMeta & { truncated?: boolean };

type Props = {
  rows: EApprovalWorkspaceLineRow[];
  meta?: LineMeta;
  isLoading: boolean;
  isError: boolean;
  onRetry: () => void;
  onPageChange: (page: number) => void;
  toolbarStart?: ReactNode;
};

export function EApprovalWorkspaceLinesTable({
  rows,
  meta,
  isLoading,
  isError,
  onRetry,
  onPageChange,
  toolbarStart,
}: Props) {
  const columns = useMemo((): ColumnDef<EApprovalWorkspaceLineRow>[] => {
    return [
      {
        id: "document_no",
        accessorKey: "document_no",
        header: "Document",
        cell: ({ row }) => (
          <Link href={`/e-approval/submissions/${row.original.submission_id}`} className="font-medium text-primary hover:underline">
            {row.original.document_no}
          </Link>
        ),
      },
      {
        id: "requestor_name",
        accessorKey: "requestor_name",
        header: "Requestor",
        cell: ({ row }) => <span className="text-muted-foreground">{row.original.requestor_name || "—"}</span>,
      },
      {
        id: "subsidiary",
        accessorKey: "subsidiary",
        header: "Subsidiary",
        cell: ({ row }) => row.original.subsidiary || "—",
      },
      {
        id: "date_range",
        accessorKey: "date_range",
        header: "Date range",
        cell: ({ row }) => row.original.date_range || "—",
      },
      {
        id: "amount",
        accessorKey: "amount",
        header: () => <span className="block w-full text-right">Amount</span>,
        cell: ({ row }) => (
          <span className="block text-right tabular-nums">{row.original.amount || "—"}</span>
        ),
      },
      {
        id: "status",
        accessorKey: "status",
        header: "Status",
        cell: ({ row }) => <EApprovalStatusBadge status={row.original.status} kind="submission" />,
      },
      {
        id: "open",
        header: () => <span className="block w-full text-right">Open</span>,
        cell: ({ row }) => (
          <div className="flex justify-end">
            <Link
              href={`/e-approval/submissions/${row.original.submission_id}`}
              className={buttonVariants({ size: "sm", variant: "outline" })}
            >
              Open
            </Link>
          </div>
        ),
      },
    ];
  }, []);

  if (isError) {
    return (
      <div className="flex flex-wrap items-center justify-between gap-3 px-4 py-6 text-sm">
        <p className="text-destructive">Could not load line items.</p>
        <button type="button" className={buttonVariants({ size: "sm", variant: "outline" })} onClick={onRetry}>
          Retry
        </button>
      </div>
    );
  }

  return (
    <div>
      <RegistryDataTableView
        columns={columns}
        data={rows}
        getRowId={(row) => row.id}
        isLoading={isLoading && rows.length === 0}
        isEmpty={!isLoading && rows.length === 0}
        emptyMessage="No line items match this filter."
        toolbarStart={toolbarStart}
      />
      {meta ? (
        <PaginatedListFooter meta={meta} onPageChange={onPageChange} isPending={isLoading} />
      ) : null}
      {meta?.truncated ? (
        <p className="px-4 py-2 text-xs text-muted-foreground">
          Showing lines from the latest 200 requests. Narrow the filters to see the rest.
        </p>
      ) : null}
    </div>
  );
}

export function EApprovalWorkspaceViewSwitch({
  mode,
  onChange,
}: {
  mode: WorkspaceListMode;
  onChange: (mode: WorkspaceListMode) => void;
}) {
  return (
    <div className="flex items-center gap-1 rounded-md border border-border bg-muted/30 p-0.5">
      <Button
        type="button"
        size="sm"
        variant={mode === "requests" ? "secondary" : "ghost"}
        className="h-7 px-2 text-xs"
        onClick={() => onChange("requests")}
      >
        Requests
      </Button>
      <Button
        type="button"
        size="sm"
        variant={mode === "lines" ? "secondary" : "ghost"}
        className="h-7 px-2 text-xs"
        onClick={() => onChange("lines")}
      >
        Lines
      </Button>
    </div>
  );
}
