"use client";

import Link from "next/link";
import { useEffect, useMemo, useState, type ReactNode } from "react";
import {
  DndContext,
  PointerSensor,
  closestCenter,
  useSensor,
  useSensors,
  type DragEndEvent,
} from "@dnd-kit/core";
import {
  SortableContext,
  horizontalListSortingStrategy,
  useSortable,
} from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import {
  ArrowDown,
  ArrowUp,
  ArrowUpDown,
  BarChart3,
  Copy,
  Download,
  Eye,
  FileSpreadsheet,
  GripVertical,
  Pencil,
  Printer,
  Trash2,
} from "lucide-react";

import {
  DynTableColumnsControl,
  useDynTableColumnPrefs,
  type DynTableColumnOption,
} from "@/components/dynamic-entities/dyn-table-columns-control";
import { DynRecordImportDialog } from "@/components/dynamic-entities/dyn-record-import-dialog";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { usePermission } from "@/hooks/use-permission";
import {
  bulkDynRecords,
  createDynRecord,
  deleteDynRecord,
  exportDynRecordsCsv,
  fetchDynEntity,
  fetchDynRecords,
  summarizeDynRecords,
  type DynEntityDetail,
  type DynField,
  type DynRecordListRow,
} from "@/lib/api/modules/dynamic-entities-api";
import {
  listPrintTemplates,
  resolvePrintTemplate,
  templateDisplayName,
} from "@/lib/dynamic-entities/dyn-print-templates";
import { permissions } from "@/lib/rbac/permissions";
import {
  canEntityAction,
  isFieldHiddenForRole,
  notifyDynPermissionDenied,
} from "@/lib/rbac/entity-access";
import { useAuthStore } from "@/stores/auth-store";
import { cn } from "@/lib/utils";
import {
  dynStatusTone,
  formatDynListCell,
} from "@/lib/dynamic-entities/dyn-list-cell-format";
import {
  dynSelectChoiceBadgeClass,
  dynSelectChoiceLabel,
} from "@/lib/dynamic-entities/select-choices";

export function DynamicEntityRecordsPageClient({ slug }: { slug: string }) {
  const canManageRecords = usePermission([permissions.dynamicEntitiesRecordsManage]);
  const canManageFields = usePermission([permissions.dynamicEntitiesFieldsManage]);
  const canManagePrintables = usePermission([permissions.printablesManage]);
  const accessMatrix = useAuthStore((state) => state.user?.accessMatrix);
  const canCreate = canManageRecords && canEntityAction(accessMatrix, slug, "create");
  const canEdit = canManageRecords && canEntityAction(accessMatrix, slug, "edit");
  const canDelete = canManageRecords && canEntityAction(accessMatrix, slug, "delete");
  const canExport = canEntityAction(accessMatrix, slug, "export");
  const canView = canEntityAction(accessMatrix, slug, "view");
  const [entity, setEntity] = useState<DynEntityDetail | null>(null);
  const [rows, setRows] = useState<DynRecordListRow[]>([]);
  const [meta, setMeta] = useState<{ current_page: number; last_page: number; total: number } | null>(
    null,
  );
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [busyId, setBusyId] = useState<string | null>(null);
  const [busyBulk, setBusyBulk] = useState(false);
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [selectAllMatching, setSelectAllMatching] = useState(false);
  const [summaryOpen, setSummaryOpen] = useState(false);
  const [summary, setSummary] = useState<{
    summary: Record<string, number>;
    row_count: number;
  } | null>(null);
  const [bulkStatus, setBulkStatus] = useState("");
  const [editOpen, setEditOpen] = useState(false);
  const [sort, setSort] = useState("-updated_at");
  const [importOpen, setImportOpen] = useState(false);

  async function load(nextPage = page, nextSearch = search, nextSort = sort) {
    setLoading(true);
    try {
      const [detail, list] = await Promise.all([
        fetchDynEntity(slug),
        fetchDynRecords(slug, {
          search: nextSearch || undefined,
          per_page: 50,
          page: nextPage,
          sort: nextSort || undefined,
        }),
      ]);
      setEntity(detail);
      setRows(list.data);
      setMeta({
        current_page: list.meta.current_page,
        last_page: list.meta.last_page,
        total: list.meta.total,
      });
      setError(null);
      setSelected(new Set());
      setSelectAllMatching(false);
    } catch (err) {
      const label = entity?.name ?? slug;
      if (!canView) {
        notifyDynPermissionDenied({ action: "view", entityLabel: label, error: err });
        setError(null);
      } else {
        notifyDynPermissionDenied({ action: "view", entityLabel: label, error: err });
        setError(null);
      }
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void load(1, search, sort);
    setPage(1);
    // eslint-disable-next-line react-hooks/exhaustive-deps -- reload when slug changes
  }, [slug]);

  function toggleSort(column: string) {
    const next = sort === column ? `-${column}` : column;
    setSort(next);
    setPage(1);
    void load(1, search, next);
  }

  function SortIcon({ column }: { column: string }) {
    if (sort === column) return <ArrowUp className="ml-1 inline size-3.5 opacity-80" />;
    if (sort === `-${column}`) return <ArrowDown className="ml-1 inline size-3.5 opacity-80" />;
    return <ArrowUpDown className="ml-1 inline size-3.5 opacity-40" />;
  }

  const fieldByName = useMemo(() => {
    const map = new Map<string, DynField>();
    for (const f of entity?.fields ?? []) {
      if (f.is_system_field || ["actions", "workflows", "print", "id", "status"].includes(f.name)) {
        continue;
      }
      map.set(f.name, f);
    }
    return map;
  }, [entity]);

  const columnOptions = useMemo((): DynTableColumnOption[] => {
    const fields = [...(entity?.fields ?? [])]
      .filter(
        (f) =>
          !f.is_system_field &&
          !["actions", "workflows", "print", "id", "status"].includes(f.name) &&
          !isFieldHiddenForRole(accessMatrix, slug, f.name),
      )
      .sort((a, b) => a.field_order - b.field_order);

    return [
      { id: "status", label: "Status", defaultVisible: true },
      { id: "title", label: "Title", defaultVisible: true },
      ...fields.map((f) => ({
        id: f.name,
        label: f.label,
        defaultVisible: Boolean(f.show_in_table),
      })),
    ];
  }, [entity, accessMatrix, slug]);

  const {
    prefs: columnPrefs,
    visible: visibleColumns,
    savePrefs,
    reorderVisible,
  } = useDynTableColumnPrefs(
    entity ? `toweros.table.columns.dynamic-entities.${slug}` : null,
    columnOptions,
  );

  const columnSensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
  );

  function onColumnDragEnd(event: DragEndEvent) {
    const { active, over } = event;
    if (!over || active.id === over.id) return;
    reorderVisible(String(active.id), String(over.id));
  }

  const totalFields = useMemo(
    () => (entity?.fields ?? []).filter((f) => f.calculate_totals),
    [entity],
  );

  const printTemplates = useMemo(
    () => listPrintTemplates(entity?.print_settings, entity?.name ?? slug),
    [entity, slug],
  );
  const defaultPrint = useMemo(
    () => resolvePrintTemplate(entity?.print_settings, null, entity?.name ?? slug),
    [entity, slug],
  );
  const printLabel = defaultPrint.list_label?.trim() || "Print";
  const listChrome = useMemo(() => {
    const byName = new Map((entity?.fields ?? []).map((f) => [f.name, f]));
    const idField = byName.get("id");
    const actionsField = byName.get("actions");
    const printField = byName.get("print");
    return {
      showId: isListChromeVisible(idField),
      showActions: isListChromeVisible(actionsField),
      showPrint: canManagePrintables && isListChromeVisible(printField),
      idLabel: idField?.label?.trim() || "ID",
      actionsLabel: actionsField?.label?.trim() || "Actions",
      printHeader: printField?.label?.trim() || (printTemplates.length > 1 ? "Print" : printLabel),
    };
  }, [canManagePrintables, entity, printLabel, printTemplates.length]);
  const pageIds = rows.map((r) => r.id);
  const allPageSelected = pageIds.length > 0 && pageIds.every((id) => selected.has(id));
  const selectedCount = selectAllMatching ? (meta?.total ?? selected.size) : selected.size;
  const chromeColCount =
    (listChrome.showId ? 1 : 0) + (listChrome.showActions ? 1 : 0) + (listChrome.showPrint ? 1 : 0);
  const dataColSpan = 1 + chromeColCount + visibleColumns.length;

  function toggleRow(id: string) {
    setSelectAllMatching(false);
    setSelected((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  }

  function togglePage() {
    setSelectAllMatching(false);
    setSelected((prev) => {
      const next = new Set(prev);
      if (allPageSelected) {
        pageIds.forEach((id) => next.delete(id));
      } else {
        pageIds.forEach((id) => next.add(id));
      }
      return next;
    });
  }

  function selectedIds(): string[] {
    return Array.from(selected);
  }

  async function resolveIdsForBulk(): Promise<string[]> {
    if (!selectAllMatching) return selectedIds();
    // Pull up to 500 matching ids via export summary path is not enough — fetch pages.
    const ids: string[] = [];
    let p = 1;
    let last = 1;
    do {
      const list = await fetchDynRecords(slug, {
        search: search || undefined,
        per_page: 100,
        page: p,
      });
      list.data.forEach((r) => ids.push(r.id));
      last = list.meta.last_page;
      p += 1;
    } while (p <= last && ids.length < 500);
    return ids.slice(0, 500);
  }

  async function onSearchSubmit(e: React.FormEvent) {
    e.preventDefault();
    setPage(1);
    await load(1, search);
  }

  async function onClone(row: DynRecordListRow) {
    if (!canCreate) {
      notifyDynPermissionDenied({ action: "clone", entityLabel: entity?.name ?? slug });
      return;
    }
    setBusyId(row.id);
    try {
      await createDynRecord(slug, {
        title: row.title ? `${row.title} (copy)` : undefined,
        status: row.status ?? undefined,
        values: { ...row.columns },
      });
      await load(page, search);
    } catch (err) {
      notifyDynPermissionDenied({ action: "clone", entityLabel: entity?.name ?? slug, error: err });
    } finally {
      setBusyId(null);
    }
  }

  async function onDelete(row: DynRecordListRow) {
    if (!canDelete) {
      notifyDynPermissionDenied({ action: "delete", entityLabel: entity?.name ?? slug });
      return;
    }
    if (!window.confirm(`Delete “${row.title || row.id.slice(0, 8)}”?`)) return;
    setBusyId(row.id);
    try {
      await deleteDynRecord(row.id);
      await load(page, search);
    } catch (err) {
      notifyDynPermissionDenied({ action: "delete", entityLabel: entity?.name ?? slug, error: err });
    } finally {
      setBusyId(null);
    }
  }

  async function onBulkDelete() {
    if (!canDelete) {
      notifyDynPermissionDenied({ action: "delete", entityLabel: entity?.name ?? slug });
      return;
    }
    if (selectedCount === 0) return;
    if (!window.confirm(`Delete ${selectedCount} selected record(s)?`)) return;
    setBusyBulk(true);
    try {
      const ids = await resolveIdsForBulk();
      await bulkDynRecords(slug, { action: "delete", ids });
      await load(page, search);
    } catch (err) {
      notifyDynPermissionDenied({ action: "delete", entityLabel: entity?.name ?? slug, error: err });
    } finally {
      setBusyBulk(false);
    }
  }

  async function onBulkDuplicate() {
    if (!canCreate) {
      notifyDynPermissionDenied({ action: "clone", entityLabel: entity?.name ?? slug });
      return;
    }
    if (selectedCount === 0) return;
    if (selectedCount > 100) {
      setError("Duplicate at most 100 records at a time.");
      return;
    }
    setBusyBulk(true);
    try {
      const ids = await resolveIdsForBulk();
      await bulkDynRecords(slug, { action: "duplicate", ids: ids.slice(0, 100) });
      await load(page, search);
    } catch (err) {
      notifyDynPermissionDenied({ action: "clone", entityLabel: entity?.name ?? slug, error: err });
    } finally {
      setBusyBulk(false);
    }
  }

  async function onBulkEdit() {
    if (!canEdit) {
      notifyDynPermissionDenied({ action: "edit", entityLabel: entity?.name ?? slug });
      return;
    }
    if (!bulkStatus.trim()) {
      setError("Enter a status value to apply.");
      return;
    }
    setBusyBulk(true);
    try {
      const ids = await resolveIdsForBulk();
      await bulkDynRecords(slug, {
        action: "update",
        ids,
        status: bulkStatus.trim(),
        values: { status: bulkStatus.trim() },
      });
      setEditOpen(false);
      setBulkStatus("");
      await load(page, search);
    } catch (err) {
      notifyDynPermissionDenied({ action: "edit", entityLabel: entity?.name ?? slug, error: err });
    } finally {
      setBusyBulk(false);
    }
  }

  async function onExport() {
    if (!canExport) {
      notifyDynPermissionDenied({ action: "export", entityLabel: entity?.name ?? slug });
      return;
    }
    setBusyBulk(true);
    try {
      const blob = await exportDynRecordsCsv(slug, {
        ids: selectAllMatching ? undefined : selectedIds(),
        search: selectAllMatching || selected.size === 0 ? search || undefined : undefined,
      });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = `${slug}-export.csv`;
      a.click();
      URL.revokeObjectURL(url);
    } catch (err) {
      notifyDynPermissionDenied({ action: "export", entityLabel: entity?.name ?? slug, error: err });
    } finally {
      setBusyBulk(false);
    }
  }

  async function onSummary() {
    setBusyBulk(true);
    setSummaryOpen(true);
    try {
      const data = await summarizeDynRecords(slug, {
        ids: selectAllMatching ? undefined : selectedIds(),
        search: selectAllMatching || selected.size === 0 ? search || undefined : undefined,
      });
      setSummary({ summary: data.summary, row_count: data.row_count });
    } catch {
      setError("Unable to build summary.");
      setSummaryOpen(false);
    } finally {
      setBusyBulk(false);
    }
  }

  async function onPrintSelected() {
    const ids = selectAllMatching ? await resolveIdsForBulk() : selectedIds();
    if (ids.length === 0) return;
    if (ids.length > 50) {
      window.alert("Print Selected supports up to 50 records in one document. Narrow your selection.");
      return;
    }
    const url = `/dynamic-entities/print?ids=${encodeURIComponent(ids.join(","))}${
      defaultPrint.id ? `&template=${encodeURIComponent(defaultPrint.id)}` : ""
    }`;
    window.open(url, "_blank", "noopener,noreferrer");
  }

  return (
    <PermissionGate requiredPermissions={[permissions.dynamicEntitiesView]}>
      <div className="space-y-4">
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <div className="flex flex-wrap items-center gap-2">
              <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                {entity?.name ?? slug}
              </h1>
              <span className="inline-flex items-center rounded-full bg-emerald-500/15 px-2 py-0.5 text-[11px] font-medium text-emerald-700 dark:text-emerald-400">
                LIVE
              </span>
            </div>
            <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
              {entity?.description || "Dynamic records driven by Manage Fields."}
            </p>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            {canManageFields ? (
              <Button variant="outline" size="sm" render={<Link href={`/dynamic-entities/fields?entity=${slug}`} />}>
                Manage Fields
              </Button>
            ) : null}
            {canCreate ? (
              <>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  className="gap-1.5"
                  onClick={() => setImportOpen(true)}
                >
                  <FileSpreadsheet className="size-3.5 text-emerald-600" />
                  Import
                </Button>
                <Button size="sm" render={<Link href={`/dynamic-entities/${slug}/new`} />}>
                  + Add New
                </Button>
              </>
            ) : null}
          </div>
        </header>

        <DynRecordImportDialog
          open={importOpen}
          onOpenChange={setImportOpen}
          entitySlug={slug}
          entityName={entity?.name ?? slug}
          onImported={() => {
            void load(1, search, sort);
            setPage(1);
          }}
        />

        <form
          onSubmit={onSearchSubmit}
          className="flex flex-wrap items-center gap-2 rounded-xl border border-border bg-card p-3"
        >
          <Input
            className="h-9 max-w-sm"
            placeholder={`Search ${entity?.name ?? "records"}…`}
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
          <Button type="submit" size="sm" variant="secondary">
            Search
          </Button>
          <DynTableColumnsControl
            options={columnOptions}
            prefs={columnPrefs}
            onSave={savePrefs}
          />
          {meta ? (
            <span className="ml-auto text-xs text-muted-foreground">
              {meta.total.toLocaleString()} record{meta.total === 1 ? "" : "s"}
            </span>
          ) : null}
        </form>

        {selectedCount > 0 || selectAllMatching ? (
          <div className="flex flex-wrap items-center gap-2 rounded-xl border border-sky-500/30 bg-sky-500/10 px-3 py-2 text-sm">
            <span className="font-medium text-sky-900 dark:text-sky-100">
              {selectAllMatching
                ? `${meta?.total.toLocaleString() ?? selectedCount} matching records selected`
                : `${selectedCount} record${selectedCount === 1 ? "" : "s"} on this page selected`}
            </span>
            {!selectAllMatching && meta && meta.total > rows.length ? (
              <button
                type="button"
                className="text-sky-800 underline-offset-2 hover:underline dark:text-sky-200"
                onClick={() => setSelectAllMatching(true)}
              >
                Select all {meta.total.toLocaleString()} matching records
              </button>
            ) : null}
            <button
              type="button"
              className="text-muted-foreground underline-offset-2 hover:underline"
              onClick={() => {
                setSelected(new Set());
                setSelectAllMatching(false);
              }}
            >
              Clear
            </button>

            <div className="ml-auto flex flex-wrap items-center gap-1.5">
              <Button
                type="button"
                size="sm"
                variant="outline"
                disabled={busyBulk}
                onClick={() => void onSummary()}
              >
                <BarChart3 className="mr-1.5 h-3.5 w-3.5" />
                Summary Report
              </Button>
              {canManagePrintables ? (
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  disabled={busyBulk || selectedIds().length === 0}
                  onClick={onPrintSelected}
                >
                  <Printer className="mr-1.5 h-3.5 w-3.5" />
                  Print Selected
                </Button>
              ) : null}
              {canExport ? (
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  disabled={busyBulk}
                  onClick={() => void onExport()}
                >
                  <Download className="mr-1.5 h-3.5 w-3.5" />
                  Export
                </Button>
              ) : null}
              {canEdit ? (
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  disabled={busyBulk}
                  onClick={() => setEditOpen((v) => !v)}
                >
                  <Pencil className="mr-1.5 h-3.5 w-3.5" />
                  Edit
                </Button>
              ) : null}
              {canCreate ? (
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  disabled={busyBulk}
                  onClick={() => void onBulkDuplicate()}
                >
                  <Copy className="mr-1.5 h-3.5 w-3.5" />
                  Duplicate
                </Button>
              ) : null}
              {canDelete ? (
                <Button
                  type="button"
                  size="sm"
                  variant="destructive"
                  disabled={busyBulk}
                  onClick={() => void onBulkDelete()}
                >
                  <Trash2 className="mr-1.5 h-3.5 w-3.5" />
                  Delete
                </Button>
              ) : null}
            </div>
          </div>
        ) : null}

        {editOpen && canEdit ? (
          <div className="flex flex-wrap items-end gap-2 rounded-xl border border-border bg-card p-3">
            <div className="space-y-1">
              <label className="text-xs font-medium text-muted-foreground">Bulk status</label>
              <Input
                className="h-9 w-56"
                placeholder="e.g. Active"
                value={bulkStatus}
                onChange={(e) => setBulkStatus(e.target.value)}
              />
            </div>
            <Button type="button" size="sm" disabled={busyBulk || !bulkStatus.trim()} onClick={() => void onBulkEdit()}>
              Apply to selected
            </Button>
            <Button type="button" size="sm" variant="ghost" onClick={() => setEditOpen(false)}>
              Cancel
            </Button>
          </div>
        ) : null}

        {summaryOpen && summary ? (
          <div className="rounded-xl border border-border bg-card p-4">
            <div className="flex items-start justify-between gap-2">
              <div>
                <h2 className="text-base font-medium">Summary Report</h2>
                <p className="text-sm text-muted-foreground">
                  {summary.row_count.toLocaleString()} record{summary.row_count === 1 ? "" : "s"}
                </p>
              </div>
              <Button type="button" size="sm" variant="ghost" onClick={() => setSummaryOpen(false)}>
                Close
              </Button>
            </div>
            <dl className="mt-3 grid gap-2 sm:grid-cols-2 md:grid-cols-3">
              <div className="rounded-lg border border-border px-3 py-2">
                <dt className="text-xs text-muted-foreground">Count</dt>
                <dd className="text-lg font-medium tabular-nums">{summary.summary.count ?? summary.row_count}</dd>
              </div>
              {totalFields.map((f) => (
                <div key={f.id} className="rounded-lg border border-border px-3 py-2">
                  <dt className="text-xs text-muted-foreground">{f.label}</dt>
                  <dd className="text-lg font-medium tabular-nums">
                    {Number(summary.summary[f.name] ?? 0).toLocaleString(undefined, {
                      maximumFractionDigits: 2,
                    })}
                  </dd>
                </div>
              ))}
              {totalFields.length === 0 ? (
                <p className="text-sm text-muted-foreground sm:col-span-2">
                  No fields marked Calculate totals. Enable that on Manage Fields to sum numeric columns here.
                </p>
              ) : null}
            </dl>
          </div>
        ) : null}

        {loading ? <p className="text-sm text-muted-foreground">Loading…</p> : null}
        {error ? <p className="text-sm text-destructive">{error}</p> : null}

        {!loading && !error ? (
          <div className="overflow-x-auto rounded-xl border border-border bg-card">
            <DndContext sensors={columnSensors} collisionDetection={closestCenter} onDragEnd={onColumnDragEnd}>
              <table className="min-w-full text-[13px]">
                <thead className="sticky top-0 bg-muted/60 text-left text-xs font-medium text-muted-foreground">
                  <tr>
                    <th className="px-3 py-2 w-10">
                      <Checkbox
                        checked={allPageSelected}
                        onCheckedChange={() => togglePage()}
                        aria-label="Select all on page"
                      />
                    </th>
                    {listChrome.showId ? <th className="px-3 py-2">{listChrome.idLabel}</th> : null}
                    {listChrome.showActions ? (
                      <th className="px-3 py-2">{listChrome.actionsLabel}</th>
                    ) : null}
                    {listChrome.showPrint ? (
                      <th className="px-3 py-2">{listChrome.printHeader}</th>
                    ) : null}
                    <SortableContext
                      items={visibleColumns.map((c) => c.id)}
                      strategy={horizontalListSortingStrategy}
                    >
                      {visibleColumns.map((col) => (
                        <SortableColumnHeader
                          key={col.id}
                          id={col.id}
                          label={col.label}
                          sortActive={sort === col.id || sort === `-${col.id}`}
                          sortIcon={<SortIcon column={col.id} />}
                          onSort={() => toggleSort(col.id)}
                        />
                      ))}
                    </SortableContext>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((row, idx) => (
                    <tr
                      key={row.id}
                      className={cn(
                        "border-t border-border/70",
                        idx % 2 === 1 && "bg-muted/20",
                        (selected.has(row.id) || selectAllMatching) && "bg-sky-500/5",
                      )}
                    >
                      <td className="px-3 py-2">
                        <Checkbox
                          checked={selectAllMatching || selected.has(row.id)}
                          onCheckedChange={() => toggleRow(row.id)}
                          aria-label={`Select ${row.title || row.id}`}
                        />
                      </td>
                      {listChrome.showId ? (
                        <td className="px-3 py-2 font-mono text-xs text-muted-foreground">
                          {row.source_external_id ?? row.id.slice(0, 8)}
                        </td>
                      ) : null}
                      {listChrome.showActions ? (
                        <td className="px-3 py-2">
                          <div className="flex items-center gap-1">
                            <Link
                              href={`/dynamic-entities/records/${row.id}`}
                              className="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                              title="View"
                            >
                              <Eye className="h-3.5 w-3.5" />
                            </Link>
                            {canEdit ? (
                              <Link
                                href={`/dynamic-entities/records/${row.id}?edit=1`}
                                className="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                                title="Edit"
                              >
                                <Pencil className="h-3.5 w-3.5" />
                              </Link>
                            ) : null}
                            {canCreate ? (
                              <button
                                type="button"
                                className="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground disabled:opacity-50"
                                title="Clone"
                                disabled={busyId === row.id}
                                onClick={() => void onClone(row)}
                              >
                                <Copy className="h-3.5 w-3.5" />
                              </button>
                            ) : null}
                            {canDelete ? (
                              <button
                                type="button"
                                className="rounded p-1 text-muted-foreground hover:bg-muted hover:text-destructive disabled:opacity-50"
                                title="Delete"
                                disabled={busyId === row.id}
                                onClick={() => void onDelete(row)}
                              >
                                <Trash2 className="h-3.5 w-3.5" />
                              </button>
                            ) : null}
                          </div>
                        </td>
                      ) : null}
                      {listChrome.showPrint ? (
                        <td className="px-3 py-2 align-middle">
                          <div className="flex min-w-[9rem] flex-col items-center gap-1">
                            {printTemplates.map((t) => {
                              const label = templateDisplayName(t);
                              return (
                                <Link
                                  key={t.id}
                                  href={`/dynamic-entities/records/${row.id}/print?template=${encodeURIComponent(t.id)}`}
                                  target="_blank"
                                  rel="noreferrer"
                                  className="inline-flex max-w-[11rem] items-center justify-center gap-1 rounded-full bg-sky-700 px-2.5 py-1 text-center text-[10px] font-medium leading-tight text-white hover:bg-sky-800"
                                  title={label}
                                >
                                  <Printer className="size-2.5 shrink-0" />
                                  <span className="truncate">{label}</span>
                                </Link>
                              );
                            })}
                          </div>
                        </td>
                      ) : null}
                      {visibleColumns.map((col) => (
                        <td key={col.id} className="px-3 py-2 text-muted-foreground whitespace-nowrap">
                          {renderDataCell(col.id, row, fieldByName.get(col.id))}
                        </td>
                      ))}
                    </tr>
                  ))}
                  {rows.length === 0 ? (
                    <tr>
                      <td className="px-3 py-8 text-center text-muted-foreground" colSpan={dataColSpan}>
                        No records found. Import Phase 1 data or add a new record.
                      </td>
                    </tr>
                  ) : null}
                </tbody>
              </table>
            </DndContext>
          </div>
        ) : null}

        {meta && meta.last_page > 1 ? (
          <div className="flex items-center justify-between text-sm text-muted-foreground">
            <div className="flex gap-1">
              <Button
                size="sm"
                variant="outline"
                disabled={page <= 1}
                onClick={() => {
                  const next = page - 1;
                  setPage(next);
                  void load(next, search, sort);
                }}
              >
                Prev
              </Button>
              <Button
                size="sm"
                variant="outline"
                disabled={page >= meta.last_page}
                onClick={() => {
                  const next = page + 1;
                  setPage(next);
                  void load(next, search, sort);
                }}
              >
                Next
              </Button>
            </div>
            <span>
              Page {meta.current_page} of {meta.last_page} ({meta.total.toLocaleString()} records)
            </span>
          </div>
        ) : null}
      </div>
    </PermissionGate>
  );
}

function SortableColumnHeader({
  id,
  label,
  sortIcon,
  onSort,
}: {
  id: string;
  label: string;
  sortActive?: boolean;
  sortIcon: ReactNode;
  onSort: () => void;
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id,
  });

  return (
    <th
      ref={setNodeRef}
      style={{
        transform: CSS.Translate.toString(transform),
        transition,
        opacity: isDragging ? 0.55 : undefined,
        zIndex: isDragging ? 20 : undefined,
      }}
      className={cn(
        "group px-2 py-2 whitespace-nowrap",
        isDragging && "bg-sky-500/10 shadow-sm ring-1 ring-sky-500/30",
      )}
    >
      <div
        className="inline-flex cursor-grab items-center gap-0.5 rounded-md px-1 py-0.5 hover:bg-muted/80 active:cursor-grabbing"
        title="Drag left or right to reorder · click label to sort"
        {...attributes}
        {...listeners}
      >
        <GripVertical className="size-3.5 shrink-0 text-muted-foreground opacity-40 group-hover:opacity-100" />
        <button
          type="button"
          className="inline-flex cursor-pointer items-center font-medium hover:text-foreground"
          onClick={(e) => {
            e.stopPropagation();
            onSort();
          }}
          onPointerDown={(e) => e.stopPropagation()}
        >
          {label}
          {sortIcon}
        </button>
      </div>
    </th>
  );
}

function renderDataCell(columnId: string, row: DynRecordListRow, field?: DynField) {
  if (columnId === "status") {
    return <StatusPill value={row.status} field={field} />;
  }
  if (columnId === "title") {
    return (
      <Link
        className="font-medium text-foreground underline-offset-4 hover:underline"
        href={`/dynamic-entities/records/${row.id}`}
      >
        {row.title || "Untitled"}
      </Link>
    );
  }
  const value = row.columns[columnId];
  if (
    field?.type === "select" ||
    columnId.includes("status") ||
    columnId.includes("type")
  ) {
    return <StatusPill value={value} field={field} />;
  }
  return formatDynListCell(value, field);
}

/** ATC meta import historically forced system show_in_table=false; keep chrome visible until managed. */
function isListChromeVisible(field: DynField | undefined): boolean {
  if (!field) return true;
  const opts = field.options;
  const managed =
    opts !== null &&
    typeof opts === "object" &&
    !Array.isArray(opts) &&
    (opts as { list_visibility_managed?: unknown }).list_visibility_managed === true;
  if (managed) return Boolean(field.show_in_table);
  return true;
}

function StatusPill({ value, field }: { value: unknown; field?: DynField }) {
  if (value === null || value === undefined || value === "") {
    return <span className="text-muted-foreground">—</span>;
  }
  const text = String(value);
  const label = field ? dynSelectChoiceLabel(field.options, text) : text;
  const badgeClass = field ? dynSelectChoiceBadgeClass(field.options, text) : null;
  return (
    <span
      className={`inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium ${
        badgeClass ?? dynStatusTone(text)
      }`}
    >
      {label}
    </span>
  );
}

function formatCell(value: unknown): string {
  return formatDynListCell(value);
}
