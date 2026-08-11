"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { useEffect, useMemo, useState } from "react";
import { FileStack, FileText, Plus, Upload } from "lucide-react";

import { EApprovalFormGalleryCard } from "@/components/e-approval/e-approval-form-gallery-card";
import { EApprovalFormTemplateGallery } from "@/components/e-approval/e-approval-form-template-gallery";
import { EApprovalFormImportExportPanel } from "@/components/e-approval/e-approval-form-import-export-panel";
import { EApprovalListShell } from "@/components/e-approval/e-approval-list-shell";
import { EApprovalListViewToggle } from "@/components/e-approval/e-approval-list-view-toggle";
import { EApprovalPageHeader } from "@/components/e-approval/e-approval-page-header";
import { createEApprovalFormsTableColumns } from "@/components/e-approval/e-approval-forms-table-columns";
import { PaginatedListFooter } from "@/components/registry/paginated-list-footer";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { useEApprovalListView } from "@/hooks/use-e-approval-list-view";
import { usePermission } from "@/hooks/use-permission";
import { useServerTableSort } from "@/hooks/use-server-table-sort";
import { fetchEApprovalFormsIndex } from "@/lib/api/modules/e-approval-api";
import { DEFAULT_FORM_DOCUMENT_NUMBER } from "@/modules/e-approval/form-document-number";
import { permissions } from "@/lib/rbac/permissions";

const PER_PAGE = 25;
const VIEW_STORAGE_KEY = "e-approval-forms-view";
const DEFAULT_SORT = "created_at:desc";

export function EApprovalFormsPageClient() {
  const router = useRouter();
  const canManage = usePermission([permissions.eApprovalFormsManage]);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const [showImport, setShowImport] = useState(false);
  const [viewMode, setViewMode] = useEApprovalListView(VIEW_STORAGE_KEY, "gallery");
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

  return (
    <PermissionGate requiredPermissions={[permissions.eApprovalFormsManage]}>
      <div className="space-y-5">
        <EApprovalPageHeader
          title="Forms"
          description="Design templates and workflows. Switch gallery or table to manage at your pace. Volume and status trends live under Reports."
          actions={
            canManage ? (
              <>
                <Link
                  href="/e-approval/forms/templates"
                  className="inline-flex h-8 items-center gap-1.5 rounded-md border border-input bg-background px-3 text-sm hover:bg-muted"
                >
                  <FileStack className="h-3.5 w-3.5" />
                  Templates
                </Link>
                <Button size="sm" type="button" variant="outline" onClick={() => setShowImport((v) => !v)}>
                  <Upload className="h-3.5 w-3.5" />
                  {showImport ? "Hide import" : "Import JSON"}
                </Button>
                <Button size="sm" type="button" onClick={() => router.push("/e-approval/forms/create")}>
                  <Plus className="h-3.5 w-3.5" />
                  New form
                </Button>
              </>
            ) : null
          }
        />

        {canManage && showImport ? (
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
        ) : null}

        {canManage ? (
          <EApprovalFormTemplateGallery onCreated={(id) => router.push(`/e-approval/forms/${id}`)} />
        ) : null}

        <EApprovalListShell
          error={isError ? <p className="px-4 py-3 text-sm text-destructive">Could not load forms.</p> : null}
          toolbar={
            <div className="flex flex-wrap items-end justify-between gap-3">
              <div className="min-w-0 flex-1">
                <label className="mb-1 block text-xs font-medium text-muted-foreground" htmlFor="e-approval-forms-search">
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
          }
          footer={
            meta ? (
              <PaginatedListFooter meta={{ ...meta, current_page: page }} onPageChange={setPage} isPending={isFetching} />
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
      </div>
    </PermissionGate>
  );
}
