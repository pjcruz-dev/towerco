"use client";

import Link from "next/link";
import { FilePlus2, Search } from "lucide-react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useSearchParams } from "next/navigation";
import { useEffect, useMemo, useRef, useState } from "react";

import { ControlledDocumentDetailDrawer } from "@/components/documents/controlled-document-detail-drawer";
import { ControlledDocumentRegisterAccessCard } from "@/components/documents/controlled-document-register-access-card";
import { createControlledDocumentsTableColumns } from "@/components/documents/controlled-documents-table-columns";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button, buttonVariants } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { FilterSelect } from "@/components/forms/filter-select";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { DataListCard } from "@/components/ui/data-list-card";
import {
  fetchControlledDocuments,
  importControlledDocumentsCsv,
} from "@/lib/api/modules/controlled-documents-api";
import { getErrorMessage } from "@/lib/api/error";
import { permissions } from "@/lib/rbac/permissions";
import { controlledDocumentSubmissionUrl } from "@/modules/documents/controlled-document-submission-url";
import { useNotificationStore } from "@/stores/notification-store";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { usePermission } from "@/hooks/use-permission";
import { useServerTableSort } from "@/hooks/use-server-table-sort";

const DEFAULT_SORT = "document_code:asc";

export function ControlledDocumentsPageClient() {
  const push = useNotificationStore((s) => s.push);
  const queryClient = useQueryClient();
  const searchParams = useSearchParams();
  const canImport = usePermission([permissions.documentsControlledImport]);
  const canManage = usePermission([permissions.documentsControlledManage]);
  const canCreate = usePermission([permissions.documentsControlledCreate]);
  const [search, setSearch] = useState("");
  const debouncedSearch = useDebouncedValue(search, 300);
  const [department, setDepartment] = useState("");
  const [status, setStatus] = useState("");
  const [selectedId, setSelectedId] = useState<string | null>(searchParams.get("document"));
  const fileInputRef = useRef<HTMLInputElement>(null);
  const { sort, sorting, onSortingChange, manualSorting } = useServerTableSort({
    defaultSort: DEFAULT_SORT,
    sortableColumnIds: ["document_code", "title", "document_type", "department", "status", "effective_date"],
  });

  useEffect(() => {
    const documentId = searchParams.get("document");
    if (documentId) {
      setSelectedId(documentId);
    }
  }, [searchParams]);

  const query = useQuery({
    queryKey: ["documents", "controlled", debouncedSearch, department, status, sort],
    queryFn: () =>
      fetchControlledDocuments({
        search: debouncedSearch || undefined,
        department: department || undefined,
        status: status || undefined,
        per_page: 50,
        sort,
      }),
  });

  const importMutation = useMutation({
    mutationFn: importControlledDocumentsCsv,
    onSuccess: (result) => {
      void queryClient.invalidateQueries({ queryKey: ["documents", "controlled"] });
      push({
        level: result.errors.length > 0 ? "warning" : "success",
        title: "Import complete",
        message: `${result.processed} imported, ${result.skipped} skipped, ${result.errors.length} errors.`,
      });
    },
    onError: (error) => {
      push({ level: "error", title: "Import failed", message: getErrorMessage(error) });
    },
  });

  const kpis = query.data?.kpis;
  const rows = query.data?.documents.data ?? [];

  const departments = useMemo(
    () =>
      [...new Set(rows.map((row) => row.department).filter((value): value is string => !!value?.trim()))].sort(),
    [rows],
  );

  const defaultFormId = useMemo(
    () => rows.find((row) => row.e_approval_form_id)?.e_approval_form_id ?? null,
    [rows],
  );

  const newRequestHref = defaultFormId
    ? controlledDocumentSubmissionUrl({ formId: defaultFormId, mode: "new" })
    : "/e-approval/submissions/new";

  const columns = useMemo(
    () => createControlledDocumentsTableColumns({ canCreate, onOpen: setSelectedId }),
    [canCreate],
  );

  return (
    <PermissionGate requiredPermissions={[permissions.documentsControlledView]}>
      <div className="space-y-6">
        <header className="flex flex-wrap items-start justify-between gap-4">
          <div className="max-w-2xl">
            <h1 className="text-2xl font-semibold tracking-tight text-foreground">
              Controlled Document Register
            </h1>
            <p className="mt-1 text-sm text-muted-foreground">
              ISO master list of approved documents. Start new requests here; submit revisions from each row — the
              E-Approval form stays focused on document content and authorization.
            </p>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            {canCreate ? (
              <Link href={newRequestHref} className={buttonVariants({ size: "sm" })}>
                <FilePlus2 className="mr-1.5 h-4 w-4" />
                New controlled document
              </Link>
            ) : null}
            {canImport ? (
              <>
                <input
                  ref={fileInputRef}
                  type="file"
                  accept=".csv,text/csv"
                  className="hidden"
                  onChange={(event) => {
                    const file = event.target.files?.[0];
                    if (file) {
                      importMutation.mutate(file);
                    }
                    event.target.value = "";
                  }}
                />
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  disabled={importMutation.isPending}
                  onClick={() => fileInputRef.current?.click()}
                >
                  Import CSV
                </Button>
              </>
            ) : null}
          </div>
        </header>

        <div className="grid gap-3 sm:grid-cols-3">
          <Metric label="Documents in register" value={kpis?.total ?? 0} />
          <Metric label="Published" value={kpis?.published ?? 0} />
          <Metric label="Obsolete" value={kpis?.obsolete ?? 0} />
        </div>

        {canManage ? <ControlledDocumentRegisterAccessCard /> : null}

        <div className="flex flex-wrap items-end gap-3 rounded-xl border border-border bg-card p-4 shadow-sm">
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
                placeholder="Document code or title"
                className="pl-9"
              />
            </div>
          </div>
          <div className="w-full min-w-[140px] sm:w-auto">
            <FilterSelect
              id="cdr-dept"
              label="Department"
              value={department}
              onChange={setDepartment}
            >
              <option value="">All departments</option>
              {departments.map((item) => (
                <option key={item} value={item}>
                  {item}
                </option>
              ))}
            </FilterSelect>
          </div>
          <div className="w-full min-w-[140px] sm:w-auto">
            <FilterSelect id="cdr-status" label="Status" value={status} onChange={setStatus}>
              <option value="">All statuses</option>
              <option value="published">Published</option>
              <option value="obsolete">Obsolete</option>
            </FilterSelect>
          </div>
        </div>

        <DataListCard>
          <RegistryDataTableView
            columns={columns}
            data={rows}
            getRowId={(row) => row.id}
            isLoading={query.isLoading}
            isEmpty={!query.isLoading && rows.length === 0}
            emptyMessage={
              canCreate
                ? "No controlled documents visible for your account. Authors only see documents from their approved E-Approval requests."
                : "No controlled documents yet."
            }
            enableColumnVisibility
            columnVisibilityStorageKey="toweros.table.columns.documents.controlled"
            sorting={sorting}
            onSortingChange={onSortingChange}
            manualSorting={manualSorting}
          />
        </DataListCard>

        {canImport ? (
          <p className="text-xs text-muted-foreground">
            CSV columns: document_code, title, document_type, department, revision_number, effective_date,
            change_summary
          </p>
        ) : null}
      </div>

      <ControlledDocumentDetailDrawer
        documentId={selectedId}
        canCreate={canCreate}
        canManage={canManage}
        onClose={() => setSelectedId(null)}
      />
    </PermissionGate>
  );
}

function Metric({ label, value }: { label: string; value: number }) {
  return (
    <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
      <p className="text-xs font-medium text-muted-foreground">{label}</p>
      <p className="mt-2 text-2xl font-semibold tabular-nums text-foreground">{value}</p>
    </div>
  );
}
