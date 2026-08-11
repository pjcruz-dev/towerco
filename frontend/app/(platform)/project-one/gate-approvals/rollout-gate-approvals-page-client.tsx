"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useEffect, useMemo, useState } from "react";

import { PaginatedListFooter } from "@/components/registry/paginated-list-footer";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { PermissionGate } from "@/components/layout/permission-gate";
import { createRolloutGateApprovalsTableColumns } from "@/components/project-one/rollout-gate-approvals-table-columns";
import { GateApprovalDelegationPanel } from "@/components/rollout/gate-approval-delegation-panel";
import { Button } from "@/components/ui/button";
import { useRolloutRealtime } from "@/hooks/use-rollout-realtime";
import { useServerTableSort } from "@/hooks/use-server-table-sort";
import { getErrorMessage } from "@/lib/api/error";
import {
  decideRolloutGateApproval,
  exportGateApprovalsCsv,
  fetchGateApprovalsAwaitingMeCount,
  fetchRolloutGateApprovals,
  GATE_APPROVALS_AWAITING_ME_COUNT_QUERY_KEY,
} from "@/lib/api/modules/rollout-api";
import { permissions } from "@/lib/rbac/permissions";
import { useNotificationStore } from "@/stores/notification-store";
import { cn } from "@/lib/utils";

const tabs = [
  { key: "awaiting_me", label: "Awaiting me" },
  { key: "in_review", label: "Pending" },
  { key: "approved", label: "Approved" },
  { key: "rejected", label: "Rejected" },
  { key: "all", label: "All" },
] as const;

type TabKey = (typeof tabs)[number]["key"];

const DEFAULT_SORT = "submitted_at:desc";
const GATE_COLUMN_TO_API: Record<string, string> = {
  waiting: "current_step_started_at",
};
const GATE_API_TO_COLUMN: Record<string, string> = {
  current_step_started_at: "waiting",
};

function resolveTabFromSearchParams(searchParams: URLSearchParams): TabKey {
  const tab = searchParams.get("tab");
  if (tab === "pending" || tab === "in_review") {
    return "in_review";
  }
  if (tab === "approved" || tab === "rejected" || tab === "all") {
    return tab;
  }
  if (searchParams.get("awaiting_me") === "0") {
    return "in_review";
  }

  return "awaiting_me";
}

export function RolloutGateApprovalsPageClient() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const queryClient = useQueryClient();
  const push = useNotificationStore((state) => state.push);
  useRolloutRealtime();

  const [status, setStatus] = useState<TabKey>(() => resolveTabFromSearchParams(searchParams));
  const [page, setPage] = useState(1);
  const { sort, sorting, onSortingChange, manualSorting } = useServerTableSort({
    defaultSort: DEFAULT_SORT,
    sortableColumnIds: ["status", "phase_key", "current_step", "submitted_at", "waiting"],
    columnIdToApiField: GATE_COLUMN_TO_API,
    apiFieldToColumnId: GATE_API_TO_COLUMN,
  });

  useEffect(() => {
    setStatus(resolveTabFromSearchParams(searchParams));
  }, [searchParams]);

  useEffect(() => {
    setPage(1);
  }, [sort]);

  const setActiveTab = (tab: TabKey) => {
    setStatus(tab);
    setPage(1);

    const params = new URLSearchParams();
    if (tab === "awaiting_me") {
      params.set("awaiting_me", "1");
    } else if (tab === "in_review") {
      params.set("tab", "pending");
    } else {
      params.set("tab", tab);
    }

    router.replace(`/project-one/gate-approvals?${params.toString()}`, { scroll: false });
  };

  const query = useQuery({
    queryKey: ["project-one", "gate-approvals", status, page, sort],
    queryFn: () =>
      fetchRolloutGateApprovals({
        status: status === "awaiting_me" ? "in_review" : status,
        awaiting_me: status === "awaiting_me" ? true : undefined,
        page,
        per_page: 25,
        sort,
      }),
    // Inbox stays fresh via mutation invalidation + rollout Echo; a short staleTime avoids
    // redundant refetches on quick remounts instead of the previous always-refetch.
    staleTime: 15_000,
  });

  const awaitingMeCountQuery = useQuery({
    queryKey: [...GATE_APPROVALS_AWAITING_ME_COUNT_QUERY_KEY],
    queryFn: fetchGateApprovalsAwaitingMeCount,
    staleTime: 30_000,
  });

  const decideMutation = useMutation({
    mutationFn: ({ id, decision }: { id: string; decision: "approve" | "reject" }) =>
      decideRolloutGateApproval(id, { decision }),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ["project-one", "gate-approvals"] });
      queryClient.invalidateQueries({ queryKey: [...GATE_APPROVALS_AWAITING_ME_COUNT_QUERY_KEY] });
      queryClient.invalidateQueries({ queryKey: ["project-one", "dashboard"] });
      queryClient.invalidateQueries({ queryKey: ["project-one", "rollouts"] });
      push({
        level: variables.decision === "approve" ? "success" : "warning",
        title: variables.decision === "approve" ? "Step approved" : "Rejected — gate stays pending",
      });
    },
    onError: (error) =>
      push({ level: "error", title: "Action failed", message: getErrorMessage(error) }),
  });

  const exportMutation = useMutation({
    mutationFn: () => exportGateApprovalsCsv(status === "awaiting_me" ? "in_review" : status),
    onSuccess: (blob) => {
      const url = URL.createObjectURL(blob);
      const anchor = document.createElement("a");
      anchor.href = url;
      anchor.download = `gate-approvals-${new Date().toISOString().slice(0, 10)}.csv`;
      anchor.click();
      URL.revokeObjectURL(url);
      push({ level: "success", title: "Export started", message: "CSV download should begin shortly." });
    },
    onError: (error) =>
      push({ level: "error", title: "Export failed", message: getErrorMessage(error) }),
  });

  const rows = query.data?.data ?? [];
  const meta = query.data?.meta;
  const awaitingMeCount =
    status === "awaiting_me" && meta?.total !== undefined
      ? meta.total
      : (awaitingMeCountQuery.data ?? 0);
  const awaitingMeCountLoading = awaitingMeCountQuery.isFetching && awaitingMeCountQuery.data === undefined;
  const inboxCountMismatch =
    status === "awaiting_me" && awaitingMeCount > 0 && rows.length === 0 && !query.isFetching;

  const columns = useMemo(
    () =>
      createRolloutGateApprovalsTableColumns({
        pending: decideMutation.isPending,
        onDecide: (id, decision) => decideMutation.mutate({ id, decision }),
      }),
    [decideMutation],
  );

  const awaitingMeEmptyContent = (
    <div className="mx-auto max-w-md space-y-2 px-4 py-10 text-left text-sm text-muted-foreground">
      {inboxCountMismatch ? (
        <p className="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs text-amber-950 dark:text-amber-100">
          The server reports <strong>{awaitingMeCount}</strong> item
          {awaitingMeCount === 1 ? "" : "s"} for you, but this page did not load them. Click{" "}
          <span className="font-medium">Refresh</span> or open the rollout Timeline and use{" "}
          <span className="font-medium">Approve step</span>.
        </p>
      ) : null}
      <p className="font-medium text-foreground">Nothing awaiting your approval right now.</p>
      <ul className="list-inside list-disc space-y-1 text-xs">
        <li>
          If the dashboard still shows a count, click <span className="font-medium">Refresh</span>{" "}
          above — the queue updates after login or after acting on the rollout Timeline.
        </li>
        <li>
          Check the <span className="font-medium">Pending</span> tab for all in-review requests (you
          may be waiting on an earlier chain step, e.g. SAQ before PMO).
        </li>
        <li>
          On the rollout <span className="font-medium">Timeline</span>, use{" "}
          <span className="font-medium">Approve step</span> when it appears — same action as this
          inbox.
        </li>
        <li>
          Confirm you are <span className="font-medium">SAQ / PMO / CME owner</span> on that rollout
          (Edit rollout metadata) for the current chain step.
        </li>
      </ul>
    </div>
  );

  return (
    <PermissionGate requiredPermissions={[permissions.rolloutView]}>
      <div className="space-y-5">
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight text-foreground">Gate approvals</h1>
            <p className="mt-1 text-sm text-muted-foreground">
              Multi-step rollout timeline gate approvals with email notifications and escalation reminders.
            </p>
            <p className="mt-2 text-xs font-medium">
              <Link className="text-primary underline-offset-4 hover:underline" href="/project-one/approvals">
                General approvals
              </Link>
              {" · "}
              <Link className="text-primary underline-offset-4 hover:underline" href="/project-one/rollout-playbook">
                Playbook settings
              </Link>
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <Button
              type="button"
              size="sm"
              variant="outline"
              disabled={query.isFetching}
              onClick={() => {
                void query.refetch();
                void awaitingMeCountQuery.refetch();
                void queryClient.invalidateQueries({ queryKey: ["project-one", "dashboard"] });
              }}
            >
              Refresh
            </Button>
            <Button
              type="button"
              size="sm"
              variant="outline"
              disabled={exportMutation.isPending}
              onClick={() => exportMutation.mutate()}
            >
              Export CSV
            </Button>
          </div>
        </header>

        <div className="flex flex-wrap gap-1">
          {tabs.map((tab) => (
            <button
              key={tab.key}
              type="button"
              onClick={() => setActiveTab(tab.key)}
              className={`min-h-11 touch-manipulation rounded-md px-3 py-2.5 text-sm font-medium sm:min-h-0 sm:py-1.5 sm:text-xs ${
                status === tab.key
                  ? "bg-primary text-primary-foreground"
                  : "bg-muted text-muted-foreground hover:text-foreground"
              }`}
            >
              <span className="inline-flex items-center gap-1.5">
                {tab.label}
                {tab.key === "awaiting_me" ? (
                  <AwaitingMeCountBadge
                    count={awaitingMeCount}
                    loading={awaitingMeCountLoading}
                    active={status === tab.key}
                  />
                ) : null}
              </span>
            </button>
          ))}
        </div>

        <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
          <RegistryDataTableView
            columns={columns}
            data={rows}
            getRowId={(row) => row.id}
            isLoading={query.isFetching && rows.length === 0}
            isEmpty={rows.length === 0 && !query.isFetching}
            emptyMessage="No gate approvals in this queue."
            emptyContent={status === "awaiting_me" ? awaitingMeEmptyContent : undefined}
            enableColumnVisibility
            columnVisibilityStorageKey="toweros.table.columns.project-one.gate-approvals"
            sorting={sorting}
            onSortingChange={onSortingChange}
            manualSorting={manualSorting}
          />
          {meta ? <PaginatedListFooter meta={meta} onPageChange={setPage} isPending={query.isFetching} /> : null}
        </div>

        <GateApprovalDelegationPanel />
      </div>
    </PermissionGate>
  );
}

function AwaitingMeCountBadge({
  count,
  loading,
  active,
}: {
  count: number;
  loading: boolean;
  active: boolean;
}) {
  if (loading) {
    return (
      <span
        className={cn(
          "inline-flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px]",
          active ? "bg-primary-foreground/20 text-primary-foreground" : "bg-muted-foreground/30",
        )}
        aria-hidden
      >
        …
      </span>
    );
  }

  if (count <= 0) {
    return null;
  }

  return (
    <span
      className={cn(
        "inline-flex h-4 min-w-4 items-center justify-center rounded-full px-1.5 text-[10px] font-semibold tabular-nums",
        active ? "bg-primary-foreground text-primary" : "bg-red-600 text-white dark:bg-red-500",
      )}
      aria-label={`${count} awaiting your approval`}
    >
      {count > 99 ? "99+" : count}
    </span>
  );
}
