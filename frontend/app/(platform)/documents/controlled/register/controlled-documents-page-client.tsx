"use client";

import Link from "next/link";
import { FilePlus2, Search, X } from "lucide-react";
import { keepPreviousData, useQuery, useQueryClient } from "@tanstack/react-query";
import { useRouter, useSearchParams } from "next/navigation";
import { useCallback, useEffect, useMemo, useState } from "react";
import type { OnChangeFn, SortingState } from "@tanstack/react-table";

import { ControlledDocumentDetailDrawer } from "@/components/documents/controlled-document-detail-drawer";
import { ControlledDocumentImportDialog } from "@/components/documents/controlled-document-import-dialog";
import { createControlledDocumentsTableColumns } from "@/components/documents/controlled-documents-table-columns";
import { WorkspacePageHeader } from "@/components/layout/workspace-page-header";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button, buttonVariants } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { DatePicker } from "@/components/ui/date-picker";
import { FilterSelect } from "@/components/forms/filter-select";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { DataListCard } from "@/components/ui/data-list-card";
import { RefreshingHint } from "@/components/ui/refreshing-hint";
import { fetchControlledDocuments } from "@/lib/api/modules/controlled-documents-api";
import { getErrorMessage } from "@/lib/api/error";
import { permissions } from "@/lib/rbac/permissions";
import { sortParamFromSortingState, sortingStateFromSortParam } from "@/lib/table/server-sort";
import { controlledDocumentSubmissionUrl } from "@/modules/documents/controlled-document-submission-url";
import {
  controlledRegisterClearFilters,
  controlledRegisterFilterChips,
  controlledRegisterHasFilters,
  controlledRegisterHref,
  controlledRegisterParams,
  readControlledRegisterQuery,
} from "@/modules/documents/controlled-document-register-query";
import { usePermission } from "@/hooks/use-permission";
import { cn } from "@/lib/utils";

const DEFAULT_SORT = "document_code:asc";
const SORTABLE = [
  "document_code",
  "dcf_number",
  "title",
  "document_type",
  "department",
  "status",
  "effective_date",
  "next_review_date",
] as const;

const FOCUS_RING =
  "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background";

export function ControlledDocumentsPageClient() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const queryClient = useQueryClient();
  const canImport = usePermission([permissions.documentsControlledImport]);
  const canManage = usePermission([permissions.documentsControlledManage]);
  const canCreate = usePermission([permissions.documentsControlledCreate]);
  const queryState = useMemo(
    () => readControlledRegisterQuery(searchParams, DEFAULT_SORT),
    [searchParams],
  );
  const [search, setSearch] = useState(queryState.q);
  const [syncedQuery, setSyncedQuery] = useState(queryState.q);
  const [importOpen, setImportOpen] = useState(false);
  if (queryState.q !== syncedQuery) {
    setSyncedQuery(queryState.q);
    setSearch(queryState.q);
  }

  const replace = useCallback(
    (patch: Record<string, string | null>) => {
      router.replace(controlledRegisterHref(searchParams, patch, DEFAULT_SORT), { scroll: false });
    },
    [router, searchParams],
  );

  useEffect(() => {
    if (search === queryState.q) {
      return;
    }
    const timer = window.setTimeout(() => {
      replace({ [controlledRegisterParams.q]: search || null });
    }, 250);
    return () => window.clearTimeout(timer);
  }, [search, queryState.q, replace]);

  const listQuery = useQuery({
    queryKey: ["documents", "controlled", queryState],
    queryFn: () =>
      fetchControlledDocuments({
        search: queryState.q || undefined,
        department: queryState.department || undefined,
        status: queryState.status || undefined,
        document_type: queryState.documentType || undefined,
        review: queryState.review || undefined,
        file: queryState.file || undefined,
        effective_from: queryState.effectiveFrom || undefined,
        effective_to: queryState.effectiveTo || undefined,
        published_from: queryState.publishedFrom || undefined,
        published_to: queryState.publishedTo || undefined,
        per_page: 50,
        sort: queryState.sort,
      }),
    placeholderData: keepPreviousData,
  });

  const kpis = listQuery.data?.kpis;
  const documentRows = listQuery.data?.documents.data;
  const rows = useMemo(() => documentRows ?? [], [documentRows]);
  const total = listQuery.data?.documents.total ?? rows.length;
  const departments = useMemo(() => {
    const values = new Set(listQuery.data?.facets?.departments ?? []);
    if (queryState.department) {
      values.add(queryState.department);
    }
    return [...values].sort();
  }, [listQuery.data?.facets?.departments, queryState.department]);
  const documentTypes = useMemo(() => {
    const values = new Set(listQuery.data?.facets?.document_types ?? []);
    if (queryState.documentType) {
      values.add(queryState.documentType);
    }
    return [...values].sort();
  }, [listQuery.data?.facets?.document_types, queryState.documentType]);

  const defaultFormId = useMemo(
    () =>
      listQuery.data?.default_form_id ??
      rows.find((row) => row.revision_form_id)?.revision_form_id ??
      rows.find((row) => row.e_approval_form_id)?.e_approval_form_id ??
      null,
    [listQuery.data?.default_form_id, rows],
  );
  const newRequestHref = defaultFormId
    ? controlledDocumentSubmissionUrl({ formId: defaultFormId, mode: "new" })
    : "/e-approval/submissions/new";

  const openDocument = useCallback(
    (id: string) => {
      replace({ [controlledRegisterParams.document]: id });
    },
    [replace],
  );

  const columns = useMemo(
    () => createControlledDocumentsTableColumns({ canCreate, onOpen: openDocument }),
    [canCreate, openDocument],
  );

  const sorting = useMemo(
    () => sortingStateFromSortParam(queryState.sort, {}, SORTABLE),
    [queryState.sort],
  );
  const onSortingChange = useCallback<OnChangeFn<SortingState>>(
    (updater) => {
      const next = typeof updater === "function" ? updater(sorting) : updater;
      replace({
        [controlledRegisterParams.sort]: sortParamFromSortingState(next, DEFAULT_SORT),
      });
    },
    [replace, sorting],
  );

  const chips = controlledRegisterFilterChips(queryState);
  const filtered = controlledRegisterHasFilters(queryState);

  function selectStatus(value: "" | "pending" | "published" | "obsolete" | "incoming") {
    replace({
      [controlledRegisterParams.status]: value === "" || queryState.status === value ? null : value,
    });
  }

  return (
    <PermissionGate requiredPermissions={[permissions.documentsControlledView]}>
      <div className="space-y-6">
        <WorkspacePageHeader
          title="Controlled Document Register"
          description="ISO master list. Pending rows have never been published. Incoming rows are published, with a revision still in approval."
          actions={
            <>
              <Link
                href="/documents/controlled/reports"
                className={buttonVariants({ variant: "outline", size: "sm" })}
              >
                Reports
              </Link>
              {canManage ? (
                <Link
                  href="/documents/controlled/access"
                  className={buttonVariants({ variant: "outline", size: "sm" })}
                >
                  Register access
                </Link>
              ) : null}
              {canCreate ? (
                <Link href={newRequestHref} className={buttonVariants({ size: "sm" })}>
                  <FilePlus2 className="mr-1.5 h-4 w-4" />
                  New controlled document
                </Link>
              ) : null}
              {canImport ? (
                <Button type="button" variant="outline" size="sm" onClick={() => setImportOpen(true)}>
                  Import
                </Button>
              ) : null}
            </>
          }
        />

        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5" role="group" aria-label="Register status">
          <StatusMetric
            label="Documents in register"
            value={kpis?.total}
            accent="blue"
            pressed={queryState.status === ""}
            onClick={() => selectStatus("")}
          />
          <StatusMetric
            label="Pending"
            value={kpis?.pending}
            accent="orange"
            pressed={queryState.status === "pending"}
            onClick={() => selectStatus("pending")}
          />
          <StatusMetric
            label="Published"
            value={kpis?.published}
            accent="green"
            pressed={queryState.status === "published"}
            onClick={() => selectStatus("published")}
          />
          <StatusMetric
            label="Obsolete"
            value={kpis?.obsolete}
            accent="red"
            pressed={queryState.status === "obsolete"}
            onClick={() => selectStatus("obsolete")}
          />
          <StatusMetric
            label="Incoming"
            value={kpis?.incoming}
            accent="sky"
            pressed={queryState.status === "incoming"}
            onClick={() => selectStatus("incoming")}
          />
        </div>

        <DataListCard>
          <div className="space-y-3 border-b border-border p-4">
            <div className="flex flex-wrap items-end gap-3">
              <div className="min-w-[220px] flex-1 space-y-1.5">
                <label htmlFor="cdr-search" className="text-xs font-medium text-muted-foreground">
                  Search
                </label>
                <div className="relative">
                  <Search className="pointer-events-none absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                  <Input
                    id="cdr-search"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder="Document code, title, or DCF number"
                    className="pl-9"
                  />
                </div>
              </div>
              <div className="w-full min-w-[140px] sm:w-44">
                <FilterSelect
                  id="cdr-dept"
                  label="Department"
                  value={queryState.department}
                  onChange={(value) => replace({ [controlledRegisterParams.department]: value || null })}
                >
                  <option value="">All departments</option>
                  {departments.map((item) => (
                    <option key={item} value={item}>
                      {item}
                    </option>
                  ))}
                </FilterSelect>
              </div>
              <div className="w-full min-w-[140px] sm:w-44">
                <FilterSelect
                  id="cdr-type"
                  label="Document type"
                  value={queryState.documentType}
                  onChange={(value) => replace({ [controlledRegisterParams.documentType]: value || null })}
                >
                  <option value="">All types</option>
                  {documentTypes.map((item) => (
                    <option key={item} value={item}>
                      {item}
                    </option>
                  ))}
                </FilterSelect>
              </div>
              <div className="w-full min-w-[140px] sm:w-44">
                <FilterSelect
                  id="cdr-review"
                  label="Review"
                  value={queryState.review}
                  onChange={(value) => replace({ [controlledRegisterParams.review]: value || null })}
                >
                  <option value="">Any review date</option>
                  <option value="overdue">Overdue</option>
                  <option value="due_30">Due in 30 days</option>
                  <option value="due_90">Due in 90 days</option>
                  <option value="none">No review date</option>
                </FilterSelect>
              </div>
              <div className="w-full min-w-[140px] sm:w-40">
                <FilterSelect
                  id="cdr-file"
                  label="File"
                  value={queryState.file}
                  onChange={(value) => replace({ [controlledRegisterParams.file]: value || null })}
                >
                  <option value="">Any file</option>
                  <option value="has">Has file</option>
                  <option value="missing">Missing file</option>
                </FilterSelect>
              </div>
              <div className="w-full min-w-[140px] space-y-1.5 sm:w-40">
                <label htmlFor="cdr-effective-from" className="text-xs font-medium text-muted-foreground">
                  Effective from
                </label>
                <DatePicker
                  id="cdr-effective-from"
                  value={queryState.effectiveFrom}
                  onChange={(value) => replace({ [controlledRegisterParams.effectiveFrom]: value || null })}
                />
              </div>
              <div className="w-full min-w-[140px] space-y-1.5 sm:w-40">
                <label htmlFor="cdr-effective-to" className="text-xs font-medium text-muted-foreground">
                  Effective to
                </label>
                <DatePicker
                  id="cdr-effective-to"
                  value={queryState.effectiveTo}
                  onChange={(value) => replace({ [controlledRegisterParams.effectiveTo]: value || null })}
                />
              </div>
            </div>

            {chips.length > 0 ? (
              <div className="flex flex-wrap items-center gap-2">
                {chips.map((chip) => (
                  <button
                    key={chip.id}
                    type="button"
                    className={cn(
                      "inline-flex h-7 items-center gap-1 rounded-full border border-border bg-muted px-2 text-xs text-foreground",
                      FOCUS_RING,
                    )}
                    onClick={() => replace(chip.clear)}
                  >
                    {chip.label}
                    <X className="size-3" aria-hidden />
                    <span className="sr-only">Remove {chip.label}</span>
                  </button>
                ))}
                <button
                  type="button"
                  className={cn("text-xs font-medium text-foreground underline-offset-2 hover:underline", FOCUS_RING)}
                  onClick={() => replace(controlledRegisterClearFilters())}
                >
                  Clear filters
                </button>
                {listQuery.isFetching ? <RefreshingHint label="Updating register" /> : null}
              </div>
            ) : listQuery.isFetching ? (
              <RefreshingHint label="Updating register" />
            ) : null}
          </div>

          {listQuery.isError ? (
            <div className="space-y-3 px-4 py-8 text-center">
              <p className="text-sm text-foreground">{getErrorMessage(listQuery.error)}</p>
              <Button type="button" variant="outline" size="sm" onClick={() => void listQuery.refetch()}>
                Retry
              </Button>
            </div>
          ) : (
            <RegistryDataTableView
              columns={columns}
              data={rows}
              getRowId={(row) => row.id}
              isLoading={listQuery.isLoading}
              isEmpty={!listQuery.isLoading && rows.length === 0}
              emptyContent={
                <RegisterEmptyState
                  filtered={filtered}
                  canCreate={canCreate}
                  newRequestHref={newRequestHref}
                  onClear={() => replace(controlledRegisterClearFilters())}
                />
              }
              enableColumnVisibility
              columnVisibilityStorageKey="toweros.table.columns.documents.controlled"
              sorting={sorting}
              onSortingChange={onSortingChange}
              manualSorting
              toolbarStart={
                <p className="text-xs text-muted-foreground tabular-nums">
                  {listQuery.isLoading ? "Loading documents" : `${total} documents`}
                </p>
              }
            />
          )}
        </DataListCard>
      </div>

      <ControlledDocumentImportDialog
        open={importOpen}
        onOpenChange={setImportOpen}
        onImported={() => {
          void queryClient.invalidateQueries({ queryKey: ["documents", "controlled"] });
        }}
      />

      <ControlledDocumentDetailDrawer
        documentId={queryState.documentId}
        canCreate={canCreate}
        canManage={canManage}
        onClose={() => replace({ [controlledRegisterParams.document]: null })}
      />
    </PermissionGate>
  );
}

function StatusMetric({
  label,
  value,
  pressed,
  onClick,
}: {
  label: string;
  value: number | undefined;
  accent?: string;
  pressed: boolean;
  onClick: () => void;
}) {
  return (
    <button
      type="button"
      aria-pressed={pressed}
      onClick={onClick}
      className={cn(
        "rounded-xl border border-border bg-card px-4 py-3 text-left shadow-sm transition-colors",
        FOCUS_RING,
        pressed ? "border-foreground" : "hover:bg-muted/40",
      )}
    >
      <p className="text-xs text-muted-foreground">{label}</p>
      <p className="mt-1 text-2xl font-medium tabular-nums">{value ?? "—"}</p>
    </button>
  );
}

function RegisterEmptyState({
  filtered,
  canCreate,
  newRequestHref,
  onClear,
}: {
  filtered: boolean;
  canCreate: boolean;
  newRequestHref: string;
  onClear: () => void;
}) {
  if (filtered) {
    return (
      <div className="space-y-3 px-4 py-10 text-center">
        <p className="text-sm text-muted-foreground">No documents match these filters.</p>
        <Button type="button" variant="outline" size="sm" onClick={onClear}>
          Clear filters
        </Button>
      </div>
    );
  }

  return (
    <div className="space-y-3 px-4 py-10 text-center">
      <p className="text-sm text-muted-foreground">
        {canCreate
          ? "No controlled documents visible for your account. Authors only see documents from their approved E-Forms requests."
          : "No controlled documents yet."}
      </p>
      {canCreate ? (
        <Link href={newRequestHref} className={buttonVariants({ size: "sm" })}>
          <FilePlus2 className="mr-1.5 h-4 w-4" />
          New controlled document
        </Link>
      ) : null}
    </div>
  );
}
