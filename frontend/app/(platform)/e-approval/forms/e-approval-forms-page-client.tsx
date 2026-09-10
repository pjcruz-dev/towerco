"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { useEffect, useMemo, useState } from "react";
import { FileStack, FileText, Plus, Upload } from "lucide-react";

import { ConfigurableModulePageHeader } from "@/components/dashboard/configurable-module-page-header";
import { DashboardLayoutToolbar } from "@/components/dashboard/dashboard-layout-toolbar";
import { DashboardWidgetBoard } from "@/components/dashboard/dashboard-widget-board";
import { EApprovalFormGalleryCard } from "@/components/e-approval/e-approval-form-gallery-card";
import { EApprovalFormTemplateGallery } from "@/components/e-approval/e-approval-form-template-gallery";
import { EApprovalFormImportExportPanel } from "@/components/e-approval/e-approval-form-import-export-panel";
import { EApprovalListShell } from "@/components/e-approval/e-approval-list-shell";
import { EApprovalListViewToggle } from "@/components/e-approval/e-approval-list-view-toggle";
import { createEApprovalFormsTableColumns } from "@/components/e-approval/e-approval-forms-table-columns";
import { PaginatedListFooter } from "@/components/registry/paginated-list-footer";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useDashboardBoardLayoutHandlers } from "@/hooks/use-dashboard-board-layout-handlers";
import {
  useDashboardCustomizeMode,
  useDashboardLayoutPrefs,
} from "@/hooks/use-dashboard-layout-prefs";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { useEApprovalListView } from "@/hooks/use-e-approval-list-view";
import { usePermission } from "@/hooks/use-permission";
import { useServerTableSort } from "@/hooks/use-server-table-sort";
import { fetchEApprovalFormsIndex } from "@/lib/api/modules/e-approval-api";
import { DEFAULT_FORM_DOCUMENT_NUMBER } from "@/modules/e-approval/form-document-number";
import { permissions } from "@/lib/rbac/permissions";
import {
  bindableCatalogEntries,
  buildDynamicBoardWidgets,
} from "@/lib/ui/build-dynamic-board-widgets";
import { emptyNormalizedData } from "@/lib/ui/dashboard-widget-data";
import { applyPageEnhancements, eApprovalFormsEnhancements } from "@/lib/ui/page-enhancement-bags";
import type { DashboardWidgetDef } from "@/lib/ui/dashboard-widget-registry";
import { E_APPROVAL_FORMS_PAGE_CHROME } from "@/lib/ui/page-chrome-config";

const PER_PAGE = 25;
const VIEW_STORAGE_KEY = "e-approval-forms-view";
const DEFAULT_SORT = "created_at:desc";
const LAYOUT_KEY = "toweros.e-approval.forms.layout";

export function EApprovalFormsPageClient() {
  const router = useRouter();
  const canManage = usePermission([permissions.eApprovalFormsManage]);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const [showImport, setShowImport] = useState(false);
  const [viewMode, setViewMode] = useEApprovalListView(VIEW_STORAGE_KEY, "gallery");
  const { layout, setLayout, tenantDefault, publishTenantDefault, resetToTenantDefault } = useDashboardLayoutPrefs(LAYOUT_KEY);
  const { editing, setEditing } = useDashboardCustomizeMode();
  const debouncedSearch = useDebouncedValue(search, 350, () => setPage(1));
  const { sort, sorting, onSortingChange, manualSorting } = useServerTableSort({
    defaultSort: DEFAULT_SORT,
    sortableColumnIds: ["name", "status", "category"],
  });

  useEffect(() => {
    setPage(1);
  }, [sort]);

  const { data, isFetching, isError } = useQuery({
    queryKey: ["e-approval", "forms", page, debouncedSearch, sort],
    queryFn: () =>
      fetchEApprovalFormsIndex({
        page,
        per_page: PER_PAGE,
        search: debouncedSearch.trim() || undefined,
        sort,
      }),
  });

  const rows = data?.data ?? [];
  const meta = data?.meta;
  const isEmpty = !isFetching && rows.length === 0;
  const formColumns = useMemo(() => createEApprovalFormsTableColumns(canManage), [canManage]);
  const normalizedData = useMemo(
    () => applyPageEnhancements(emptyNormalizedData(), eApprovalFormsEnhancements()),
    [],
  );

  const layoutMeta = useMemo(() => {
    const items = [
      { id: "template_gallery", label: "Starter templates" },
      { id: "filters", label: "Search & view" },
      { id: "table", label: "Forms catalog" },
    ];
    if (canManage) {
      items.unshift({ id: "import_panel", label: "Import panel" });
    }
    return items;
  }, [canManage]);

  const boardWidgets = useMemo((): DashboardWidgetDef[] => {
    const widgets: DashboardWidgetDef[] = [];

    if (canManage) {
      widgets.push({
        id: "import_panel",
        label: "Import panel",
        defaultSpan: "full",
        render: () =>
          showImport ? (
            <EApprovalFormImportExportPanel
              importOnly
              className="rounded-xl border border-border bg-card p-4 shadow-sm"
              formName="imported-form"
              getDraftPayload={() => ({
                name: "",
                description: "",
                status: "draft",
                fields: [{ type: "text", name: "summary", label: "Summary" }],
                steps: [],
                metadataJson: "{}",
                brandLogoUrl: null,
                documentNumber: DEFAULT_FORM_DOCUMENT_NUMBER,
              })}
              onLoadIntoEditor={() => {}}
              onOpenInEditor={() => router.push("/e-approval/forms/create")}
              onImported={(id) => router.push(`/e-approval/forms/${id}`)}
            />
          ) : (
            <p className="rounded-xl border border-dashed border-border bg-muted/20 px-4 py-6 text-center text-sm text-muted-foreground">
              Import is hidden. Use <span className="font-medium text-foreground">Import JSON</span> in the
              header to show this panel.
            </p>
          ),
      });
      widgets.push({
        id: "template_gallery",
        label: "Starter templates",
        defaultSpan: "full",
        render: () => (
          <EApprovalFormTemplateGallery onCreated={(id) => router.push(`/e-approval/forms/${id}`)} />
        ),
      });
    }

    widgets.push(
      {
        id: "filters",
        label: "Search & view",
        defaultSpan: "full",
        render: () => (
          <div className="flex flex-wrap items-end justify-between gap-3 rounded-xl border border-border bg-card p-4 shadow-sm">
            <div className="min-w-0 flex-1">
              <label
                className="mb-1 block text-xs font-medium text-muted-foreground"
                htmlFor="e-approval-forms-search"
              >
                Search forms
              </label>
              <Input
                id="e-approval-forms-search"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Form name or category"
                className="h-11 w-full text-base sm:h-9 sm:max-w-md sm:text-sm"
              />
            </div>
            <EApprovalListViewToggle value={viewMode} onChange={setViewMode} ariaLabel="Forms list view" />
          </div>
        ),
      },
      {
        id: "table",
        label: "Forms catalog",
        defaultSpan: "full",
        hideable: false,
        removable: false,
        render: () => (
          <EApprovalListShell
            error={isError ? <p className="px-4 py-3 text-sm text-destructive">Could not load forms.</p> : null}
            footer={
              meta ? (
                <PaginatedListFooter
                  meta={{ ...meta, current_page: page }}
                  onPageChange={setPage}
                  isPending={isFetching}
                />
              ) : null
            }
          >
            {viewMode === "gallery" ? (
              <div className="p-4">
                {isFetching && rows.length === 0 ? (
                  <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {Array.from({ length: 3 }).map((_, index) => (
                      <div key={index} className="h-52 animate-pulse rounded-xl border border-border bg-muted/40" />
                    ))}
                  </div>
                ) : isEmpty ? (
                  <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-border bg-muted/20 px-6 py-14 text-center">
                    <div className="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                      <FileText className="h-6 w-6" />
                    </div>
                    <h2 className="mt-4 text-base font-medium">No forms yet</h2>
                    <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                      Create a new form or import a legacy JSON export to get started.
                    </p>
                    {canManage ? (
                      <div className="mt-4 flex flex-wrap justify-center gap-2">
                        <Button size="sm" onClick={() => router.push("/e-approval/forms/new")}>
                          New form
                        </Button>
                        <Button size="sm" variant="outline" onClick={() => setShowImport(true)}>
                          Import JSON
                        </Button>
                      </div>
                    ) : null}
                  </div>
                ) : (
                  <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {rows.map((row) => (
                      <EApprovalFormGalleryCard key={row.id} form={row} canManage={canManage} />
                    ))}
                  </div>
                )}
              </div>
            ) : (
              <RegistryDataTableView
                columns={formColumns}
                data={rows}
                getRowId={(row) => row.id}
                isLoading={isFetching && rows.length === 0}
                isEmpty={isEmpty}
                emptyMessage="No forms yet."
                getRowClassName={() => "group"}
                enableColumnVisibility
                columnVisibilityStorageKey="toweros.table.columns.e-approval.forms"
                sorting={sorting}
                onSortingChange={onSortingChange}
                manualSorting={manualSorting}
              />
            )}
          </EApprovalListShell>
        ),
      },
    );

    return widgets;
  }, [
    canManage,
    formColumns,
    isEmpty,
    isError,
    isFetching,
    manualSorting,
    meta,
    onSortingChange,
    page,
    router,
    rows,
    search,
    showImport,
    sorting,
    viewMode,
  ]);

  const titleOverrides = useMemo(() => {
    const map: Record<string, string | undefined> = {};
    for (const [id, opt] of Object.entries(layout.widgetOptions)) {
      map[id] = opt.title;
    }
    return map;
  }, [layout.widgetOptions]);

  const defaultEnabledIds = useMemo(
    () =>
      canManage
        ? ["template_gallery", "filters", "table"]
        : ["filters", "table"],
    [canManage],
  );

  const catalogBoardWidgets = useMemo(
    () =>
      buildDynamicBoardWidgets({
        moduleId: "e-approval",
        data: normalizedData,
        slots: boardWidgets,
        enabledIds: layout.enabledWidgetIds.length > 0 ? layout.enabledWidgetIds : defaultEnabledIds,
        titleOverrides,
        widgetOptions: layout.widgetOptions,
      }),
    [
      boardWidgets,
      defaultEnabledIds,
      layout.enabledWidgetIds,
      layout.widgetOptions,
      normalizedData,
      titleOverrides,
    ],
  );

  const bindableCatalog = useMemo(
    () => bindableCatalogEntries("e-approval", boardWidgets, normalizedData),
    [boardWidgets, normalizedData],
  );

  const addableCatalog = useMemo(() => {
    const enabled = new Set(
      layout.enabledWidgetIds.length > 0 ? layout.enabledWidgetIds : defaultEnabledIds,
    );
    return bindableCatalog.filter((entry) => !enabled.has(entry.id));
  }, [bindableCatalog, defaultEnabledIds, layout.enabledWidgetIds]);

  const boardHandlers = useDashboardBoardLayoutHandlers(layout, setLayout, defaultEnabledIds);

  return (
    <PermissionGate requiredPermissions={[permissions.eApprovalFormsManage]}>
      <div className="space-y-5">
        <ConfigurableModulePageHeader
          defaults={E_APPROVAL_FORMS_PAGE_CHROME}
          prefs={layout.pageChrome}
          editing={editing}
          onChromeChange={(pageChrome) => setLayout({ ...layout, pageChrome })}
          renderActions={({ isVisible }) =>
            canManage ? (
              <>
                {isVisible("customize") ? (
                  <DashboardLayoutToolbar
                    widgets={layoutMeta}
                    catalogModule="e-approval"
                    layout={layout}
                    editing={editing}
                    onEditingChange={setEditing}
                    onChange={setLayout}
                    defaultEnabledIds={defaultEnabledIds}
                    bindableCatalog={bindableCatalog}
                    hasTenantDefault={Boolean(tenantDefault)}
                    onPublishTenantDefault={publishTenantDefault}
                    onResetToTenantDefault={resetToTenantDefault}
                    data={normalizedData}
                  />
                ) : null}
                {isVisible("templates") ? (
                  <Link
                    href="/e-approval/forms/templates"
                    className="inline-flex h-8 items-center gap-1.5 rounded-md border border-input bg-background px-3 text-sm hover:bg-muted"
                  >
                    <FileStack className="h-3.5 w-3.5" />
                    Templates
                  </Link>
                ) : null}
                {isVisible("import") ? (
                  <Button size="sm" type="button" variant="outline" onClick={() => setShowImport((v) => !v)}>
                    <Upload className="h-3.5 w-3.5" />
                    {showImport ? "Hide import" : "Import JSON"}
                  </Button>
                ) : null}
                {isVisible("new") ? (
                  <Button size="sm" type="button" onClick={() => router.push("/e-approval/forms/create")}>
                    <Plus className="h-3.5 w-3.5" />
                    New form
                  </Button>
                ) : null}
              </>
            ) : isVisible("customize") ? (
              <DashboardLayoutToolbar
                widgets={layoutMeta}
                catalogModule="e-approval"
                layout={layout}
                editing={editing}
                onEditingChange={setEditing}
                onChange={setLayout}
                defaultEnabledIds={defaultEnabledIds}
                bindableCatalog={bindableCatalog}
                hasTenantDefault={Boolean(tenantDefault)}
                onPublishTenantDefault={publishTenantDefault}
                onResetToTenantDefault={resetToTenantDefault}
                data={normalizedData}
              />
            ) : null
          }
        />

        <DashboardWidgetBoard
          widgets={catalogBoardWidgets}
          order={layout.widgetOrder}
          hiddenIds={layout.hiddenWidgetIds}
          enabledIds={layout.enabledWidgetIds.length > 0 ? layout.enabledWidgetIds : defaultEnabledIds}
          layoutPrefs={layout}
          editing={editing}
          addableCatalog={addableCatalog}
          data={normalizedData}
          {...boardHandlers}
          onAddWidget={(entry, insertAt) => {
            boardHandlers.onAddWidget(entry, insertAt);
            setEditing(true);
          }}
        />
      </div>
    </PermissionGate>
  );
}
