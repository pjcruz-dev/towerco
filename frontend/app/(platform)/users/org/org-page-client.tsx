"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import axios from "axios";
import Link from "next/link";
import { RefreshCw } from "lucide-react";
import { useEffect, useMemo, useState } from "react";

import { AdminOrgChartView } from "@/components/admin/admin-org-chart-view";
import { AdminOrgTreeView } from "@/components/admin/admin-org-tree-view";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button, buttonVariants } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Spinner } from "@/components/ui/spinner";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
  buildOrgChartIndex,
  filterOrgPeople,
  pickDefaultFocus,
} from "@/lib/admin/org-chart";
import { useOrganizationLabel } from "@/hooks/use-organization-label";
import { formatTimestamp } from "@/lib/admin/user-display";
import { getErrorMessage } from "@/lib/api/error";
import { fetchAdminOrgChart, syncAdminEntraOrg } from "@/lib/api/modules/admin-users-api";
import { permissions } from "@/lib/rbac/permissions";
import { useAuthStore } from "@/stores/auth-store";
import { useNotificationStore } from "@/stores/notification-store";
import { cn } from "@/lib/utils";

const EMPTY_PEOPLE: [] = [];

type OrgView = "line" | "all";

export function OrgPageClient() {
  const queryClient = useQueryClient();
  const notify = useNotificationStore((state) => state.push);
  const currentUserId = useAuthStore((state) => state.user?.id);
  const organizationLabel = useOrganizationLabel();
  const [search, setSearch] = useState("");
  const [view, setView] = useState<OrgView>("all");
  const [focusedId, setFocusedId] = useState<string | null>(null);

  const chartQuery = useQuery({
    queryKey: ["admin", "users", "org-chart"],
    queryFn: fetchAdminOrgChart,
    staleTime: 0,
  });

  const people = chartQuery.data?.people ?? EMPTY_PEOPLE;
  const index = useMemo(() => buildOrgChartIndex(people), [people]);
  const suggestions = useMemo(() => filterOrgPeople(index.nodes, search), [index.nodes, search]);

  useEffect(() => {
    if (focusedId && index.byId.has(focusedId)) {
      return;
    }
    setFocusedId(pickDefaultFocus(index, currentUserId));
  }, [currentUserId, focusedId, index]);

  const syncMutation = useMutation({
    mutationFn: syncAdminEntraOrg,
    onSuccess: (result) => {
      notify({
        level: result.ok ? "success" : "warning",
        title: result.ok ? "Org chart updated" : "Sync did not complete",
        message: result.message,
      });
      void queryClient.invalidateQueries({ queryKey: ["admin", "users"] });
      void queryClient.invalidateQueries({ queryKey: ["admin", "users", "org-chart"] });
    },
    onError: (error) => {
      const timedOut =
        axios.isAxiosError(error) &&
        (error.code === "ECONNABORTED" || error.message.toLowerCase().includes("timeout"));
      const dropped =
        axios.isAxiosError(error) &&
        (error.code === "ERR_NETWORK" || error.message === "Network Error");
      notify({
        level: timedOut ? "warning" : "error",
        title: timedOut ? "Sync is still running" : "Sync failed",
        message: timedOut
          ? "Microsoft org sync is taking longer than the browser wait. Refresh this page — licensed people and reporting lines may already be saved."
          : dropped
            ? "Organization sync failed before a result came back. Confirm the API is running, then try Sync again."
            : getErrorMessage(error),
      });
      if (timedOut) {
        void queryClient.invalidateQueries({ queryKey: ["admin", "users"] });
        void queryClient.invalidateQueries({ queryKey: ["admin", "users", "org-chart"] });
      }
    },
  });

  const selectPerson = (id: string) => {
    setFocusedId(id);
    setSearch("");
  };

  const selectFromSearch = (id: string) => {
    selectPerson(id);
    setView("line");
  };

  return (
    <PermissionGate requiredPermissions={[permissions.userManage]}>
      <div className="space-y-5">
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight text-foreground">Organization</h1>
            <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
              Browse the full organization chart, or open one person for their manager and direct reports. Sync copies
              manager, job title, department, and Microsoft 365 license onto existing {organizationLabel} users. People without a
              Microsoft 365 license are hidden here.
            </p>
            <p className="mt-2 text-xs text-muted-foreground">
              Last synced {formatTimestamp(chartQuery.data?.synced_at)}
            </p>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <Link href="/users" prefetch={false} className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
              Back to users
            </Link>
            <Button size="sm" disabled={syncMutation.isPending} onClick={() => syncMutation.mutate()}>
              {syncMutation.isPending ? <Spinner className="size-3.5" /> : <RefreshCw className="size-3.5" />}
              {syncMutation.isPending ? "Syncing…" : "Sync from Microsoft"}
            </Button>
          </div>
        </header>

        <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
          <div className="border-b border-border px-4 py-3">
            <div className="flex flex-wrap items-end justify-between gap-3">
              <div className="min-w-0 flex-1">
                <label className="mb-1 block text-xs font-medium text-muted-foreground" htmlFor="org-search">
                  Search people
                </label>
                <Input
                  id="org-search"
                  value={search}
                  onChange={(event) => setSearch(event.target.value)}
                  placeholder="Name, email, job title, or department"
                  className="h-11 w-full text-base sm:h-9 sm:max-w-md sm:text-sm"
                />
              </div>
              <Tabs value={view} onValueChange={(value) => setView(value as OrgView)}>
                <TabsList>
                  <TabsTrigger value="all" className="px-3">
                    All organization
                  </TabsTrigger>
                  <TabsTrigger value="line" className="px-3">
                    Reporting line
                  </TabsTrigger>
                </TabsList>
              </Tabs>
            </div>
            {suggestions.length > 0 ? (
              <ul className="mt-2 max-w-md divide-y divide-border rounded-lg border border-border bg-background">
                {suggestions.map((person) => (
                  <li key={person.id}>
                    <button
                      type="button"
                      className="flex w-full flex-col px-3 py-2 text-left hover:bg-muted/40"
                      onClick={() => selectFromSearch(person.id)}
                    >
                      <span className="text-sm font-medium text-foreground">{person.name}</span>
                      <span className="text-xs text-muted-foreground">
                        {person.job_title ? `${person.job_title} · ` : ""}
                        {person.department ? `${person.department} · ` : ""}
                        {person.email}
                        {person.external ? " · In Microsoft Entra only" : ""}
                      </span>
                    </button>
                  </li>
                ))}
              </ul>
            ) : null}
          </div>

          <div className="min-h-[28rem] bg-muted/20">
            {chartQuery.isLoading ? (
              <p className="px-4 py-10 text-center text-sm text-muted-foreground">Loading organization…</p>
            ) : chartQuery.isError ? (
              <p className="px-4 py-10 text-center text-sm text-destructive">{getErrorMessage(chartQuery.error)}</p>
            ) : index.nodes.length === 0 ? (
              <p className="px-4 py-10 text-center text-sm text-muted-foreground">
                No licensed Microsoft 365 users to display. Sync from Microsoft to load licensed people. Unlicensed Entra
                accounts stay hidden.
              </p>
            ) : view === "all" ? (
              <AdminOrgTreeView index={index} focusedId={focusedId} onSelect={selectPerson} />
            ) : focusedId ? (
              <AdminOrgChartView
                index={index}
                focusedId={focusedId}
                onFocus={setFocusedId}
                organizationLabel={organizationLabel}
              />
            ) : (
              <p className="px-4 py-10 text-center text-sm text-muted-foreground">
                Search for a person to open their reporting line.
              </p>
            )}
          </div>
        </div>
      </div>
    </PermissionGate>
  );
}
