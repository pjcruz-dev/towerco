"use client";

import { useQuery } from "@tanstack/react-query";
import { Download, FileDown, RefreshCw } from "lucide-react";
import Link from "next/link";
import { useMemo } from "react";
import type { ColumnDef } from "@tanstack/react-table";

import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { getErrorMessage } from "@/lib/api/error";
import {
  downloadEApprovalExportHistoryFile,
  fetchEApprovalExportHistory,
  type EApprovalExportHistoryRow,
} from "@/lib/api/modules/e-approval-api";
import {
  downloadModuleListExportFile,
  fetchModuleListExports,
  type ModuleListExportRow,
} from "@/lib/api/modules/module-list-exports-api";
import { cn } from "@/lib/utils";
import { useNotificationStore } from "@/stores/notification-store";
import { usePermission } from "@/hooks/use-permission";
import { permissions } from "@/lib/rbac/permissions";

function moduleLabel(module: string): string {
  if (module === "doc-extract") return "DocExtract";
  if (module === "ticketing") return "Ticketing";
  return module;
}

function statusClass(status: string): string {
  if (status === "completed") {
    return "border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200";
  }
  if (status === "failed") {
    return "border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200";
  }
  if (status === "processing" || status === "queued") {
    return "border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-200";
  }
  return "";
}

export function ModuleListExportsPageClient() {
  const push = useNotificationStore((state) => state.push);
  const canEFormsExports = usePermission([
    permissions.eApprovalAuditView,
    permissions.eApprovalSubmissionsView,
  ]);

  const query = useQuery({
    queryKey: ["module-list-exports"],
    queryFn: () => fetchModuleListExports(50),
    refetchInterval: (current) => {
      const rows = current.state.data ?? [];
      return rows.some((row) => row.status === "queued" || row.status === "processing") ? 4000 : false;
    },
  });

  const eformsQuery = useQuery({
    queryKey: ["e-approval", "export-history", "my-exports"],
    queryFn: () => fetchEApprovalExportHistory(20),
    enabled: canEFormsExports,
    refetchInterval: (current) => {
      const rows = current.state.data ?? [];
      return rows.some((row) => row.status === "queued" || row.status === "processing") ? 4000 : false;
    },
  });

  const columns = useMemo<ColumnDef<ModuleListExportRow>[]>(
    () => [
      {
        accessorKey: "filename",
        header: "File",
        cell: ({ row }) => (
          <div className="min-w-0">
            <p className="truncate text-sm font-medium text-foreground">{row.original.filename}</p>
            <p className="text-xs text-muted-foreground">{moduleLabel(row.original.module)}</p>
          </div>
        ),
      },
      {
        accessorKey: "status",
        header: "Status",
        cell: ({ row }) => (
          <Badge variant="outline" className={cn("capitalize", statusClass(row.original.status))}>
            {row.original.status}
          </Badge>
        ),
      },
      {
        id: "rows",
        header: "Rows",
        cell: ({ row }) => (
          <span className="tabular-nums text-sm text-muted-foreground">
            {row.original.exported_rows.toLocaleString()}
            {row.original.matched_rows > 0
              ? ` / ${row.original.matched_rows.toLocaleString()}`
              : ""}
            {row.original.truncated ? " · truncated" : ""}
          </span>
        ),
      },
      {
        accessorKey: "created_at",
        header: "Created",
        cell: ({ row }) => (
          <span className="text-sm text-muted-foreground">
            {row.original.created_at ? new Date(row.original.created_at).toLocaleString() : "—"}
          </span>
        ),
      },
      {
        id: "actions",
        header: "",
        cell: ({ row }) => {
          const ready = row.original.status === "completed" && Boolean(row.original.download?.url);
          return (
            <Button
              type="button"
              size="sm"
              variant="outline"
              className="h-8 gap-1.5"
              disabled={!ready}
              onClick={() => {
                void downloadModuleListExportFile(row.original).catch((error) => {
                  push({
                    level: "error",
                    title: "Download failed",
                    message: getErrorMessage(error),
                  });
                });
              }}
            >
              <Download className="size-3.5" aria-hidden />
              Download
            </Button>
          );
        },
      },
    ],
    [push],
  );

  const eformsColumns = useMemo<ColumnDef<EApprovalExportHistoryRow>[]>(
    () => [
      {
        accessorKey: "name",
        header: "Name",
        cell: ({ row }) => (
          <div className="min-w-0">
            <p className="truncate text-sm font-medium text-foreground">
              {row.original.name ?? "Export"}
            </p>
            <p className="text-xs capitalize text-muted-foreground">{row.original.triggered_by}</p>
          </div>
        ),
      },
      {
        accessorKey: "status",
        header: "Status",
        cell: ({ row }) => (
          <Badge variant="outline" className={cn("capitalize", statusClass(row.original.status))}>
            {row.original.status}
          </Badge>
        ),
      },
      {
        accessorKey: "format",
        header: "Format",
        cell: ({ row }) => (
          <span className="uppercase text-sm text-muted-foreground">{row.original.format}</span>
        ),
      },
      {
        id: "rows",
        header: "Rows",
        cell: ({ row }) => (
          <span className="tabular-nums text-sm text-muted-foreground">
            {row.original.exported_rows.toLocaleString()}
            {row.original.matched_rows > row.original.exported_rows
              ? ` / ${row.original.matched_rows.toLocaleString()}`
              : ""}
          </span>
        ),
      },
      {
        accessorKey: "created_at",
        header: "Created",
        cell: ({ row }) => (
          <span className="text-sm text-muted-foreground">
            {row.original.created_at ? new Date(row.original.created_at).toLocaleString() : "—"}
          </span>
        ),
      },
      {
        id: "actions",
        header: "",
        cell: ({ row }) => {
          const ready = row.original.status === "completed" && Boolean(row.original.download);
          return (
            <Button
              type="button"
              size="sm"
              variant="outline"
              className="h-8 gap-1.5"
              disabled={!ready}
              onClick={() => {
                void downloadEApprovalExportHistoryFile(row.original).catch((error) => {
                  push({
                    level: "error",
                    title: "Download failed",
                    message: getErrorMessage(error),
                  });
                });
              }}
            >
              <Download className="size-3.5" aria-hidden />
              Download
            </Button>
          );
        },
      },
    ],
    [push],
  );

  const refreshAll = () => {
    void query.refetch();
    if (canEFormsExports) void eformsQuery.refetch();
  };

  return (
    <div className="w-full space-y-8">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold text-foreground">My exports</h1>
          <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
            Queued DocExtract / Ticketing files and recent E-Forms exports in one place.
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Button size="sm" variant="outline" render={<Link href="/e-approval/reports" />}>
            E-Forms reports
          </Button>
          <Button
            type="button"
            size="sm"
            variant="outline"
            className="gap-1.5"
            onClick={refreshAll}
            disabled={query.isFetching || eformsQuery.isFetching}
          >
            <RefreshCw
              className={
                query.isFetching || eformsQuery.isFetching ? "size-3.5 animate-spin" : "size-3.5"
              }
              aria-hidden
            />
            Refresh
          </Button>
        </div>
      </div>

      <section className="space-y-3">
        <div>
          <h2 className="text-base font-medium text-foreground">List exports</h2>
          <p className="text-sm text-muted-foreground">
            DocExtract and Ticketing queued downloads (expire after 7 days).
          </p>
        </div>
        {query.isError ? (
          <p className="text-sm text-destructive">{getErrorMessage(query.error)}</p>
        ) : (
          <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
            <RegistryDataTableView
              columns={columns}
              data={query.data ?? []}
              getRowId={(row) => row.id}
              isLoading={query.isLoading}
              isEmpty={!query.isLoading && (query.data?.length ?? 0) === 0}
              emptyMessage="No queued list exports yet."
              enableColumnVisibility={false}
              enableNamedLayouts={false}
            />
          </div>
        )}
      </section>

      {canEFormsExports ? (
        <section className="space-y-3">
          <div className="flex flex-wrap items-end justify-between gap-2">
            <div>
              <h2 className="text-base font-medium text-foreground">E-Forms exports</h2>
              <p className="text-sm text-muted-foreground">
                Recent report / submission exports. Manage schedules on Reports.
              </p>
            </div>
            <Button size="sm" variant="ghost" render={<Link href="/e-approval/reports" />}>
              Open Reports
            </Button>
          </div>
          {eformsQuery.isError ? (
            <p className="text-sm text-destructive">{getErrorMessage(eformsQuery.error)}</p>
          ) : (
            <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
              <RegistryDataTableView
                columns={eformsColumns}
                data={eformsQuery.data ?? []}
                getRowId={(row) => row.id}
                isLoading={eformsQuery.isLoading}
                isEmpty={!eformsQuery.isLoading && (eformsQuery.data?.length ?? 0) === 0}
                emptyMessage="No E-Forms exports yet."
                enableColumnVisibility={false}
                enableNamedLayouts={false}
              />
            </div>
          )}
        </section>
      ) : null}

      {!query.isLoading && (query.data?.length ?? 0) === 0 ? (
        <p className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
          <FileDown className="size-3.5" aria-hidden />
          Tip: filter a large DocExtract or Ticketing list and choose Export — oversized jobs queue under List
          exports.
        </p>
      ) : null}
    </div>
  );
}
