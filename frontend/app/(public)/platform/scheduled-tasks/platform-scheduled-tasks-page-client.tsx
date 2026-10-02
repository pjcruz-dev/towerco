"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useMemo } from "react";
import { useQuery } from "@tanstack/react-query";

import { environmentBadgeClass } from "@/components/platform/tenant-environment-sheet";
import { PlatformDataTable } from "@/components/platform/platform-data-table";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { RefreshingHint } from "@/components/ui/refreshing-hint";
import { Skeleton } from "@/components/ui/skeleton";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import {
  platformFetchScheduledTasks,
  type PlatformScheduleTask,
  type PlatformScheduleTaskStatus,
} from "@/lib/api/modules/platform-api";
import { getErrorMessage } from "@/lib/api/error";
import { statusToneClassName, type StatusTone } from "@/lib/ui/status-tone";
import { cn } from "@/lib/utils";

const ENVIRONMENTS = ["all", "production", "staging", "test", "local"] as const;
const STATUSES = ["all", "failed", "overdue", "paused", "active"] as const;

function formatWhen(value: string | null): string {
  if (!value) {
    return "—";
  }
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return "—";
  }
  return date.toLocaleString(undefined, { dateStyle: "medium", timeStyle: "short" });
}

function formatRelative(value: string | null): string {
  if (!value) {
    return "—";
  }
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return "—";
  }
  const diffMs = date.getTime() - Date.now();
  const future = diffMs > 0;
  const minutes = Math.round(Math.abs(diffMs) / 60000);
  const phrase = (amount: number, singular: string, plural: string) => {
    const label = `${amount} ${amount === 1 ? singular : plural}`;
    return future ? `in ${label}` : `${label} ago`;
  };
  if (minutes < 1) {
    return future ? "in under a minute" : "just now";
  }
  if (minutes < 60) {
    return phrase(minutes, "min", "min");
  }
  const hours = Math.round(minutes / 60);
  if (hours < 48) {
    return phrase(hours, "hour", "hours");
  }
  return phrase(Math.round(hours / 24), "day", "days");
}

function cronWorkspaceHref(domain: string): string {
  const host = domain.trim();
  const local =
    host === "localhost" ||
    host.endsWith(".localhost") ||
    host.startsWith("127.0.0.1");
  return `${local ? "http" : "https"}://${host}/admin/automation`;
}

function statusLabel(status: PlatformScheduleTaskStatus): string {
  switch (status) {
    case "failed":
      return "Failed";
    case "overdue":
      return "Overdue";
    case "paused":
      return "Paused";
    default:
      return "Active";
  }
}

function statusTone(status: PlatformScheduleTaskStatus): StatusTone {
  switch (status) {
    case "failed":
      return "danger";
    case "overdue":
      return "warning";
    case "paused":
      return "neutral";
    default:
      return "success";
  }
}

export function PlatformScheduledTasksPageClient() {
  const router = useRouter();
  const params = useSearchParams();
  const environment = params.get("environment") ?? "all";
  const status = params.get("status") ?? "all";

  const query = useQuery({
    queryKey: ["platform", "scheduled-tasks"],
    queryFn: platformFetchScheduledTasks,
  });

  const data = query.data;

  const filtered = useMemo(() => {
    const tasks = data?.tasks ?? [];
    return tasks.filter((task) => {
      if (environment !== "all" && task.environment !== environment) {
        return false;
      }
      if (status !== "all" && task.status !== status) {
        return false;
      }
      return true;
    });
  }, [data?.tasks, environment, status]);

  const setFilter = (key: "environment" | "status", value: string) => {
    const next = new URLSearchParams(params.toString());
    if (value === "all") {
      next.delete(key);
    } else {
      next.set(key, value);
    }
    const queryString = next.toString();
    router.replace(queryString === "" ? "/platform/scheduled-tasks" : `/platform/scheduled-tasks?${queryString}`);
  };

  const pausedCopies = (data?.tenants ?? []).filter((tenant) => tenant.paused_after_copy);
  const unreachable = (data?.tenants ?? []).filter((tenant) => tenant.unreachable);
  const noTable = (data?.tenants ?? []).filter((tenant) => !tenant.unreachable && !tenant.has_schedule_table);

  return (
    <div className="flex flex-col gap-6">
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold text-foreground">Schedules</h1>
          <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
            Platform jobs run once for the server. Each environment has one cron list, and a job appears there only when that environment has the module turned on.
          </p>
        </div>
        <Link href="/platform#tenant-directory" className={buttonVariants({ variant: "outline" })}>
          Tenant directory
        </Link>
      </header>

      {query.isLoading ? (
        <div className="flex flex-col gap-6" aria-busy="true">
          <span className="sr-only">Loading schedules</span>
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {Array.from({ length: 4 }).map((_, index) => (
              <Skeleton key={index} className="h-24 rounded-xl" />
            ))}
          </div>
          <Skeleton className="h-40 rounded-xl" />
          <Skeleton className="h-64 rounded-xl" />
        </div>
      ) : query.isError ? (
        <Card className="rounded-xl shadow-sm">
          <CardContent className="flex flex-wrap items-center justify-between gap-3 p-5">
            <p className="text-sm text-destructive">
              Could not load schedules. {getErrorMessage(query.error)}
            </p>
            <Button type="button" variant="outline" onClick={() => void query.refetch()}>
              Retry
            </Button>
          </CardContent>
        </Card>
      ) : data ? (
        <>
          {query.isFetching && !query.isLoading ? <RefreshingHint label="Refreshing schedules" /> : null}
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Count label="Environments" value={data.counts.environments} />
            <Count label="Active cron rows" value={data.counts.active} />
            <Count label="Paused cron rows" value={data.counts.paused} />
            <Count label="Failed last runs" value={data.counts.failed} />
          </div>

          <section className="flex flex-col gap-3">
            <div>
              <h2 className="text-base font-medium text-foreground">Runs for the platform</h2>
              <p className="mt-1 text-sm text-muted-foreground">
                Backups and billing. Module jobs are not listed here, so they are not run a second time.
              </p>
            </div>
            <PlatformDataTable
              rows={data.fleet}
              rowKey={(row) => row.command}
              emptyMessage="No fleet jobs are registered."
              columns={[
                { id: "label", header: "Job", cell: (row) => row.label },
                { id: "module", header: "Module", cell: (row) => row.module },
                { id: "cadence", header: "Cadence", cell: (row) => row.cadence },
                {
                  id: "command",
                  header: "Command",
                  cell: (row) => <span className="font-mono text-xs text-muted-foreground">{row.command}</span>,
                },
              ]}
            />
          </section>

          {pausedCopies.length > 0 ? (
            <Card className="rounded-xl shadow-sm">
              <CardContent className="flex flex-col gap-2 p-5 text-sm">
                <p className="font-medium text-foreground">Paused after a copy</p>
                <p className="text-muted-foreground">
                  Every saved cron row in these environments is off. Resume them inside that workspace. Staging is unchanged.
                </p>
                <ul className="flex flex-col gap-1">
                  {pausedCopies.map((tenant) => (
                    <li key={tenant.tenant_id}>
                      <Link href={`/platform/tenants/${tenant.tenant_id}`} className="text-primary underline-offset-4 hover:underline">
                        {tenant.slug || "Workspace"} · {tenant.environment || "environment"}
                        {tenant.domain ? ` · ${tenant.domain}` : ""}
                      </Link>
                    </li>
                  ))}
                </ul>
              </CardContent>
            </Card>
          ) : null}

          {unreachable.length > 0 ? (
            <Card className="rounded-xl border-destructive/40 shadow-sm">
              <CardContent className="p-5 text-sm text-destructive">
                Could not open {unreachable.map((tenant) => tenant.domain || tenant.slug || tenant.tenant_id).join(", ")}. Other environments are still listed.
              </CardContent>
            </Card>
          ) : null}

          <section className="flex flex-col gap-3">
            <div>
              <h2 className="text-base font-medium text-foreground">Saved in each environment</h2>
              <p className="mt-1 text-sm text-muted-foreground">
                One row per job. Hidden when that environment does not have the module. Resume and pause stay inside the workspace under Settings, Manage Cron Jobs.
                {data.timezone ? ` Times use ${data.timezone}.` : ""}
              </p>
            </div>
            <div className="flex flex-col gap-2">
              <FilterRow
                label="Environment"
                values={ENVIRONMENTS}
                current={environment}
                onSelect={(value) => setFilter("environment", value)}
              />
              <FilterRow
                label="Status"
                values={STATUSES}
                current={status}
                onSelect={(value) => setFilter("status", value)}
              />
            </div>
            <PlatformDataTable
              rows={filtered}
              rowKey={(row) => `${row.tenant_id}:${row.id}`}
              emptyMessage={
                data.tasks.length === 0
                  ? "No environment has saved cron jobs."
                  : "No schedules match these filters."
              }
              columns={[
                {
                  id: "org",
                  header: "Organization",
                  cell: (row) => (
                    <Link href={`/platform/tenants/${row.tenant_id}`} className="font-medium text-primary underline-offset-4 hover:underline">
                      {row.slug || "Workspace"}
                    </Link>
                  ),
                },
                {
                  id: "environment",
                  header: "Environment",
                  cell: (row) => (
                    <Badge variant="outline" className={environmentBadgeClass(row.environment)}>
                      {row.environment || "—"}
                    </Badge>
                  ),
                },
                { id: "domain", header: "Domain", cell: (row) =>
                  row.domain ? (
                    <a
                      href={cronWorkspaceHref(row.domain)}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="text-primary underline-offset-4 hover:underline"
                    >
                      {row.domain}
                    </a>
                  ) : (
                    "—"
                  ),
                },
                {
                  id: "task",
                  header: "Task",
                  cell: (row) => <TaskCell row={row} />,
                },
                {
                  id: "cron",
                  header: "Cron",
                  cell: (row) => (
                    <span className="font-mono text-xs">
                      {row.cron_expression || "—"}
                      {data.timezone ? (
                        <span className="ml-2 font-sans text-muted-foreground">{data.timezone}</span>
                      ) : null}
                    </span>
                  ),
                },
                {
                  id: "status",
                  header: "Status",
                  cell: (row) => (
                    <span className={statusToneClassName(statusTone(row.status))}>{statusLabel(row.status)}</span>
                  ),
                },
                { id: "last", header: "Last run", cell: (row) => <When value={row.last_run_at} /> },
                { id: "next", header: "Next run", cell: (row) => <When value={row.next_run_at} /> },
              ]}
            />
            {noTable.length > 0 ? (
              <p className="text-xs text-muted-foreground">
                No cron table in {noTable.map((tenant) => tenant.domain || `${tenant.slug} ${tenant.environment}`).join(", ")}.
              </p>
            ) : null}
          </section>
        </>
      ) : null}
    </div>
  );
}

function Count({ label, value }: { label: string; value: number }) {
  return (
    <Card className="rounded-xl shadow-sm">
      <CardContent className="p-5">
        <p className="text-xs font-medium text-muted-foreground">{label}</p>
        <p className="mt-2 text-2xl font-semibold tabular-nums text-foreground">{value}</p>
      </CardContent>
    </Card>
  );
}

function FilterRow({
  label,
  values,
  current,
  onSelect,
}: {
  label: string;
  values: readonly string[];
  current: string;
  onSelect: (value: string) => void;
}) {
  return (
    <div className="flex flex-wrap items-center gap-2">
      <span className="text-xs font-medium text-muted-foreground">{label}</span>
      {values.map((value) => {
        const selected = current === value;
        return (
          <Button
            key={value}
            type="button"
            size="sm"
            variant={selected ? "default" : "outline"}
            aria-pressed={selected}
            onClick={() => onSelect(value)}
            className={cn("capitalize")}
          >
            {value}
          </Button>
        );
      })}
    </div>
  );
}

function When({ value }: { value: string | null }) {
  const absolute = formatWhen(value);
  if (absolute === "—") {
    return <span className="tabular-nums">—</span>;
  }
  return (
    <Tooltip>
      <TooltipTrigger
        render={<span className="cursor-default tabular-nums underline decoration-dotted underline-offset-4" />}
      >
        {formatRelative(value)}
      </TooltipTrigger>
      <TooltipContent>{absolute}</TooltipContent>
    </Tooltip>
  );
}

function TaskCell({ row }: { row: PlatformScheduleTask }) {
  return (
    <div className="flex flex-col gap-0.5">
      <span>{row.name || row.command_key || "Untitled"}</span>
      {row.last_error ? <span className="text-xs text-destructive">{row.last_error}</span> : null}
    </div>
  );
}
