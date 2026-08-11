"use client";

import Link from "next/link";
import { Download } from "lucide-react";
import { useSearchParams } from "next/navigation";
import { Suspense, useCallback, useMemo, useEffect, useState } from "react";
import type { OnChangeFn, RowSelectionState, SortingState } from "@tanstack/react-table";

import { FilterSelect } from "@/components/forms/filter-select";
import { AcronymLabel } from "@/components/help/acronym-label";
import { ListEmptyState } from "@/components/project-one/list-empty-state";
import {
  applyRowSelectionToSelectedIds,
  flattenRolloutTableRows,
  selectedIdsToRowSelection,
  useRolloutsTableColumns,
} from "@/components/project-one/rollouts-table-columns";
import { RolloutBulkEditSheet } from "@/components/rollout/rollout-bulk-edit-sheet";
import { RolloutBulkPhaseDatesSheet } from "@/components/rollout/rollout-bulk-phase-dates-sheet";
import { PaginatedListFooter } from "@/components/registry/paginated-list-footer";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { RegistryListToolbar } from "@/components/registry/registry-list-toolbar";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button, buttonVariants } from "@/components/ui/button";
import { RefreshingHint } from "@/components/ui/refreshing-hint";
import { DEFAULT_FILTERS, useRolloutsIndex, type RolloutIndexFilters } from "@/hooks/use-rollouts-index";
import { useRolloutBulkSelection, isRolloutBulkSelectable } from "@/hooks/use-rollout-bulk-selection";
import { useRolloutRealtime } from "@/hooks/use-rollout-realtime";
import { usePermission } from "@/hooks/use-permission";
import { exportRolloutsCsv } from "@/lib/api/modules/rollout-api";
import { getErrorMessage } from "@/lib/api/error";
import { permissions } from "@/lib/rbac/permissions";
import type { RolloutListRow } from "@/modules/rollout/types";
import { useNotificationStore } from "@/stores/notification-store";

function RolloutsPageContent() {
  const searchParams = useSearchParams();
  const canManage = usePermission([permissions.rolloutManage]);
  const push = useNotificationStore((state) => state.push);
  const [search, setSearch] = useState("");
  const [filters, setFilters] = useState<RolloutIndexFilters>(() => ({
    ...DEFAULT_FILTERS,
    sla_at_risk: searchParams.get("sla_at_risk") === "1",
  }));
  const [exporting, setExporting] = useState(false);
  const [bulkEditOpen, setBulkEditOpen] = useState(false);
  const [bulkPhaseDatesOpen, setBulkPhaseDatesOpen] = useState(false);
  const [expandedBatchIds, setExpandedBatchIds] = useState<Set<string>>(() => new Set());
  const { setPage, params, query } = useRolloutsIndex(search, filters);
  useRolloutRealtime();
  const { data, isFetching, isError, refetch } = query;
  const rows = data?.data ?? [];
  const meta = data?.meta;
  const bulk = useRolloutBulkSelection(rows);

  useEffect(() => {
    const slaRisk = searchParams.get("sla_at_risk") === "1";
    setFilters((current) =>
      current.sla_at_risk === slaRisk ? current : { ...current, sla_at_risk: slaRisk },
    );
  }, [searchParams]);

  function updateFilter<K extends keyof RolloutIndexFilters>(key: K, value: RolloutIndexFilters[K]) {
    setFilters((current) => ({ ...current, [key]: value }));
    setPage(1);
  }

  const toggleBatchExpanded = useCallback((batchId: string) => {
    setExpandedBatchIds((current) => {
      const next = new Set(current);
      if (next.has(batchId)) {
        next.delete(batchId);
      } else {
        next.add(batchId);
      }
      return next;
    });
  }, []);

  const displayRows = useMemo(
    () => flattenRolloutTableRows(rows, expandedBatchIds),
    [rows, expandedBatchIds],
  );

  const columns = useRolloutsTableColumns({
    canManage,
    expandedBatchIds,
    onToggleBatch: toggleBatchExpanded,
  });

  const rowSelection = useMemo(
    () => selectedIdsToRowSelection(bulk.selectedIds),
    [bulk.selectedIds],
  );

  const sorting = useMemo((): SortingState => {
    const [field, direction] = filters.sort.split(":");
    if (field === "rollout_ref") {
      return [{ id: "reference", desc: direction === "desc" }];
    }
    if (field === "target_rfi_working_date") {
      return [{ id: "target_rfi", desc: direction === "desc" }];
    }
    return [];
  }, [filters.sort]);

  const onSortingChange = useCallback<OnChangeFn<SortingState>>(
    (updater) => {
      const next = typeof updater === "function" ? updater(sorting) : updater;
      const first = next[0];
      if (!first) {
        updateFilter("sort", "created_at:desc");
        return;
      }
      if (first.id === "reference") {
        updateFilter("sort", first.desc ? "rollout_ref:desc" : "rollout_ref:asc");
        return;
      }
      if (first.id === "target_rfi") {
        updateFilter("sort", first.desc ? "target_rfi_working_date:desc" : "target_rfi_working_date:asc");
        return;
      }
      updateFilter("sort", "created_at:desc");
    },
    [sorting],
  );

  const onRowSelectionChange = useCallback<OnChangeFn<RowSelectionState>>(
    (updater) => {
      applyRowSelectionToSelectedIds(updater, bulk.selectedIds, bulk.replaceSelection);
    },
    [bulk.selectedIds, bulk.replaceSelection],
  );

  async function handleExport() {
    setExporting(true);
    try {
      const blob = await exportRolloutsCsv(params);
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = `rollouts-full-${new Date().toISOString().slice(0, 10)}.csv`;
      link.click();
      URL.revokeObjectURL(url);
    } catch (error) {
      push({
        level: "error",
        title: "Export failed",
        message: getErrorMessage(error) || "Unable to download rollout CSV.",
      });
    } finally {
      setExporting(false);
    }
  }

  const isEmpty = rows.length === 0 && !isFetching;

  return (
    <PermissionGate requiredPermissions={[permissions.rolloutView]}>
      <div className="space-y-5">
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight text-foreground">Rollouts</h1>
            <p className="mt-1 text-sm text-muted-foreground">
              Registry of rollout programs — search, filter, export, and manage lifecycle.
            </p>
            <p className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs font-medium">
              <Link className="text-primary underline-offset-4 hover:underline" href="/project-one">
                Dashboard
              </Link>
              <Link className="text-primary underline-offset-4 hover:underline" href="/project-one/rollout-playbook">
                Playbook settings
              </Link>
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <Button size="sm" variant="outline" disabled={exporting} onClick={handleExport}>
              <Download className="mr-1.5 h-3.5 w-3.5" />
              {exporting ? "Exporting…" : "Export full CSV"}
            </Button>
            <Button size="sm" variant="outline" onClick={() => refetch()}>
              Refresh
            </Button>
            {canManage ? (
              <Link href="/project-one/rollouts/new" className={buttonVariants({ size: "sm" })}>
                New rollout
              </Link>
            ) : null}
          </div>
        </header>

        <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
          <RegistryListToolbar
            label="Filter"
            value={search}
            onChange={setSearch}
            placeholder="Reference, search ring, MNO, TCO ID"
          />
          <div className="flex flex-wrap gap-3 border-b border-border px-4 py-3">
            <FilterSelect
              id="rollout-filter-status"
              label="Status"
              touchFriendly
              value={filters.status}
              onChange={(value) => updateFilter("status", value)}
            >
              <option value="all">All statuses</option>
              <option value="draft">Draft</option>
              <option value="saq">Site acquisition (SAQ)</option>
              <option value="permitting">Permitting</option>
              <option value="construction">Construction</option>
              <option value="completed">Completed</option>
              <option value="cancelled">Cancelled</option>
            </FilterSelect>
            <FilterSelect
              id="rollout-filter-mno"
              label={<AcronymLabel term="MNO" />}
              touchFriendly
              value={filters.mno}
              onChange={(value) => updateFilter("mno", value)}
            >
              <option value="all">All MNOs</option>
              <option value="globe">Globe</option>
              <option value="smart">Smart</option>
              <option value="dito">DITO</option>
            </FilterSelect>
            <FilterSelect
              id="rollout-filter-project-type"
              label="Project type"
              touchFriendly
              value={filters.project_type}
              onChange={(value) => updateFilter("project_type", value)}
            >
              <option value="all">All types</option>
              <option value="bts">BTS</option>
              <option value="ibs">IBS</option>
              <option value="colo">Colo</option>
            </FilterSelect>
            <FilterSelect
              id="rollout-filter-region"
              label={<AcronymLabel term="NCR">Region</AcronymLabel>}
              touchFriendly
              value={filters.region}
              onChange={(value) => updateFilter("region", value)}
            >
              <option value="all">All regions</option>
              <option value="ncr">NCR</option>
              <option value="visayas">Visayas</option>
              <option value="mindanao">Mindanao</option>
              <option value="luzon">Luzon</option>
            </FilterSelect>
            <FilterSelect
              id="rollout-filter-sort"
              label="Sort"
              touchFriendly
              value={filters.sort}
              onChange={(value) => updateFilter("sort", value)}
            >
              <option value="created_at:desc">Newest first</option>
              <option value="created_at:asc">Oldest first</option>
              <option value="rollout_ref:asc">Reference A–Z</option>
              <option value="target_rfi_working_date:asc">Target RFI (soonest)</option>
            </FilterSelect>
          </div>
          <RegistryDataTableView
            columns={columns}
            data={displayRows}
            getRowId={(row) => row.id}
            isLoading={isFetching && rows.length === 0}
            isEmpty={isEmpty}
            emptyContent={
              <ListEmptyState
                title={filters.sla_at_risk ? "No rollouts at SLA risk" : "No rollouts yet"}
                description={
                  filters.sla_at_risk
                    ? "All open programs are on track against target RFI, or adjust filters to see more."
                    : "Create a rollout program to start site acquisition, permitting, and delivery tracking."
                }
                actionHref={canManage ? "/project-one/rollouts/new" : undefined}
                actionLabel={canManage ? "Create rollout" : undefined}
                secondaryHref="/project-one/rollout-playbook"
                secondaryLabel="View playbook"
              />
            }
            getRowClassName={(row) => (row.original.kind === "child" ? "bg-muted/20" : undefined)}
            rowSelection={canManage ? rowSelection : undefined}
            onRowSelectionChange={canManage ? onRowSelectionChange : undefined}
            sorting={sorting}
            onSortingChange={onSortingChange}
            manualSorting
            enableColumnVisibility
            columnVisibilityStorageKey="toweros.table.columns.project-one.rollouts"
            enableRowSelection={
              canManage
                ? (row) =>
                    row.original.kind === "parent" &&
                    isRolloutBulkSelectable(row.original.row as RolloutListRow)
                : false
            }
            scrollClassName="max-h-none"
          />
          {meta ? <PaginatedListFooter meta={meta} onPageChange={setPage} isPending={isFetching} /> : null}
        </div>

        {canManage && bulk.selectedCount > 0 ? (
          <div className="sticky bottom-4 z-20 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-card px-4 py-3 shadow-md">
            <p className="text-sm text-foreground">
              <span className="font-medium">{bulk.selectedCount}</span> selected
            </p>
            <div className="flex flex-wrap gap-2">
              <Button type="button" size="sm" variant="outline" onClick={bulk.clear}>
                Clear
              </Button>
              <Button type="button" size="sm" variant="outline" onClick={() => setBulkEditOpen(true)}>
                Bulk edit metadata
              </Button>
              <Button type="button" size="sm" onClick={() => setBulkPhaseDatesOpen(true)}>
                Mass update dates
              </Button>
            </div>
          </div>
        ) : null}

        {canManage ? (
          <>
            <RolloutBulkEditSheet
              open={bulkEditOpen}
              onOpenChange={setBulkEditOpen}
              selectedIds={[...bulk.selectedIds]}
              selectedRows={bulk.selectedRows}
              onSuccess={bulk.clear}
            />
            <RolloutBulkPhaseDatesSheet
              open={bulkPhaseDatesOpen}
              onOpenChange={setBulkPhaseDatesOpen}
              selectedIds={[...bulk.selectedIds]}
              selectedRows={bulk.selectedRows}
              onSuccess={bulk.clear}
            />
          </>
        ) : null}

        {isFetching ? <RefreshingHint label="Loading rollouts" /> : null}
        {isError ? (
          <p className="text-xs text-red-600 dark:text-red-400">Unable to load rollouts. Check API connectivity.</p>
        ) : null}
      </div>
    </PermissionGate>
  );
}

export function RolloutsPageClient() {
  return (
    <Suspense fallback={<p className="p-6 text-sm text-muted-foreground">Loading rollouts…</p>}>
      <RolloutsPageContent />
    </Suspense>
  );
}
