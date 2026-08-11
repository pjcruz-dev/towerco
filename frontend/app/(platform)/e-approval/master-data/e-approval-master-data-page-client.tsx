"use client";

import Link from "next/link";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useMemo, useState } from "react";

import {
  createEApprovalMasterDataRowsTableColumns,
  eApprovalMasterDataSetsTableColumns,
} from "@/components/e-approval/e-approval-master-data-table-columns";
import { PermissionGate } from "@/components/layout/permission-gate";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  bulkImportEApprovalMasterDataRows,
  createEApprovalMasterDataRow,
  createEApprovalMasterDataSet,
  deleteEApprovalMasterDataRow,
  fetchEApprovalMasterDataRows,
  fetchEApprovalMasterDataSets,
} from "@/lib/api/modules/e-approval-api";
import { getErrorMessage } from "@/lib/api/error";
import { permissions } from "@/lib/rbac/permissions";
import { useNotificationStore } from "@/stores/notification-store";

export function EApprovalMasterDataPageClient() {
  const queryClient = useQueryClient();
  const push = useNotificationStore((s) => s.push);
  const [selectedSetId, setSelectedSetId] = useState<string | null>(null);
  const [newKey, setNewKey] = useState("");
  const [newName, setNewName] = useState("");
  const [rowLabel, setRowLabel] = useState("");
  const [rowCode, setRowCode] = useState("");
  const [bulkJson, setBulkJson] = useState("");

  const setsQuery = useQuery({
    queryKey: ["e-approval", "master-data-sets"],
    queryFn: fetchEApprovalMasterDataSets,
  });

  const rowsQuery = useQuery({
    queryKey: ["e-approval", "master-data-rows", selectedSetId],
    queryFn: () => fetchEApprovalMasterDataRows(selectedSetId!),
    enabled: !!selectedSetId,
  });

  const invalidateRows = () => {
    queryClient.invalidateQueries({ queryKey: ["e-approval", "master-data-rows", selectedSetId] });
    queryClient.invalidateQueries({ queryKey: ["e-approval", "master-data-sets"] });
  };

  const createSetMutation = useMutation({
    mutationFn: () => createEApprovalMasterDataSet({ key: newKey, name: newName || newKey }),
    onSuccess: () => {
      setNewKey("");
      setNewName("");
      queryClient.invalidateQueries({ queryKey: ["e-approval", "master-data-sets"] });
      push({ level: "success", title: "Master data set created" });
    },
    onError: (e) => push({ level: "error", title: "Create failed", message: getErrorMessage(e) }),
  });

  const addRowMutation = useMutation({
    mutationFn: () =>
      createEApprovalMasterDataRow(selectedSetId!, {
        label: rowLabel,
        code: rowCode || undefined,
      }),
    onSuccess: () => {
      setRowLabel("");
      setRowCode("");
      invalidateRows();
      push({ level: "success", title: "Row added" });
    },
    onError: (e) => push({ level: "error", title: "Add row failed", message: getErrorMessage(e) }),
  });

  const bulkMutation = useMutation({
    mutationFn: () => {
      const rows = JSON.parse(bulkJson) as Record<string, unknown>[];
      return bulkImportEApprovalMasterDataRows(selectedSetId!, rows);
    },
    onSuccess: (result) => {
      setBulkJson("");
      invalidateRows();
      push({ level: "success", title: `Imported ${result.created} row(s)` });
    },
    onError: (e) => push({ level: "error", title: "Bulk import failed", message: getErrorMessage(e) }),
  });

  const sets = setsQuery.data ?? [];
  const rows = rowsQuery.data ?? [];

  const rowsColumns = useMemo(
    () =>
      createEApprovalMasterDataRowsTableColumns({
        onDelete: async (id) => {
          try {
            await deleteEApprovalMasterDataRow(id);
            queryClient.invalidateQueries({ queryKey: ["e-approval", "master-data-rows", selectedSetId] });
            queryClient.invalidateQueries({ queryKey: ["e-approval", "master-data-sets"] });
          } catch (e) {
            push({ level: "error", title: "Delete failed", message: getErrorMessage(e) });
          }
        },
      }),
    [push, queryClient, selectedSetId],
  );

  return (
    <PermissionGate requiredPermissions={[permissions.eApprovalSettingsManage]}>
      <div className="space-y-6">
        <header>
          <h1 className="text-2xl font-semibold tracking-tight text-foreground">Master data</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            <Link href="/e-approval/settings" className="text-primary hover:underline">
              Settings
            </Link>
          </p>
        </header>

        <section className="flex flex-wrap gap-2 rounded-xl border border-border bg-card p-4">
          <Input placeholder="Key (e.g. vendors)" value={newKey} onChange={(e) => setNewKey(e.target.value)} className="max-w-xs" />
          <Input placeholder="Display name" value={newName} onChange={(e) => setNewName(e.target.value)} className="max-w-xs" />
          <Button size="sm" onClick={() => createSetMutation.mutate()} disabled={!newKey.trim() || createSetMutation.isPending}>
            Add set
          </Button>
        </section>

        <div className="grid gap-4 lg:grid-cols-2">
          <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
            <RegistryDataTableView
              columns={eApprovalMasterDataSetsTableColumns}
              data={sets}
              getRowId={(row) => row.id}
              isLoading={setsQuery.isFetching && sets.length === 0}
              isEmpty={!setsQuery.isFetching && sets.length === 0}
              emptyMessage="No master data sets yet."
              getRowClassName={(row) => (selectedSetId === row.original.id ? "bg-muted/50" : undefined)}
              onRowClick={(row) => setSelectedSetId(row.original.id)}
              enableColumnVisibility
              columnVisibilityStorageKey="toweros.table.columns.e-approval.master-data.sets"
            />
          </div>

          <div className="space-y-4 rounded-xl border border-border bg-card p-4 shadow-sm">
            <h2 className="text-base font-medium">Rows</h2>
            {!selectedSetId ? (
              <p className="text-sm text-muted-foreground">Select a set to manage rows.</p>
            ) : (
              <>
                <div className="flex flex-wrap gap-2">
                  <Input placeholder="Label" value={rowLabel} onChange={(e) => setRowLabel(e.target.value)} className="max-w-[180px]" />
                  <Input placeholder="Code" value={rowCode} onChange={(e) => setRowCode(e.target.value)} className="max-w-[120px]" />
                  <Button size="sm" onClick={() => addRowMutation.mutate()} disabled={!rowLabel.trim() || addRowMutation.isPending}>
                    Add row
                  </Button>
                </div>
                <RegistryDataTableView
                  columns={rowsColumns}
                  data={rows}
                  getRowId={(row) => row.id}
                  isLoading={rowsQuery.isFetching && rows.length === 0}
                  isEmpty={!rowsQuery.isFetching && rows.length === 0}
                  emptyMessage="No rows in this set."
                  scrollClassName="max-h-none"
                  enableColumnVisibility
                  columnVisibilityStorageKey="toweros.table.columns.e-approval.master-data.rows"
                />
                <div className="space-y-2">
                  <p className="text-xs text-muted-foreground">
                    Bulk import: paste a JSON array of objects with label and optional code.
                  </p>
                  <textarea
                    className="min-h-[80px] w-full rounded-md border border-input bg-background px-2 py-1 font-mono text-xs"
                    value={bulkJson}
                    onChange={(e) => setBulkJson(e.target.value)}
                  />
                  <Button size="sm" variant="outline" onClick={() => bulkMutation.mutate()} disabled={!bulkJson.trim() || bulkMutation.isPending}>
                    Import bulk
                  </Button>
                </div>
              </>
            )}
          </div>
        </div>
      </div>
    </PermissionGate>
  );
}
