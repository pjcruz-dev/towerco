"use client";

import { useQuery } from "@tanstack/react-query";
import { Download, ScrollText } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { useSearchParams } from "next/navigation";

import { WorkspaceAuditDetailDrawer } from "@/components/governance/workspace-audit-detail-drawer";
import { PermissionGate } from "@/components/layout/permission-gate";
import { PaginatedListFooter } from "@/components/registry/paginated-list-footer";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { createWorkspaceAuditTableColumns } from "@/components/registry/workspace-audit-table-columns";
import { Button } from "@/components/ui/button";
import { DatePicker } from "@/components/ui/date-picker";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { useServerTableSort } from "@/hooks/use-server-table-sort";
import { getErrorMessage } from "@/lib/api/error";
import {
  exportWorkspaceAuditCsv,
  fetchWorkspaceAuditIndex,
  type WorkspaceAuditRow,
} from "@/lib/api/modules/workspace-audit-api";
import { permissions } from "@/lib/rbac/permissions";
import { useNotificationStore } from "@/stores/notification-store";

const PER_PAGE = 50;
const DEFAULT_SORT = "created_at:desc";

function saveBlob(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  anchor.href = url;
  anchor.download = filename;
  anchor.click();
  URL.revokeObjectURL(url);
}

export function WorkspaceAuditPageClient() {
  const notify = useNotificationStore((state) => state.push);
  const searchParams = useSearchParams();
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const [actor, setActor] = useState("");
  const [module, setModule] = useState("");
  const [category, setCategory] = useState("");
  const [severity, setSeverity] = useState("");
  const [actionFamily, setActionFamily] = useState("");
  const [entityType, setEntityType] = useState(searchParams.get("entity_type") ?? "");
  const [entityId, setEntityId] = useState(searchParams.get("entity_id") ?? "");
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [appliedFrom, setAppliedFrom] = useState("");
  const [appliedTo, setAppliedTo] = useState("");
  const [exporting, setExporting] = useState(false);
  const [selectedRow, setSelectedRow] = useState<WorkspaceAuditRow | null>(null);
  const [detailOpen, setDetailOpen] = useState(false);

  const debouncedSearch = useDebouncedValue(search, 350, () => setPage(1));
  const debouncedActor = useDebouncedValue(actor, 350, () => setPage(1));
  const debouncedActionFamily = useDebouncedValue(actionFamily, 350, () => setPage(1));
  const { sort, sorting, onSortingChange, manualSorting } = useServerTableSort({
    defaultSort: DEFAULT_SORT,
    sortableColumnIds: ["created_at", "module", "action", "category", "severity"],
  });

  useEffect(() => {
    setPage(1);
  }, [sort, appliedFrom, appliedTo, module, category, severity, entityType, entityId]);

  const columns = useMemo(
    () =>
      createWorkspaceAuditTableColumns({
        onOpen: (row) => {
          setSelectedRow(row);
          setDetailOpen(true);
        },
      }),
    [],
  );

  const filterParams = {
    search: debouncedSearch.trim() || undefined,
    actor: debouncedActor.trim() || undefined,
    module: module || undefined,
    category: category || undefined,
    severity: severity || undefined,
    action_family: debouncedActionFamily.trim() || undefined,
    entity_type: entityType.trim() || undefined,
    entity_id: entityId.trim() || undefined,
    from: appliedFrom || undefined,
    to: appliedTo || undefined,
  };

  const { data, isFetching, isError } = useQuery({
    queryKey: ["workspace", "audit", page, sort, filterParams],
    queryFn: () =>
      fetchWorkspaceAuditIndex({
        page,
        per_page: PER_PAGE,
        sort,
        ...filterParams,
      }),
  });

  const rows = data?.data ?? [];
  const meta = data?.meta;

  const applyDateRange = () => {
    setAppliedFrom(from);
    setAppliedTo(to);
    setPage(1);
  };

  const clearDateRange = () => {
    setFrom("");
    setTo("");
    setAppliedFrom("");
    setAppliedTo("");
    setPage(1);
  };

  const handleExport = async () => {
    setExporting(true);
    try {
      const blob = await exportWorkspaceAuditCsv(filterParams);
      saveBlob(blob, `workspace-audit-${new Date().toISOString().slice(0, 10)}.csv`);
      notify({ level: "success", title: "Export ready", message: "Audit CSV downloaded." });
    } catch (error) {
      notify({
        level: "error",
        title: "Export failed",
        message: getErrorMessage(error) || "Unable to download audit CSV.",
      });
    } finally {
      setExporting(false);
    }
  };

  return (
    <PermissionGate requiredPermissions={[permissions.workspaceAuditView]}>
      <div className="space-y-6">
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight text-foreground">Workspace audit trail</h1>
            <p className="mt-1 text-sm text-muted-foreground">
              Who did what, what changed, severity, and reason — across modules.
            </p>
          </div>
          <Button type="button" variant="outline" size="sm" onClick={() => void handleExport()} disabled={exporting}>
            <Download className="mr-1.5 h-4 w-4" />
            {exporting ? "Exporting…" : "Export CSV"}
          </Button>
        </header>

        <div className="flex flex-wrap items-end gap-3 rounded-xl border border-border bg-card p-4 shadow-sm">
          <div className="min-w-[180px] flex-1 space-y-1.5">
            <Label htmlFor="audit-search" className="text-xs font-medium text-muted-foreground">
              Search
            </Label>
            <Input
              id="audit-search"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Action, entity, reason…"
            />
          </div>
          <div className="min-w-[160px] flex-1 space-y-1.5 sm:max-w-[200px]">
            <Label htmlFor="audit-actor" className="text-xs font-medium text-muted-foreground">
              Actor
            </Label>
            <Input
              id="audit-actor"
              value={actor}
              onChange={(event) => setActor(event.target.value)}
              placeholder="Name or email…"
            />
          </div>
          <div className="w-full min-w-[140px] space-y-1.5 sm:w-auto">
            <Label htmlFor="audit-module" className="text-xs font-medium text-muted-foreground">
              Module
            </Label>
            <Select id="audit-module" value={module} onChange={(event) => setModule(event.target.value)}>
              <option value="">All modules</option>
              <option value="e_approval">E-Approval</option>
              <option value="documents">Documents</option>
              <option value="team_access">Team &amp; access</option>
              <option value="procurement_one">Procurement</option>
              <option value="project_one">Project-One</option>
              <option value="ticketing">Ticketing</option>
              <option value="ai_assistant">AI Assistant</option>
            </Select>
          </div>
          <div className="w-full min-w-[140px] space-y-1.5 sm:w-auto">
            <Label htmlFor="audit-category" className="text-xs font-medium text-muted-foreground">
              Category
            </Label>
            <Select id="audit-category" value={category} onChange={(event) => setCategory(event.target.value)}>
              <option value="">All categories</option>
              <option value="security">Security</option>
              <option value="access">Access</option>
              <option value="data_change">Data change</option>
              <option value="lifecycle">Lifecycle</option>
            </Select>
          </div>
          <div className="w-full min-w-[140px] space-y-1.5 sm:w-auto">
            <Label htmlFor="audit-severity" className="text-xs font-medium text-muted-foreground">
              Severity
            </Label>
            <Select id="audit-severity" value={severity} onChange={(event) => setSeverity(event.target.value)}>
              <option value="">All severities</option>
              <option value="critical">Critical</option>
              <option value="high">High</option>
              <option value="medium">Medium</option>
              <option value="low">Low</option>
            </Select>
          </div>
          <div className="min-w-[140px] space-y-1.5 sm:max-w-[160px]">
            <Label htmlFor="audit-family" className="text-xs font-medium text-muted-foreground">
              Action family
            </Label>
            <Input
              id="audit-family"
              value={actionFamily}
              onChange={(event) => setActionFamily(event.target.value)}
              placeholder="auth, ticket, rbac…"
            />
          </div>
          <div className="min-w-[120px] space-y-1.5 sm:max-w-[140px]">
            <Label htmlFor="audit-entity-type" className="text-xs font-medium text-muted-foreground">
              Entity type
            </Label>
            <Input
              id="audit-entity-type"
              value={entityType}
              onChange={(event) => setEntityType(event.target.value)}
              placeholder="ticket, form…"
            />
          </div>
          <div className="min-w-[140px] flex-1 space-y-1.5 sm:max-w-[200px]">
            <Label htmlFor="audit-entity-id" className="text-xs font-medium text-muted-foreground">
              Entity ID
            </Label>
            <Input
              id="audit-entity-id"
              value={entityId}
              onChange={(event) => setEntityId(event.target.value)}
              placeholder="UUID…"
            />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="audit-from" className="text-xs font-medium text-muted-foreground">
              From
            </Label>
            <DatePicker id="audit-from" value={from} onChange={setFrom} className="h-9 w-[148px]" />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="audit-to" className="text-xs font-medium text-muted-foreground">
              To
            </Label>
            <DatePicker id="audit-to" value={to} onChange={setTo} className="h-9 w-[148px]" />
          </div>
          <div className="flex flex-wrap gap-2">
            <Button type="button" size="sm" onClick={applyDateRange}>
              Apply dates
            </Button>
            {appliedFrom || appliedTo ? (
              <Button type="button" size="sm" variant="ghost" onClick={clearDateRange}>
                Clear dates
              </Button>
            ) : null}
          </div>
        </div>

        <section className="rounded-xl border border-border bg-card shadow-sm">
          <div className="flex items-center gap-2 border-b border-border px-4 py-3">
            <ScrollText className="h-4 w-4 text-muted-foreground" />
            <h2 className="text-base font-medium">Activity log</h2>
          </div>

          <RegistryDataTableView
            columns={columns}
            data={rows}
            getRowId={(row) => row.id}
            isLoading={isFetching && rows.length === 0}
            isEmpty={!isFetching && (isError || rows.length === 0)}
            emptyMessage={isError ? "Could not load audit trail." : "No audit events match your filters."}
            enableColumnVisibility
            columnVisibilityStorageKey="toweros.table.columns.governance.audit"
            sorting={sorting}
            onSortingChange={onSortingChange}
            manualSorting={manualSorting}
            onRowClick={(tableRow) => {
              setSelectedRow(tableRow.original);
              setDetailOpen(true);
            }}
            getRowClassName={() => "cursor-pointer"}
          />

          {meta ? (
            <PaginatedListFooter meta={meta} onPageChange={setPage} isPending={isFetching} />
          ) : null}
        </section>

        <WorkspaceAuditDetailDrawer
          row={selectedRow}
          open={detailOpen}
          onOpenChange={(open) => {
            setDetailOpen(open);
            if (!open) {
              setSelectedRow(null);
            }
          }}
        />
      </div>
    </PermissionGate>
  );
}
