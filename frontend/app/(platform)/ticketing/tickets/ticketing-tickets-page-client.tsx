"use client";

import Link from "next/link";
import { useMemo, useEffect, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { useSearchParams } from "next/navigation";
import { Plus, RefreshCw } from "lucide-react";

import { FilterSelect } from "@/components/forms/filter-select";
import { TicketingHelpEntryActions } from "@/components/help/ticketing-help-entry-actions";
import { LiveProductTourHost } from "@/components/help/live-product-tour-host";
import { TicketingTourSampleNotice } from "@/components/help/ticketing-tour-fixtures";
import { TicketingPageHeader } from "@/components/ticketing/ticketing-page-header";
import { ticketingTicketsTableColumns, createTicketingTicketsTableColumns } from "@/components/ticketing/ticketing-tickets-table-columns";
import { PaginatedListFooter } from "@/components/registry/paginated-list-footer";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { DataListCard } from "@/components/ui/data-list-card";
import { Input } from "@/components/ui/input";
import { Spinner } from "@/components/ui/spinner";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { useServerTableSort } from "@/hooks/use-server-table-sort";
import { fetchTicketingMetadata, fetchTicketingTickets } from "@/lib/api/modules/ticketing-api";
import {
  TICKETING_TOUR_SAMPLE_TICKET_ID,
  isTicketingTourActive,
  ticketingTourSampleListRow,
} from "@/lib/help/ticketing-tour-fixtures";
import { permissions } from "@/lib/rbac/permissions";

const DEFAULT_SORT = "updated_at:desc";

export function TicketingTicketsPageClient() {
  const searchParams = useSearchParams();
  const tourActive = isTicketingTourActive(searchParams);
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("");
  const [category, setCategory] = useState("");
  const [mineOnly, setMineOnly] = useState(false);
  const [assignedMe, setAssignedMe] = useState(false);
  const [page, setPage] = useState(1);
  const debouncedSearch = useDebouncedValue(search, 350, () => setPage(1));
  const { sort, sorting, onSortingChange, manualSorting } = useServerTableSort({
    defaultSort: DEFAULT_SORT,
    sortableColumnIds: ["ticket_number", "title", "status", "priority", "updated_at"],
  });

  useEffect(() => {
    if (searchParams.get("assigned_me") === "1") {
      setAssignedMe(true);
      setMineOnly(false);
    }
  }, [searchParams]);

  useEffect(() => {
    setPage(1);
  }, [sort]);

  const { data: metadata } = useQuery({
    queryKey: ["ticketing", "metadata"],
    queryFn: fetchTicketingMetadata,
    staleTime: 300_000,
  });

  const query = useQuery({
    queryKey: [
      "ticketing",
      "tickets",
      { search: debouncedSearch, status, category, mineOnly, assignedMe, page, sort },
    ],
    queryFn: () =>
      fetchTicketingTickets({
        search: debouncedSearch,
        status: status || undefined,
        category: category || undefined,
        mine: mineOnly || undefined,
        assigned_me: assignedMe || undefined,
        page,
        per_page: 20,
        sort,
      }),
  });

  const tickets = useMemo(() => {
    const rows = query.data?.data ?? [];
    if (!tourActive) {
      return rows;
    }
    if (rows.some((row) => row.id === TICKETING_TOUR_SAMPLE_TICKET_ID)) {
      return rows;
    }
    return [ticketingTourSampleListRow, ...rows];
  }, [query.data?.data, tourActive]);
  const columns = useMemo(
    () =>
      tourActive
        ? createTicketingTicketsTableColumns({ tourQuery: searchParams.toString() })
        : ticketingTicketsTableColumns,
    [searchParams, tourActive],
  );
  const meta = query.data?.meta;
  const statusOptions = useMemo(() => metadata?.statuses ?? [], [metadata]);
  const categoryOptions = useMemo(
    () => metadata?.category_options ?? (metadata?.categories ?? []).map((id) => ({ id, label: id })),
    [metadata],
  );
  const emptyMessage =
    debouncedSearch || status || category || mineOnly || assignedMe
      ? "No tickets match your filters."
      : "No tickets yet.";

  return (
    <PermissionGate requiredPermissions={[permissions.ticketingView]}>
      <div className="space-y-6">
        <LiveProductTourHost />
        <TicketingPageHeader
          title="Tickets"
          description="Operational issue queue for your workspace."
          actions={
            <div className="flex flex-wrap items-center gap-2">
              <TicketingHelpEntryActions />
              <Button
                size="sm"
                data-help="tk-tickets-new"
                data-tour-nav="/ticketing/tickets/new"
                render={<Link href="/ticketing/tickets/new" />}
              >
                <Plus className="mr-1.5 h-4 w-4" aria-hidden />
                New ticket
              </Button>
            </div>
          }
        />

        <div
          data-help="tk-tickets-filters"
          className="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 shadow-sm lg:flex-row lg:items-end"
        >          <Input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search by title, description, or ticket #…"
            className="max-w-md"
          />
          <FilterSelect
            id="ticketing-status"
            label="Status"
            value={status}
            onChange={(value) => {
              setStatus(value);
              setPage(1);
            }}
            className="w-full min-w-[10rem] sm:w-auto"
          >
            <option value="">All statuses</option>
            {statusOptions.map((item) => (
              <option key={item} value={item}>
                {item.replace(/_/g, " ")}
              </option>
            ))}
          </FilterSelect>
          <FilterSelect
            id="ticketing-category"
            label="Category"
            value={category}
            onChange={(value) => {
              setCategory(value);
              setPage(1);
            }}
            className="w-full min-w-[12rem] sm:w-auto"
          >
            <option value="">All categories</option>
            {categoryOptions.map((item) => (
              <option key={item.id} value={item.id}>
                {item.label}
              </option>
            ))}
          </FilterSelect>
          <label className="inline-flex h-8 items-center gap-2 rounded-lg border border-input bg-background px-3 text-sm">
            <Checkbox
              checked={mineOnly}
              onCheckedChange={(v) => {
                const checked = v === true;
                setMineOnly(checked);
                if (checked) setAssignedMe(false);
                setPage(1);
              }}
            />
            My tickets
          </label>
          <label className="inline-flex h-8 items-center gap-2 rounded-lg border border-input bg-background px-3 text-sm">
            <Checkbox
              checked={assignedMe}
              onCheckedChange={(v) => {
                const checked = v === true;
                setAssignedMe(checked);
                if (checked) setMineOnly(false);
                setPage(1);
              }}
            />
            Assigned to me
          </label>
          <Button
            size="sm"
            variant="outline"
            type="button"
            onClick={() => query.refetch()}
            disabled={query.isFetching}
          >
            {query.isFetching ? <Spinner className="size-4" /> : <RefreshCw className="h-4 w-4" aria-hidden />}
          </Button>
        </div>

        {query.isError ? (
          <div className="rounded-xl border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm">
            <p className="font-medium text-destructive">Could not load tickets.</p>
            <Button size="sm" variant="outline" type="button" className="mt-2" onClick={() => query.refetch()}>
              Retry
            </Button>
          </div>
        ) : null}

        {!query.isError ? (
          <div data-help="tk-tickets-table">
            <DataListCard>
              {tourActive ? (
                <div className="border-b border-border px-4 pt-4">
                  <TicketingTourSampleNotice />
                </div>
              ) : null}
              <RegistryDataTableView
                columns={columns}
                data={tickets}
                getRowId={(row) => row.id}
                isLoading={query.isLoading || (query.isFetching && tickets.length === 0)}
                isEmpty={!query.isLoading && tickets.length === 0}
                emptyMessage={emptyMessage}
                enableColumnVisibility
                columnVisibilityStorageKey="toweros.table.columns.ticketing.tickets"
                sorting={sorting}
                onSortingChange={onSortingChange}
                manualSorting={manualSorting}
              />
              {meta && meta.last_page > 1 ? (
                <PaginatedListFooter meta={meta} onPageChange={setPage} isPending={query.isFetching} />
              ) : null}
            </DataListCard>
          </div>
        ) : null}
      </div>
    </PermissionGate>
  );
}
