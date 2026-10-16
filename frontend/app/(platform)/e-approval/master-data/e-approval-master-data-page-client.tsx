"use client";

import Link from "next/link";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Download, Upload } from "lucide-react";
import { useMemo, useRef, useState } from "react";

import { EApprovalMasterDataImportDialog } from "@/components/e-approval/e-approval-master-data-import-dialog";

import {
  createEApprovalMasterDataRowsTableColumns,
  eApprovalMasterDataSetsTableColumns,
  type EApprovalMasterDataColumn,
} from "@/components/e-approval/e-approval-master-data-table-columns";
import { PermissionGate } from "@/components/layout/permission-gate";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { SelectField } from "@/components/ui/select-field";
import {
  createEApprovalMasterDataRow,
  createEApprovalMasterDataSet,
  deleteEApprovalMasterDataRow,
  fetchEApprovalMasterDataRows,
  fetchEApprovalMasterDataSets,
  updateEApprovalMasterDataSet,
} from "@/lib/api/modules/e-approval-api";
import { getErrorMessage } from "@/lib/api/error";
import { masterDataRowsToCsv } from "@/modules/e-approval/master-data-csv";
import { permissions } from "@/lib/rbac/permissions";
import { useNotificationStore } from "@/stores/notification-store";

const DEFAULT_COLUMNS: Record<string, EApprovalMasterDataColumn[]> = {
  sites: [
    { key: "address", label: "Address", type: "text" },
    { key: "name", label: "Name", type: "text" },
  ],
  expense_types: [
    { key: "vatable", label: "VATable", type: "boolean" },
    { key: "per_diem", label: "Per diem", type: "boolean" },
  ],
};

function columnKeyFromLabel(label: string): string {
  return label
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "_")
    .replace(/^_+|_+$/g, "")
    .slice(0, 40);
}

function columnsForSet(set: { key: string; config_json?: { columns?: EApprovalMasterDataColumn[] } | null } | undefined): EApprovalMasterDataColumn[] {
  const saved = set?.config_json?.columns;
  if (Array.isArray(saved)) {
    return saved;
  }
  return DEFAULT_COLUMNS[set?.key ?? ""] ?? [];
}

export function EApprovalMasterDataPageClient() {
  const queryClient = useQueryClient();
  const push = useNotificationStore((s) => s.push);
  const [selectedSetId, setSelectedSetId] = useState<string | null>(null);
  const [newKey, setNewKey] = useState("");
  const [newName, setNewName] = useState("");
  const [rowLabel, setRowLabel] = useState("");
  const [rowCode, setRowCode] = useState("");
  const [extraValues, setExtraValues] = useState<Record<string, string>>({});
  const [columnLabel, setColumnLabel] = useState("");
  const [columnType, setColumnType] = useState<EApprovalMasterDataColumn["type"]>("text");
  const [renamingKey, setRenamingKey] = useState<string | null>(null);
  const [renameDraft, setRenameDraft] = useState("");
  const renameCancel = useRef(false);
  const [importOpen, setImportOpen] = useState(false);

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

  const sets = setsQuery.data ?? [];
  const rows = rowsQuery.data ?? [];
  const selectedSet = sets.find((set) => set.id === selectedSetId);
  const extraColumns = columnsForSet(selectedSet);

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
    mutationFn: () => {
      const data: Record<string, unknown> = {};
      for (const column of extraColumns) {
        const raw = (extraValues[column.key] ?? "").trim();
        if (raw === "") {
          continue;
        }
        if (column.type === "boolean") {
          data[column.key] = raw === "true";
        } else if (column.type === "number") {
          const parsed = Number(raw);
          data[column.key] = Number.isFinite(parsed) ? parsed : raw;
        } else {
          data[column.key] = raw;
        }
      }
      return createEApprovalMasterDataRow(selectedSetId!, {
        label: rowLabel,
        code: rowCode || undefined,
        data: Object.keys(data).length > 0 ? data : undefined,
      });
    },
    onSuccess: () => {
      setRowLabel("");
      setRowCode("");
      setExtraValues({});
      invalidateRows();
      push({ level: "success", title: "Row added" });
    },
    onError: (e) => push({ level: "error", title: "Add row failed", message: getErrorMessage(e) }),
  });

  const saveColumnsMutation = useMutation({
    mutationFn: (columns: EApprovalMasterDataColumn[]) =>
      updateEApprovalMasterDataSet(selectedSetId!, { config_json: { columns } }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["e-approval", "master-data-sets"] });
      push({ level: "success", title: "Columns updated" });
    },
    onError: (e) => push({ level: "error", title: "Column update failed", message: getErrorMessage(e) }),
  });

  const rowsColumns = useMemo(
    () =>
      createEApprovalMasterDataRowsTableColumns({
        columns: extraColumns,
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
    [extraColumns, push, queryClient, selectedSetId],
  );

  const addColumn = () => {
    const key = columnKeyFromLabel(columnLabel);
    if (!selectedSetId || key === "" || ["label", "code", "id", "data"].includes(key)) {
      push({ level: "error", title: "Column name is required" });
      return;
    }
    if (extraColumns.some((column) => column.key === key)) {
      push({ level: "error", title: "That column already exists" });
      return;
    }
    if (extraColumns.length >= 12) {
      push({ level: "error", title: "A set can have 12 extra columns" });
      return;
    }
    saveColumnsMutation.mutate([
      ...extraColumns,
      { key, label: columnLabel.trim(), type: columnType },
    ]);
    setColumnLabel("");
    setColumnType("text");
  };

  const commitRename = (key: string) => {
    if (renameCancel.current) {
      renameCancel.current = false;
      setRenamingKey(null);
      return;
    }
    const label = renameDraft.trim();
    setRenamingKey(null);
    const current = extraColumns.find((column) => column.key === key);
    if (!current || label === "" || current.label === label) {
      return;
    }
    saveColumnsMutation.mutate(
      extraColumns.map((column) => (column.key === key ? { ...column, label } : column)),
    );
  };

  return (
    <PermissionGate requiredPermissions={[permissions.eApprovalSettingsManage]}>
      <div className="space-y-6">
        <header>
          <h1 className="text-2xl font-semibold tracking-tight text-foreground">Master data</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Forecast lines read these sets: <span className="font-medium text-foreground">expense_types</span>,{" "}
            <span className="font-medium text-foreground">departments</span>, and{" "}
            <span className="font-medium text-foreground">sites</span>. Add or edit a row here and the dropdown uses it.
          </p>
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
            <div className="flex flex-wrap items-center justify-between gap-2">
              <h2 className="text-base font-medium">Rows</h2>
              {selectedSet ? (
                <div className="flex flex-wrap gap-2">
                  <Button size="sm" variant="outline" className="gap-1.5" onClick={() => setImportOpen(true)}>
                    <Upload className="size-3.5" />
                    Import
                  </Button>
                  <Button
                    size="sm"
                    variant="outline"
                    className="gap-1.5"
                    disabled={rows.length === 0}
                    onClick={() => {
                      const csv = masterDataRowsToCsv(extraColumns, rows);
                      const blob = new Blob([csv], { type: "text/csv;charset=utf-8" });
                      const url = URL.createObjectURL(blob);
                      const link = document.createElement("a");
                      link.href = url;
                      link.download = `${selectedSet.key}-rows.csv`;
                      link.click();
                      URL.revokeObjectURL(url);
                    }}
                  >
                    <Download className="size-3.5" />
                    Export
                  </Button>
                </div>
              ) : null}
            </div>
            {!selectedSetId ? (
              <p className="text-sm text-muted-foreground">Select a set to manage rows.</p>
            ) : (
              <>
                <div className="space-y-2 rounded-lg border border-border bg-muted/30 p-3">
                  <p className="text-xs text-muted-foreground">
                    Label and code stay on every row. Click a column name to rename it. Add a column and it shows on this set only.
                  </p>
                  {extraColumns.length > 0 ? (
                    <div className="flex flex-wrap gap-1.5">
                      {extraColumns.map((column) => (
                        <span
                          key={column.key}
                          className="inline-flex items-center gap-1 rounded-md border border-border bg-card px-2 py-1 text-xs"
                        >
                          {renamingKey === column.key ? (
                            <Input
                              value={renameDraft}
                              autoFocus
                              aria-label={`Rename ${column.label}`}
                              className="h-6 w-28 px-1 text-xs"
                              onChange={(e) => setRenameDraft(e.target.value)}
                              onBlur={() => commitRename(column.key)}
                              onKeyDown={(e) => {
                                if (e.key === "Enter") {
                                  e.currentTarget.blur();
                                }
                                if (e.key === "Escape") {
                                  renameCancel.current = true;
                                  setRenamingKey(null);
                                }
                              }}
                            />
                          ) : (
                            <button
                              type="button"
                              className="font-medium text-foreground hover:underline"
                              aria-label={`Rename ${column.label}`}
                              onClick={() => {
                                renameCancel.current = false;
                                setRenamingKey(column.key);
                                setRenameDraft(column.label);
                              }}
                            >
                              {column.label}
                            </button>
                          )}
                          <span className="text-muted-foreground">{column.type === "boolean" ? "yes/no" : column.type}</span>
                          <button
                            type="button"
                            className="text-muted-foreground hover:text-foreground"
                            aria-label={`Remove ${column.label}`}
                            disabled={saveColumnsMutation.isPending}
                            onClick={() =>
                              saveColumnsMutation.mutate(extraColumns.filter((item) => item.key !== column.key))
                            }
                          >
                            ×
                          </button>
                        </span>
                      ))}
                    </div>
                  ) : null}
                  <div className="flex flex-wrap gap-2">
                    <Input
                      placeholder="Column name"
                      value={columnLabel}
                      onChange={(e) => setColumnLabel(e.target.value)}
                      className="max-w-[180px]"
                    />
                    <SelectField
                      value={columnType}
                      onChange={(value) => setColumnType(value as EApprovalMasterDataColumn["type"])}
                      allowEmpty={false}
                      options={[
                        { value: "text", label: "Text" },
                        { value: "number", label: "Number" },
                        { value: "boolean", label: "Yes / No" },
                      ]}
                      className="max-w-[140px]"
                    />
                    <Button
                      size="sm"
                      variant="outline"
                      onClick={addColumn}
                      disabled={!columnLabel.trim() || saveColumnsMutation.isPending}
                    >
                      Add column
                    </Button>
                  </div>
                </div>
                <div className="flex flex-wrap gap-2">
                  <Input placeholder="Label" value={rowLabel} onChange={(e) => setRowLabel(e.target.value)} className="max-w-[180px]" />
                  <Input placeholder="Code" value={rowCode} onChange={(e) => setRowCode(e.target.value)} className="max-w-[120px]" />
                  {extraColumns.map((column) =>
                    column.type === "boolean" ? (
                      <SelectField
                        key={column.key}
                        value={extraValues[column.key] ?? ""}
                        onChange={(value) => setExtraValues((current) => ({ ...current, [column.key]: value }))}
                        placeholder={column.label}
                        emptyLabel={column.label}
                        options={[
                          { value: "true", label: `${column.label}: Yes` },
                          { value: "false", label: `${column.label}: No` },
                        ]}
                        className="max-w-[180px]"
                      />
                    ) : (
                      <Input
                        key={column.key}
                        placeholder={column.label}
                        type={column.type === "number" ? "number" : "text"}
                        value={extraValues[column.key] ?? ""}
                        onChange={(e) => setExtraValues((current) => ({ ...current, [column.key]: e.target.value }))}
                        className="max-w-[180px]"
                      />
                    ),
                  )}
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
                  columnVisibilityStorageKey={`toweros.table.columns.e-approval.master-data.rows.${selectedSetId}`}
                />
                {selectedSet ? (
                  <EApprovalMasterDataImportDialog
                    open={importOpen}
                    onOpenChange={setImportOpen}
                    setId={selectedSet.id}
                    setKey={selectedSet.key}
                    setName={selectedSet.name}
                    columns={extraColumns}
                    existing={rows.map((row) => ({ label: row.label, code: row.code }))}
                    onImported={invalidateRows}
                  />
                ) : null}
              </>
            )}
          </div>
        </div>
      </div>
    </PermissionGate>
  );
}
