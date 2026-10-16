"use client";

import { useQuery, useQueryClient } from "@tanstack/react-query";
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
import { ArrowDown, ArrowUp, ArrowUpDown, BarChart3, Download, Eye, EyeOff, FileSpreadsheet, GripVertical, Pencil, Pin, Printer, Trash2 } from "lucide-react";
import {
  createColumnHelper,
  getCoreRowModel,
  useReactTable,
} from "@tanstack/react-table";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useEffect, useLayoutEffect, useMemo, useRef, useState } from "react";
import { AssetStatusBadge } from "@/components/assets/asset-status-badge";
import { WorkspacePageHeader } from "@/components/layout/workspace-page-header";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { DynRecordImportDialog } from "@/components/dynamic-entities/dyn-record-import-dialog";
import { DynSchemaSheets, type DynSchemaMode } from "@/components/dynamic-entities/dyn-schema-sheets";
import {
  RoleFieldAccessMenuItems,
  RoleFieldAccessProvider,
  RoleRowActionsMenuItems,
  useRoleFieldAccessContext,
} from "@/components/dynamic-entities/dyn-role-field-access";
import {
  DynTableColumnsControl,
  useDynTableColumnPrefs,
  type DynTableColumnOption,
  type DynTableColumnPrefs,
} from "@/components/dynamic-entities/dyn-table-columns-control";
import { RecordActionHistoryButton } from "@/components/dynamic-entities/record-action-history";
import { Button, buttonVariants } from "@/components/ui/button";
import Pagination1 from "@/components/ui/pagination-01";
import { statusToneClasses } from "@/components/ui/status-tone";
import {
  ContextMenu,
  ContextMenuContent,
  ContextMenuItem,
  ContextMenuSeparator,
  ContextMenuTrigger,
} from "@/components/ui/context-menu";
import { DataTable } from "@/components/ui/data-table";
import { RowActionsMenu } from "@/components/ui/row-actions-menu";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { FilterSelect } from "@/components/forms/filter-select";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  analyzeAssetImport,
  deleteAssetField,
  downloadAssetExport,
  downloadAssetImportTemplate,
  fetchAssetDashboard,
  fetchAssetSchema,
  fetchAssets,
  runAssetImport,
  searchAssetPeople,
  transferAssets,
} from "@/lib/api/modules/assets-api";
import { fetchDynEntity, type DynField } from "@/lib/api/modules/dynamic-entities-api";
import { listPrintTemplates, templateDisplayName } from "@/lib/dynamic-entities/dyn-print-templates";
import { getErrorMessage } from "@/lib/api/error";
import { canEntityAction, fieldAccessLevel, isFieldHiddenOnSurface } from "@/lib/rbac/entity-access";
import { fieldVisibleOnSurface } from "@/lib/rbac/field-surface-access";
import type { RoleAccessMatrix } from "@/lib/api/modules/admin-roles-api";
import { notify } from "@/lib/ui/notify";
import { adminPageShellClass } from "@/lib/ui/page-shell";
import { cn } from "@/lib/utils";
import type { AssetPerson, AssetRecord, AssetSchemaField } from "@/modules/assets/types";
import { useAuthStore } from "@/stores/auth-store";

const columnHelper = createColumnHelper<AssetRecord>();

const emptyAssets: AssetRecord[] = [];

function assetFieldText(record: AssetRecord, name: string): string {
  if (name === "assignee") {
    return record.assignee?.name ?? "—";
  }
  const direct = record[name as keyof AssetRecord];
  if (direct != null && typeof direct !== "object") {
    return String(direct);
  }
  const custom = record.custom_values?.[name];
  if (custom == null || custom === "") {
    return "—";
  }
  return String(custom);
}

function schemaColumnOptions(fields: AssetSchemaField[]): DynTableColumnOption[] {
  const fromSchema = [...fields]
    .sort((left, right) => left.field_order - right.field_order)
    .map((field) => ({
      id: field.name,
      label: field.label,
      defaultVisible: field.show_in_table,
    }));
  return [...fromSchema, { id: "assignee", label: "Assignee", defaultVisible: true }];
}

function AssetListControls({
  accessMatrix,
  canManage,
  canImport,
  canExport,
  columnOptions,
  columnPrefs,
  onSaveColumns,
  onImport,
  onExport,
}: {
  accessMatrix: RoleAccessMatrix | null | undefined;
  canManage: boolean;
  canImport: boolean;
  canExport: boolean;
  columnOptions: DynTableColumnOption[];
  columnPrefs: DynTableColumnPrefs;
  onSaveColumns: (prefs: DynTableColumnPrefs) => void;
  onImport: () => void;
  onExport: () => void;
}) {
  const preview = useRoleFieldAccessContext();
  const previewing = Boolean(preview?.selected);
  const showCreate = previewing
    ? Boolean(preview?.entityAllows("create"))
    : canManage && canEntityAction(accessMatrix, "it_asset", "create");
  const showImport = previewing
    ? Boolean(preview?.entityAllows("import"))
    : canImport && canEntityAction(accessMatrix, "it_asset", "import");
  const showColumns = previewing
    ? Boolean(preview?.entityAllows("columns"))
    : canEntityAction(accessMatrix, "it_asset", "columns");

  return (
    <>
      {showCreate ? (
        <Button type="button" render={<Link href="/assets/new" />}>
          Register asset
        </Button>
      ) : null}
      {canExport && (!previewing || Boolean(preview?.entityAllows("export"))) ? (
        <Button type="button" variant="outline" onClick={onExport}>
          Export
        </Button>
      ) : null}
      {showImport ? (
        <Button type="button" variant="outline" className="gap-1.5" onClick={onImport}>
          <FileSpreadsheet className={cn("size-3.5", statusToneClasses.success.icon)} />
          Import
        </Button>
      ) : null}
      <Button type="button" variant="outline" render={<Link href="/assets/scan" />}>
        Scan
      </Button>
      {showColumns ? (
        <DynTableColumnsControl options={columnOptions} prefs={columnPrefs} onSave={onSaveColumns} />
      ) : null}
    </>
  );
}

export function AssetsPageClient() {
  const router = useRouter();
  const params = useSearchParams();
  const queryClient = useQueryClient();
  const user = useAuthStore((state) => state.user);
  const activeTenantId = useAuthStore((state) => state.activeTenantId);
  const permissions =
    user?.tenantAccesses.find((item) => item.tenantId === activeTenantId)?.permissions ??
    user?.permissions ??
    [];
  const can = (key: string) => permissions.includes(key);
  const accessMatrix = user?.accessMatrix;
  const qParam = params.get("q") ?? "";
  const status = params.get("status") ?? "";
  const assetType = params.get("asset_type") ?? "";
  const lifecycle = params.get("lifecycle") ?? "";
  const attention = params.get("attention") ?? "";
  const repair = params.get("repair") ?? "";
  const page = Number(params.get("page") ?? "1");
  const sort = params.get("sort") ?? "";
  const [search, setSearch] = useState(qParam);
  const [importOpen, setImportOpen] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const [schemaMode, setSchemaMode] = useState<DynSchemaMode>({ kind: "closed" });
  const [deleteField, setDeleteField] = useState<DynField | null>(null);
  const [pinned, setPinned] = useState<string[]>([]);
  const [pinOrigins, setPinOrigins] = useState<Record<string, number>>({});
  const [selected, setSelected] = useState<string[]>([]);
  const [selectAllMatching, setSelectAllMatching] = useState(false);
  const [summaryOpen, setSummaryOpen] = useState(false);
  const [transferOpen, setTransferOpen] = useState(false);
  const [employeeQuery, setEmployeeQuery] = useState("");
  const [employee, setEmployee] = useState<AssetPerson | null>(null);
  const [confirmText, setConfirmText] = useState("");
  const [formError, setFormError] = useState<string | null>(null);

  useEffect(() => {
    if (search === qParam) {
      return;
    }
    const timer = window.setTimeout(() => {
      const next = new URLSearchParams(params.toString());
      if (search) next.set("q", search);
      else next.delete("q");
      next.delete("page");
      router.replace(`/assets?${next.toString()}`);
    }, 250);
    return () => window.clearTimeout(timer);
  }, [search, qParam, params, router]);

  const listFilters = {
    q: qParam || undefined,
    status: status || undefined,
    asset_type: assetType || undefined,
    lifecycle: lifecycle || undefined,
    attention: attention || undefined,
    repair: repair || undefined,
  };
  const list = useQuery({
    queryKey: ["assets", qParam, status, assetType, lifecycle, attention, repair, page, sort],
    queryFn: () =>
      fetchAssets({
        ...listFilters,
        page,
        sort: sort || undefined,
      }),
  });
  const dashboard = useQuery({
    queryKey: ["assets-dashboard"],
    queryFn: fetchAssetDashboard,
    enabled: can("assets:view"),
  });
  const schema = useQuery({
    queryKey: ["asset-schema"],
    queryFn: fetchAssetSchema,
  });
  const columnOptions = useMemo(() => {
    const chrome: DynTableColumnOption[] = [
      { id: "actions", label: "Actions", defaultVisible: true },
      { id: "print", label: "Print", defaultVisible: true },
    ];
    const columns = [...chrome, ...schemaColumnOptions(schema.data?.fields ?? [])];
    if (permissions.includes("assets:fields:manage") || permissions.includes("role:manage")) return columns;
    return columns.filter((column) => !isFieldHiddenOnSurface(accessMatrix, "it_asset", column.id, "table"));
  }, [schema.data?.fields, accessMatrix, permissions]);
  const columnPrefs = useDynTableColumnPrefs("toweros.assets.table", columnOptions, ["actions", "print"]);
  const visibleColumns = useMemo(() => {
    const pinSet = new Set(pinned);
    return [
      ...columnPrefs.visible.filter((column) => pinSet.has(column.id)),
      ...columnPrefs.visible.filter((column) => !pinSet.has(column.id)),
    ];
  }, [columnPrefs.visible, pinned]);
  const [previewMatrix, setPreviewMatrix] = useState<RoleAccessMatrix | null>(null);
  const roleMatrix = previewMatrix ?? accessMatrix;
  const previewingRole = previewMatrix !== null;
  const tableColumns = useMemo(
    () =>
      visibleColumns.filter((column) =>
        fieldVisibleOnSurface(fieldAccessLevel(roleMatrix, "it_asset", column.id), "table"),
      ),
    [roleMatrix, visibleColumns],
  );
  const allowView = canEntityAction(roleMatrix, "it_asset", "view");
  const allowEdit = previewingRole
    ? canEntityAction(roleMatrix, "it_asset", "edit")
    : can("assets:manage") && canEntityAction(roleMatrix, "it_asset", "edit");
  const tableWrapRef = useRef<HTMLDivElement>(null);
  const [pinLeft, setPinLeft] = useState<Record<string, number>>({});
  const pinnedVisible = pinned.filter((id) => tableColumns.some((column) => column.id === id));
  const pinEdgeId = pinnedVisible[pinnedVisible.length - 1] ?? (pinnedVisible.length > 0 ? "select" : null);
  const people = useQuery({
    queryKey: ["asset-people", employeeQuery],
    queryFn: () => searchAssetPeople(employeeQuery),
    enabled: transferOpen && employeeQuery.trim().length > 1,
  });

  const registerEntity = useQuery({
    queryKey: ["it-asset-entity"],
    queryFn: async () => {
      await fetchAssetSchema();
      return fetchDynEntity("it_asset");
    },
    staleTime: 0,
    refetchOnMount: "always",
  });
  const printTemplates = useMemo(() => {
    if (!registerEntity.data) return [];
    return listPrintTemplates(registerEntity.data.print_settings, registerEntity.data.name);
  }, [registerEntity.data]);

  useEffect(() => {
    const raw = window.localStorage.getItem("toweros.assets.table.pinned");
    if (!raw) return;
    try {
      const parsed = JSON.parse(raw) as unknown;
      if (Array.isArray(parsed)) {
        setPinned(parsed.filter((id): id is string => typeof id === "string"));
        return;
      }
      if (parsed && typeof parsed === "object" && Array.isArray((parsed as { ids?: unknown }).ids)) {
        const store = parsed as { ids: unknown[]; origins?: unknown };
        setPinned(store.ids.filter((id): id is string => typeof id === "string"));
        if (store.origins && typeof store.origins === "object") {
          const origins: Record<string, number> = {};
          for (const [key, value] of Object.entries(store.origins)) {
            if (typeof value === "number") origins[key] = value;
          }
          setPinOrigins(origins);
        }
      }
    } catch {
      setPinned([]);
    }
  }, []);

  function writePins(ids: string[], origins: Record<string, number>) {
    window.localStorage.setItem("toweros.assets.table.pinned", JSON.stringify({ ids, origins }));
  }

  function togglePin(id: string) {
    const order = [...columnPrefs.prefs.order];
    if (pinned.includes(id)) {
      const nextIds = pinned.filter((item) => item !== id);
      const without = order.filter((item) => item !== id);
      const origin = pinOrigins[id] ?? without.length;
      const at = Math.min(Math.max(origin, nextIds.length), without.length);
      without.splice(at, 0, id);
      const nextOrigins = { ...pinOrigins };
      delete nextOrigins[id];
      setPinned(nextIds);
      setPinOrigins(nextOrigins);
      writePins(nextIds, nextOrigins);
      columnPrefs.savePrefs({ ...columnPrefs.prefs, order: without });
      return;
    }
    const origin = Math.max(order.indexOf(id), 0);
    const nextOrigins = { ...pinOrigins, [id]: origin };
    const nextIds = [id, ...pinned.filter((item) => item !== id)];
    const pinSet = new Set(nextIds);
    const pinOrder = nextIds.filter((item) => order.includes(item));
    const rest = order.filter((item) => !pinSet.has(item));
    setPinned(nextIds);
    setPinOrigins(nextOrigins);
    writePins(nextIds, nextOrigins);
    columnPrefs.savePrefs({
      ...columnPrefs.prefs,
      order: [...pinOrder, ...rest],
      hidden: columnPrefs.prefs.hidden.filter((item) => item !== id),
    });
  }

  function hideColumn(id: string) {
    if (columnPrefs.prefs.hidden.includes(id)) return;
    columnPrefs.savePrefs({ ...columnPrefs.prefs, hidden: [...columnPrefs.prefs.hidden, id] });
  }

  const columns = useMemo(
    () => [
      columnHelper.display({
        id: "select",
        meta: {
          pinId: "select",
          stickyLeft: pinnedVisible.length > 0 ? pinLeft.select : undefined,
          pinEdge: pinEdgeId === "select",
        },
        header: () => {
          const pageIds = (list.data?.rows ?? []).map((item) => item.id);
          const all = pageIds.length > 0 && pageIds.every((id) => selected.includes(id));
          return (
            <input
              type="checkbox"
              aria-label="Select all on this page"
              checked={all}
              onChange={() => {
                setSelectAllMatching(false);
                setSummaryOpen(false);
                setSelected((current) =>
                  all
                    ? current.filter((id) => !pageIds.includes(id))
                    : [...new Set([...current, ...pageIds])],
                );
              }}
            />
          );
        },
        cell: ({ row }) => (
          <input
            type="checkbox"
            aria-label={`Select ${row.original.asset_tag}`}
            checked={selected.includes(row.original.id)}
            onChange={(event) => {
              setSelectAllMatching(false);
              setSummaryOpen(false);
              setSelected((current) =>
                event.target.checked
                  ? [...current, row.original.id]
                  : current.filter((id) => id !== row.original.id),
              );
            }}
          />
        ),
      }),
      ...tableColumns.map((column) => {
        if (column.id === "actions") {
          return columnHelper.display({
            id: "actions",
            meta: {
              pinId: "actions",
              stickyLeft: pinnedVisible.includes("actions") ? pinLeft.actions : undefined,
              pinEdge: pinEdgeId === "actions",
            },
            header: () => (
              <AssetColumnHeader
                id="actions"
                label="Actions"
                sort={sort}
                pinned={pinned.includes("actions")}
                sortable={false}
                canManage={false}
                field={null}
                columns={columnOptions}
                accessMenu="actions"
                onSort={() => undefined}
                onPin={() => togglePin("actions")}
                onHide={() => hideColumn("actions")}
                onEdit={() => undefined}
                onDelete={() => undefined}
              />
            ),
            cell: ({ row }) => (
              <ContextMenu>
                <ContextMenuTrigger className="inline-flex" render={<div />}>
                  <RowActionsMenu
                    label={`Actions for ${row.original.asset_tag}`}
                    items={[
                      {
                        key: "view",
                        label: "View",
                        href: `/assets/${row.original.asset_tag}`,
                        icon: <Eye className="size-4" />,
                        hidden: !allowView,
                      },
                      {
                        key: "edit",
                        label: "Edit",
                        href: `/assets/${row.original.asset_tag}?edit=1`,
                        icon: <Pencil className="size-4" />,
                        hidden: !allowEdit,
                      },
                    ]}
                  />
                </ContextMenuTrigger>
                <ContextMenuContent className="min-w-[14rem]">
                  <RoleRowActionsMenuItems columns={columnOptions} />
                </ContextMenuContent>
              </ContextMenu>
            ),
          });
        }
        if (column.id === "print") {
          return columnHelper.display({
            id: "print",
            meta: {
              pinId: "print",
              stickyLeft: pinnedVisible.includes("print") ? pinLeft.print : undefined,
              pinEdge: pinEdgeId === "print",
            },
            header: () => (
              <AssetColumnHeader
                id="print"
                label="Print"
                sort={sort}
                pinned={pinned.includes("print")}
                sortable={false}
                canManage={false}
                field={null}
                columns={columnOptions}
                onSort={() => undefined}
                onPin={() => togglePin("print")}
                onHide={() => hideColumn("print")}
                onEdit={() => undefined}
                onDelete={() => undefined}
              />
            ),
            cell: ({ row }) => (
              <ContextMenu>
                <ContextMenuTrigger className="block" render={<div />}>
                  <div className="flex min-w-[9rem] flex-col items-center gap-1">
                    {(printTemplates.length > 0
                      ? printTemplates.map((template) => ({
                          id: template.id,
                          label: templateDisplayName(template),
                        }))
                      : [{ id: "", label: "Accountability Note" }]
                    ).map((template) => (
                      <Link
                        key={template.id || "default"}
                        href={
                          template.id
                            ? `/assets/${row.original.asset_tag}/print?template=${encodeURIComponent(template.id)}`
                            : `/assets/${row.original.asset_tag}/print`
                        }
                        target="_blank"
                        className={cn(
                          buttonVariants({ variant: "outline", size: "sm" }),
                          "max-w-[11rem] text-[10px] leading-tight",
                        )}
                        title={template.label}
                      >
                        <Printer className="size-2.5 shrink-0" />
                        <span className="truncate">{template.label}</span>
                      </Link>
                    ))}
                  </div>
                </ContextMenuTrigger>
                <ContextMenuContent className="min-w-[14rem]">
                  <RoleFieldAccessMenuItems fieldName="print" fieldLabel="Print" columns={columnOptions} />
                </ContextMenuContent>
              </ContextMenu>
            ),
          });
        }
        return columnHelper.display({
          id: column.id,
          meta: {
            pinId: column.id,
            stickyLeft: pinnedVisible.includes(column.id) ? pinLeft[column.id] : undefined,
            pinEdge: pinEdgeId === column.id,
          },
          header: () => {
            const field = registerEntity.data?.fields.find((item) => item.name === column.id) ?? null;
            return (
              <AssetColumnHeader
                id={column.id}
                label={column.label}
                sort={sort}
                pinned={pinned.includes(column.id)}
                canManage={can("assets:fields:manage")}
                field={field}
                columns={columnOptions}
                onSort={() => {
                  const next = sort === column.id ? `-${column.id}` : column.id;
                  const query = new URLSearchParams(params.toString());
                  query.set("sort", next);
                  query.delete("page");
                  router.replace(`/assets?${query.toString()}`);
                }}
                onPin={() => togglePin(column.id)}
                onHide={() => hideColumn(column.id)}
                onEdit={() => {
                  if (field) setSchemaMode({ kind: "edit-field", field });
                }}
                onDelete={() => {
                  if (field && !field.is_system_field) setDeleteField(field);
                }}
              />
            );
          },
          cell: ({ row }) => {
            const text = assetFieldText(row.original, column.id);
            const body =
              column.id === "asset_tag" ? (
                <Link className="font-mono text-sm tabular-nums underline-offset-2 hover:underline" href={`/assets/${row.original.asset_tag}`}>
                  {text}
                </Link>
              ) : column.id === "status" ? (
                <AssetStatusBadge status={text} />
              ) : column.id === "serial_number" ? (
                <span className="font-mono text-sm tabular-nums">{text}</span>
              ) : (
                text
              );
            const editable = can("assets:manage");
            return (
              <ContextMenu>
                <ContextMenuTrigger
                  className="block"
                  title={editable ? "Double-click to edit · right-click for role access" : "Right-click for role access"}
                  render={<div />}
                  onDoubleClick={() =>
                    router.push(
                      editable
                        ? `/assets/${row.original.asset_tag}?edit=1`
                        : `/assets/${row.original.asset_tag}`,
                    )
                  }
                >
                  {body}
                </ContextMenuTrigger>
                <ContextMenuContent className="min-w-[14rem]">
                  <RoleFieldAccessMenuItems fieldName={column.id} fieldLabel={column.label} columns={columnOptions} />
                </ContextMenuContent>
              </ContextMenu>
            );
          },
        });
      }),
    ],
    [allowEdit, allowView, can, columnOptions, columnPrefs, hideColumn, list.data?.rows, params, pinEdgeId, pinLeft, pinned, pinnedVisible, printTemplates, registerEntity.data?.fields, router, selected, sort, tableColumns, togglePin],
  );

  const pinMeasureKey = pinnedVisible.join("\0");
  useLayoutEffect(() => {
    const root = tableWrapRef.current;
    if (!root || pinnedVisible.length === 0) {
      setPinLeft((current) => (Object.keys(current).length === 0 ? current : {}));
      return;
    }
    const ids = ["select", ...pinnedVisible];
    let left = 0;
    const next: Record<string, number> = {};
    for (const id of ids) {
      const cell = root.querySelector<HTMLElement>(`thead [data-pin-id="${globalThis.CSS.escape(id)}"]`);
      if (!cell) continue;
      next[id] = left;
      left += Math.round(cell.getBoundingClientRect().width);
    }
    setPinLeft((current) => {
      const keys = Object.keys(next);
      if (keys.length === Object.keys(current).length && keys.every((key) => current[key] === next[key])) {
        return current;
      }
      return next;
    });
  }, [pinMeasureKey, pinnedVisible, list.data?.rows]);

  const rows = list.data?.rows ?? emptyAssets;
  const totalMatching = list.data?.meta.total ?? rows.length;
  const selectedOnPage = rows.filter((row) => selected.includes(row.id));
  const selectionCount = selectAllMatching ? totalMatching : selected.length;
  const summaryRows = selectAllMatching ? rows : selectedOnPage;
  const summaryByStatus = countBy(summaryRows, (row) => row.status.replaceAll("_", " "));
  const summaryByType = countBy(summaryRows, (row) => row.asset_type);

  function clearSelection() {
    setSelected([]);
    setSelectAllMatching(false);
    setSummaryOpen(false);
  }

  async function matchingIds(): Promise<string[]> {
    if (!selectAllMatching) return selected;
    const ids: string[] = [];
    let pageNumber = 1;
    let lastPage = 1;
    do {
      const result = await fetchAssets({
        ...listFilters,
        sort: sort || undefined,
        page: pageNumber,
        per_page: 100,
      });
      ids.push(...result.rows.map((row) => row.id));
      lastPage = result.meta.last_page;
      pageNumber += 1;
    } while (pageNumber <= lastPage && ids.length < 500);
    return ids;
  }

  async function onExportSelected() {
    setActionError(null);
    try {
      const ids = await matchingIds();
      if (selectAllMatching && totalMatching > ids.length) {
        setActionError("Export selected supports up to 500 assets. Narrow the filters.");
        return;
      }
      await downloadAssetExport("xlsx", { ids: ids.join(",") });
    } catch (error: unknown) {
      setActionError(getErrorMessage(error));
    }
  }

  async function onPrintSelected() {
    setActionError(null);
    let tags = selectedOnPage.map((row) => row.asset_tag);
    if (selectAllMatching) {
      if (totalMatching > 50) {
        setActionError("Print Selected supports up to 50 assets. Narrow the filters.");
        return;
      }
      const collected: string[] = [];
      let pageNumber = 1;
      let lastPage = 1;
      do {
        const result = await fetchAssets({
          ...listFilters,
          sort: sort || undefined,
          page: pageNumber,
          per_page: 50,
        });
        collected.push(...result.rows.map((row) => row.asset_tag));
        lastPage = result.meta.last_page;
        pageNumber += 1;
      } while (pageNumber <= lastPage && collected.length < 50);
      tags = collected;
    }
    if (tags.length === 0) return;
    const [first, ...rest] = tags;
    const query = new URLSearchParams();
    if (rest.length > 0) query.set("with", rest.join(","));
    const defaultTemplate =
      printTemplates.find((template) => template.id === registerEntity.data?.print_settings?.default_template_id) ??
      printTemplates[0];
    if (defaultTemplate?.id) query.set("template", defaultTemplate.id);
    const queryText = query.toString();
    const extra = queryText ? `?${queryText}` : "";
    window.open(`/assets/${first}/print${extra}`, "_blank", "noopener,noreferrer");
  }

  function onEditSelected() {
    if (selectedOnPage.length !== 1 || selectAllMatching) return;
    router.push(`/assets/${selectedOnPage[0]?.asset_tag}?edit=1`);
  }

  const table = useReactTable({
    data: rows,
    columns,
    getCoreRowModel: getCoreRowModel(),
  });

  function setFilter(key: string, value: string) {
    const next = new URLSearchParams(params.toString());
    if (value) next.set(key, value);
    else next.delete(key);
    next.delete("page");
    router.replace(`/assets?${next.toString()}`);
  }

  function setPage(nextPage: number) {
    const next = new URLSearchParams(params.toString());
    if (nextPage <= 1) next.delete("page");
    else next.set("page", String(nextPage));
    const query = next.toString();
    router.replace(query ? `/assets?${query}` : "/assets", { scroll: false });
  }

  const columnSensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
  );

  function onColumnDragEnd(event: DragEndEvent) {
    const { active, over } = event;
    if (!over || active.id === over.id) {
      return;
    }
    columnPrefs.reorderVisible(String(active.id), String(over.id));
  }

  const filteredEmpty = Boolean(qParam || status || assetType || lifecycle || attention || repair);

  return (
    <RoleFieldAccessProvider entitySlug="it_asset">
    <PublishPreviewMatrix onChange={setPreviewMatrix} />
    <div className={adminPageShellClass}>
      <WorkspacePageHeader
        title="Assets"
        description="Inventory, assignment, and lifecycle for IT equipment."
        actions={
          <div className="flex flex-wrap items-center justify-end gap-2">
            <RecordActionHistoryButton
              entitySlug="it_asset"
              entityLabel="Assets"
              canRevert={can("assets:manage")}
              onChanged={() => {
                void queryClient.invalidateQueries({ queryKey: ["assets"] });
                void queryClient.invalidateQueries({ queryKey: ["assets-dashboard"] });
              }}
            />
            <AssetListControls
              accessMatrix={accessMatrix}
              canManage={can("assets:manage")}
              canImport={can("assets:import")}
              canExport={can("assets:export")}
              columnOptions={columnOptions}
              columnPrefs={columnPrefs.prefs}
              onSaveColumns={columnPrefs.savePrefs}
              onImport={() => setImportOpen(true)}
              onExport={() => void downloadAssetExport("xlsx")}
            />
          </div>
        }
      />

      {formError ? <p className="text-sm text-muted-foreground">{formError}</p> : null}

      {dashboard.data ? (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
          {dashboard.data.cards.map((card) => {
            const body = (
              <>
                <p className="text-xs text-muted-foreground">{card.label}</p>
                <p className="mt-1 text-2xl font-semibold tabular-nums">{card.value}</p>
                <p className="mt-2 text-[11px] text-muted-foreground">
                  Updated {new Date(dashboard.data.updated_at).toLocaleString()}
                </p>
              </>
            );
            const className = "rounded-xl border border-border bg-card p-4 shadow-sm";
            return card.href ? (
              <Link key={card.key} href={card.href} className={cn(className, "transition-colors hover:bg-muted/40")}>
                {body}
              </Link>
            ) : (
              <div key={card.key} className={className}>
                {body}
              </div>
            );
          })}
        </div>
      ) : null}

      <div className="flex flex-wrap items-end gap-2">
        <Input
          value={search}
          onChange={(event) => setSearch(event.target.value)}
          placeholder="Search tag, serial, hostname, name"
          aria-label="Search assets"
          className="w-full max-w-sm"
        />
        <FilterSelect
          id="assets-status"
          label="Status"
          value={status}
          onChange={(value) => setFilter("status", value)}
          className="w-auto"
          selectClassName="w-auto max-w-xs"
        >
          <option value="">All statuses</option>
          {status.includes(",") ? (
            <option value={status}>{status.replaceAll("_", " ").replaceAll(",", ", ")}</option>
          ) : null}
          {["NEW", "AVAILABLE", "ASSIGNED", "UNDER_REPAIR", "REPLACEMENT_PENDING", "LOST", "STOLEN", "RETIRED", "E_WASTE", "DISPOSED"].map((item) => (
            <option key={item} value={item}>
              {item.replaceAll("_", " ")}
            </option>
          ))}
        </FilterSelect>
        <FilterSelect
          id="assets-type"
          label="Type"
          value={assetType}
          onChange={(value) => setFilter("asset_type", value)}
          className="w-auto"
          selectClassName="w-auto max-w-xs"
        >
          <option value="">All types</option>
          {["laptop", "desktop", "monitor", "dock", "phone", "peripheral"].map((item) => (
            <option key={item} value={item}>
              {item}
            </option>
          ))}
        </FilterSelect>
      </div>

      {attention || repair || lifecycle ? (
        <div className="flex flex-wrap gap-2">
          {attention === "accountability" ? (
            <FilterChip label="Pending accountability" onClear={() => setFilter("attention", "")} />
          ) : null}
          {repair === "PENDING_VENDOR" ? (
            <FilterChip label="Vendor repair pending" onClear={() => setFilter("repair", "")} />
          ) : null}
          {lifecycle ? (
            <FilterChip
              label={lifecycle === "LIFECYCLE_REVIEW,REPLACEMENT_PLANNING" ? "Near EOL" : lifecycle.replaceAll("_", " ").replaceAll(",", ", ")}
              onClear={() => setFilter("lifecycle", "")}
            />
          ) : null}
        </div>
      ) : null}

      {selectionCount > 0 ? (
        <div className={cn("flex flex-wrap items-center gap-2 rounded-xl border px-3 py-2 text-sm", statusToneClasses.info.alert)}>
          <span className="font-medium">
            {selectAllMatching
              ? `${totalMatching.toLocaleString()} matching records selected`
              : `${selected.length} record${selected.length === 1 ? "" : "s"} on this page selected`}
          </span>
          {!selectAllMatching && totalMatching > rows.length ? (
            <button
              type="button"
              className="underline-offset-2 hover:underline"
              onClick={() => setSelectAllMatching(true)}
            >
              Select all {totalMatching.toLocaleString()} matching records
            </button>
          ) : null}
          <button
            type="button"
            className="text-muted-foreground underline-offset-2 hover:underline"
            onClick={clearSelection}
          >
            Clear
          </button>
          <div className="ml-auto flex flex-wrap items-center gap-1.5">
            <Button type="button" size="sm" variant="outline" onClick={() => setSummaryOpen((open) => !open)}>
              <BarChart3 className="size-3.5" />
              Summary Report
            </Button>
            <Button type="button" size="sm" variant="outline" onClick={() => void onPrintSelected()}>
              <Printer className="size-3.5" />
              Print Selected
            </Button>
            {can("assets:export") ? (
              <Button type="button" size="sm" variant="outline" onClick={() => void onExportSelected()}>
                <Download className="size-3.5" />
                Export
              </Button>
            ) : null}
            {can("assets:manage") ? (
              <Button
                type="button"
                size="sm"
                variant="outline"
                disabled={selectAllMatching || selectedOnPage.length !== 1}
                onClick={onEditSelected}
              >
                <Pencil className="size-3.5" />
                Edit
              </Button>
            ) : null}
            {can("assets:transfer") ? (
              <Button type="button" size="sm" onClick={() => setTransferOpen(true)} disabled={selectAllMatching}>
                Transfer
              </Button>
            ) : null}
          </div>
        </div>
      ) : null}

      {summaryOpen && selectionCount > 0 ? (
        <div className="grid gap-4 lg:grid-cols-2">
          <CountShareTable
            title="Status"
            subtitle={
              selectAllMatching
                ? `${totalMatching.toLocaleString()} matching. Counts below are this page.`
                : `${summaryRows.length.toLocaleString()} selected`
            }
            rows={summaryRowsToShare(summaryByStatus)}
          />
          <CountShareTable
            title="Type"
            subtitle={
              selectAllMatching
                ? `${totalMatching.toLocaleString()} matching. Counts below are this page.`
                : `${summaryRows.length.toLocaleString()} selected`
            }
            rows={summaryRowsToShare(summaryByType)}
          />
        </div>
      ) : null}

      {list.isError ? (
        <div className="rounded-xl border border-border bg-card p-4 text-sm">
          <p>{getErrorMessage(list.error)}</p>
          <Button type="button" className="mt-3" variant="outline" onClick={() => void list.refetch()}>
            Retry
          </Button>
        </div>
      ) : (
        <div ref={tableWrapRef} className="rounded-xl border border-border bg-card shadow-sm">
          <DndContext
            sensors={columnSensors}
            collisionDetection={closestCenter}
            onDragEnd={onColumnDragEnd}
          >
            <SortableContext
              items={tableColumns.map((column) => column.id)}
              strategy={horizontalListSortingStrategy}
            >
              <DataTable
                table={table}
                isLoading={list.isLoading}
                isEmpty={!list.isLoading && rows.length === 0}
                emptyMessage={filteredEmpty ? "No assets match these filters." : "No assets registered yet."}
                footer={
                  list.data && list.data.meta.total > 0 ? (
                    <Pagination1
                      className="mx-0 w-auto justify-end py-0"
                      page={list.data.meta.current_page}
                      pageCount={list.data.meta.last_page}
                      disabled={list.isFetching}
                      onPageChange={setPage}
                    />
                  ) : null
                }
              />
            </SortableContext>
          </DndContext>
        </div>
      )}

      {actionError ? <p className="text-sm text-destructive">{actionError}</p> : null}

      <DynRecordImportDialog
        open={importOpen}
        onOpenChange={setImportOpen}
        entitySlug="it_asset"
        entityName="Assets"
        client={{
          downloadTemplate: downloadAssetImportTemplate,
          analyze: analyzeAssetImport,
          run: runAssetImport,
        }}
        onImported={() => {
          void queryClient.invalidateQueries({ queryKey: ["assets"] });
          void queryClient.invalidateQueries({ queryKey: ["assets-dashboard"] });
        }}
      />

      <Dialog open={transferOpen} onOpenChange={setTransferOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Transfer selected assets</DialogTitle>
            <DialogDescription>One employee receives the whole selection. Type TRANSFER to confirm.</DialogDescription>
          </DialogHeader>
          <div className="space-y-3">
            <Label htmlFor="employee-search">Employee</Label>
            <Input id="employee-search" value={employeeQuery} onChange={(event) => setEmployeeQuery(event.target.value)} placeholder="Name or email" />
            <ul className="max-h-40 overflow-auto text-sm">
              {(people.data ?? []).map((person) => (
                <li key={person.id}>
                  <button type="button" className="w-full px-2 py-2 text-left hover:bg-muted" onClick={() => setEmployee(person)}>
                    {person.name} · {person.email}
                  </button>
                </li>
              ))}
            </ul>
            {employee ? <p className="text-sm">Destination: {employee.name}</p> : null}
            <Label htmlFor="confirm-transfer">Confirmation</Label>
            <Input id="confirm-transfer" value={confirmText} onChange={(event) => setConfirmText(event.target.value)} placeholder="TRANSFER" />
            {formError ? <p className="text-sm text-destructive">{formError}</p> : null}
          </div>
          <DialogFooter>
            <Button
              type="button"
              disabled={!employee || confirmText !== "TRANSFER"}
              onClick={() => {
                if (!employee) return;
                setFormError(null);
                void transferAssets({ asset_ids: selected, employee_id: employee.id, confirm: true })
                  .then(async () => {
                    notify.success("Assets transferred", `${selected.length} to ${employee.name}`);
                    setSelected([]);
                    setTransferOpen(false);
                    setConfirmText("");
                    await queryClient.invalidateQueries({ queryKey: ["assets"] });
                  })
                  .catch((error: unknown) => setFormError(getErrorMessage(error)));
              }}
            >
              Confirm transfer
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {registerEntity.data ? (
        <DynSchemaSheets
          entitySlug="it_asset"
          entityName={registerEntity.data.name}
          groups={registerEntity.data.field_groups}
          fields={registerEntity.data.fields}
          mode={schemaMode}
          onClose={() => setSchemaMode({ kind: "closed" })}
          onSaved={() => {
            void queryClient.invalidateQueries({ queryKey: ["asset-schema"] });
            void queryClient.invalidateQueries({ queryKey: ["it-asset-entity"] });
          }}
        />
      ) : null}

      <Dialog open={deleteField !== null} onOpenChange={(open) => { if (!open) setDeleteField(null); }}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Delete field</DialogTitle>
            <DialogDescription>
              {deleteField ? `Remove “${deleteField.label}” from the asset form and this table. Values already stored on assets stay in the record.` : ""}
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setDeleteField(null)}>
              Cancel
            </Button>
            <Button
              type="button"
              variant="destructive"
              onClick={() => {
                if (!deleteField) return;
                const fieldId = deleteField.id;
                setDeleteField(null);
                void deleteAssetField(fieldId)
                  .then(async () => {
                    notify.success("Field deleted");
                    await queryClient.invalidateQueries({ queryKey: ["asset-schema"] });
                    await queryClient.invalidateQueries({ queryKey: ["it-asset-entity"] });
                  })
                  .catch((error: unknown) => setActionError(getErrorMessage(error)));
              }}
            >
              Delete field
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
    </RoleFieldAccessProvider>
  );
}

function countBy(rows: AssetRecord[], label: (row: AssetRecord) => string): Array<{ label: string; count: number }> {
  const counts = new Map<string, number>();
  for (const row of rows) {
    const key = label(row) || "—";
    counts.set(key, (counts.get(key) ?? 0) + 1);
  }
  return [...counts.entries()].map(([name, count]) => ({ label: name, count }));
}

function summaryRowsToShare(counts: Array<{ label: string; count: number }>) {
  const total = counts.reduce((sum, item) => sum + item.count, 0) || 1;
  return counts.map((item) => ({
    label: item.label,
    count: item.count,
    share: Math.round((item.count / total) * 100),
  }));
}

function CountShareTable({
  title,
  subtitle,
  rows,
}: {
  title: string;
  subtitle?: string;
  rows: Array<{ label: string; count: number; share: number }>;
}) {
  return (
    <Card>
      <CardHeader className="gap-1">
        <CardTitle className="text-base font-medium">{title}</CardTitle>
        {subtitle ? <p className="text-xs text-muted-foreground">{subtitle}</p> : null}
      </CardHeader>
      <CardContent className="pt-0">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{title}</TableHead>
              <TableHead className="text-right">Count</TableHead>
              <TableHead className="text-right">Share</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {rows.map((row) => (
              <TableRow key={row.label}>
                <TableCell>{row.label}</TableCell>
                <TableCell className="text-right tabular-nums">{row.count}</TableCell>
                <TableCell className="text-right tabular-nums">{row.share}%</TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </CardContent>
    </Card>
  );
}

function PublishPreviewMatrix({ onChange }: { onChange: (matrix: RoleAccessMatrix | null) => void }) {
  const access = useRoleFieldAccessContext();
  const matrix = access?.selected?.access_matrix ?? null;
  useEffect(() => {
    onChange(matrix);
  }, [matrix, onChange]);
  return null;
}

function AssetColumnHeader({
  id,
  label,
  sort,
  pinned,
  sortable = true,
  canManage,
  field,
  columns = [],
  accessMenu = "field",
  onSort,
  onPin,
  onHide,
  onEdit,
  onDelete,
}: {
  id: string;
  label: string;
  sort: string;
  pinned: boolean;
  sortable?: boolean;
  canManage: boolean;
  field: DynField | null;
  columns?: ReadonlyArray<{ id: string; label: string }>;
  accessMenu?: "field" | "actions";
  onSort: () => void;
  onPin: () => void;
  onHide: () => void;
  onEdit: () => void;
  onDelete: () => void;
}) {
  const { attributes, listeners, setActivatorNodeRef, setNodeRef, transform, transition, isDragging } = useSortable({ id });
  const icon =
    sort === id ? (
      <ArrowUp className="ml-1 size-3.5" />
    ) : sort === `-${id}` ? (
      <ArrowDown className="ml-1 size-3.5" />
    ) : (
      <ArrowUpDown className="ml-1 size-3.5 opacity-40" />
    );

  const canEdit = canManage && field !== null;
  const canDelete = canManage && field !== null && !field.is_system_field;

  return (
    <ContextMenu>
      <ContextMenuTrigger
        className={cn(
          "group inline-flex items-center gap-0.5 rounded-md px-1 py-0.5 hover:bg-muted/80",
          isDragging && "bg-info/10 shadow-sm ring-1 ring-info/30",
        )}
        render={
          <div
            ref={setNodeRef}
            style={{
              transform: CSS.Translate.toString(transform),
              transition,
              opacity: isDragging ? 0.55 : undefined,
            }}
            title="Drag to reorder · click to sort · right-click for column actions"
          />
        }
      >
        <button
          type="button"
          ref={setActivatorNodeRef}
          className="cursor-grab text-muted-foreground opacity-40 group-hover:opacity-100 active:cursor-grabbing"
          aria-label={`Reorder ${label}`}
          {...attributes}
          {...listeners}
        >
          <GripVertical className="size-3.5 shrink-0" />
        </button>
        {sortable ? (
          <button
            type="button"
            className="inline-flex cursor-pointer items-center font-medium hover:text-foreground"
            onClick={(event) => {
              event.stopPropagation();
              onSort();
            }}
            onPointerDown={(event) => event.stopPropagation()}
          >
            {label}
            {icon}
          </button>
        ) : (
          <span className="inline-flex items-center font-medium">{label}</span>
        )}
      </ContextMenuTrigger>
      <ContextMenuContent className="min-w-[14rem]">
        <ContextMenuItem onClick={onPin}>
          <Pin className={cn("size-4", statusToneClasses.info.icon)} />
          {pinned ? "Unpin Column" : "Pin Column"}
        </ContextMenuItem>
        {accessMenu === "field" ? (
          <ContextMenuItem disabled={!canEdit} onClick={onEdit}>
            <Pencil className="size-4" />
            Edit Field
          </ContextMenuItem>
        ) : null}
        <ContextMenuItem onClick={onHide}>
          <EyeOff className={cn("size-4", statusToneClasses.warning.icon)} />
          Hide Column
        </ContextMenuItem>
        {accessMenu === "field" ? (
          <>
            <ContextMenuSeparator />
            <ContextMenuItem destructive disabled={!canDelete} onClick={onDelete}>
              <Trash2 className="size-4" />
              Delete Field
            </ContextMenuItem>
            <RoleFieldAccessMenuItems fieldName={id} fieldLabel={label} columns={columns} />
          </>
        ) : (
          <>
            <ContextMenuSeparator />
            <RoleRowActionsMenuItems columns={columns} />
          </>
        )}
      </ContextMenuContent>
    </ContextMenu>
  );
}

function FilterChip({ label, onClear }: { label: string; onClear: () => void }) {
  return (
    <span className="inline-flex items-center gap-2 rounded-md border border-border bg-card px-2 py-1 text-xs text-foreground">
      {label}
      <button type="button" className="text-muted-foreground hover:text-foreground" onClick={onClear}>
        Clear
      </button>
    </span>
  );
}
