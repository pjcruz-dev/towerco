"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { AlertTriangle, ArrowRight, CheckCircle2, Info } from "lucide-react";

import { DashboardBarChart } from "@/components/dashboard/dashboard-bar-chart";
import { DashboardDonutChart } from "@/components/dashboard/dashboard-donut-chart";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  fetchAtcTicketingBoard,
  type AtcTicketingBoard,
} from "@/lib/api/modules/dynamic-entities-api";
import { permissions } from "@/lib/rbac/permissions";

export function AtcTicketingBoardPageClient() {
  const [data, setData] = useState<AtcTicketingBoard | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    fetchAtcTicketingBoard()
      .then((row) => {
        if (!cancelled) {
          setData(row);
          setError(null);
        }
      })
      .catch(() => {
        if (!cancelled) setError("Unable to load ATC ticketing board.");
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  const k = data?.kpis;

  return (
    <PermissionGate requiredPermissions={[permissions.dynamicEntitiesView]}>
      <div className="space-y-5">
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-2xl font-semibold tracking-tight">Ticketing Board</h1>
              <span className="inline-flex items-center rounded-full bg-emerald-500/15 px-2 py-0.5 text-[11px] font-medium text-emerald-700 dark:text-emerald-400">
                LIVE
              </span>
            </div>
            <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
              Site / maintenance / incident tickets on Dynamic Entities. Native IT helpdesk stays at{" "}
              <Link href="/ticketing" className="underline-offset-4 hover:underline">
                /ticketing
              </Link>
              .
            </p>
            {data?.generated_at ? (
              <p className="mt-1 text-xs text-muted-foreground">
                Updated {new Date(data.generated_at).toLocaleString()}
              </p>
            ) : null}
          </div>
          <div className="flex flex-wrap gap-2">
            <Button variant="outline" size="sm" render={<Link href="/dynamic-entities/site_tickets" />}>
              All tickets
            </Button>
            <Button size="sm" render={<Link href="/dynamic-entities/site_tickets/new" />}>
              New ticket
            </Button>
          </div>
        </header>

        {loading ? <p className="text-sm text-muted-foreground">Loading board…</p> : null}
        {error ? <p className="text-sm text-destructive">{error}</p> : null}
        {data?.message ? <p className="text-sm text-amber-700 dark:text-amber-400">{data.message}</p> : null}

        {!loading && data ? (
          <>
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
              <Kpi title="Total" value={String(k?.total ?? 0)} />
              <Kpi title="Open" value={String(k?.open ?? 0)} />
              <Kpi title="Overdue" value={String(k?.overdue ?? 0)} />
              <Kpi title="Critical open" value={String(k?.critical_open ?? 0)} />
              <Kpi title="Resolved MTD" value={String(k?.resolved_mtd ?? 0)} />
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
              <Card className="rounded-xl">
                <CardHeader>
                  <CardTitle className="text-base font-medium">Needs attention</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                  {(data.attention ?? []).map((item) => (
                    <Link
                      key={item.headline}
                      href={item.href}
                      className="flex gap-3 rounded-lg border border-border/80 p-3 transition-colors hover:bg-muted/40"
                    >
                      <ToneIcon tone={item.tone} />
                      <div className="min-w-0">
                        <div className="text-sm font-medium text-foreground">{item.headline}</div>
                        <div className="mt-0.5 text-xs text-muted-foreground">{item.detail}</div>
                      </div>
                    </Link>
                  ))}
                </CardContent>
              </Card>
              <DashboardDonutChart
                title="By status"
                data={data.by_status}
                valueLabel="Tickets"
                height={240}
              />
              <DashboardBarChart
                title="By priority"
                data={data.by_priority}
                valueLabel="Tickets"
                height={240}
              />
            </div>

            <DashboardDonutChart
              title="By type"
              data={data.by_type}
              valueLabel="Tickets"
              height={220}
            />

            <Card className="rounded-xl">
              <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle className="text-base font-medium">Priority queue</CardTitle>
                <Button variant="ghost" size="sm" render={<Link href="/dynamic-entities/site_tickets" />}>
                  View all
                  <ArrowRight className="ml-1 h-3.5 w-3.5" />
                </Button>
              </CardHeader>
              <CardContent className="overflow-x-auto">
                {(data.recent ?? []).length === 0 ? (
                  <p className="text-sm text-muted-foreground">No tickets yet.</p>
                ) : (
                  <table className="w-full min-w-[640px] text-left text-[13px]">
                    <thead className="border-b border-border text-xs font-medium text-muted-foreground">
                      <tr>
                        <th className="px-2 py-2">Ticket</th>
                        <th className="px-2 py-2">Priority</th>
                        <th className="px-2 py-2">Status</th>
                        <th className="px-2 py-2">Site</th>
                        <th className="px-2 py-2">Assigned</th>
                      </tr>
                    </thead>
                    <tbody>
                      {data.recent.map((row) => (
                        <tr key={row.id} className="border-b border-border/60">
                          <td className="px-2 py-2">
                            <Link href={row.href} className="font-medium text-foreground hover:underline">
                              {row.ticket_number || row.title}
                            </Link>
                            <div className="text-xs text-muted-foreground">{row.title}</div>
                          </td>
                          <td className="px-2 py-2">
                            <PriorityPill priority={row.priority} />
                          </td>
                          <td className="px-2 py-2 text-muted-foreground">{row.status}</td>
                          <td className="px-2 py-2 text-muted-foreground">{row.site}</td>
                          <td className="px-2 py-2 text-muted-foreground">{row.assigned_to || "—"}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                )}
              </CardContent>
            </Card>

            <Card className="rounded-xl">
              <CardHeader>
                <CardTitle className="text-base font-medium">Quick links</CardTitle>
              </CardHeader>
              <CardContent className="flex flex-wrap gap-2">
                {(data.quick_links ?? []).map((link) => (
                  <Button key={link.href} variant="outline" size="sm" render={<Link href={link.href} />}>
                    {link.label}
                    <ArrowRight className="ml-1 h-3.5 w-3.5" />
                  </Button>
                ))}
              </CardContent>
            </Card>
          </>
        ) : null}
      </div>
    </PermissionGate>
  );
}

function Kpi({ title, value }: { title: string; value: string }) {
  return (
    <Card className="rounded-xl">
      <CardContent className="p-4">
        <div className="text-xs font-medium text-muted-foreground">{title}</div>
        <div className="mt-1 text-xl font-semibold tabular-nums text-foreground">{value}</div>
      </CardContent>
    </Card>
  );
}

function PriorityPill({ priority }: { priority: string }) {
  const tone =
    priority === "Critical"
      ? "bg-red-500/15 text-red-700 dark:text-red-400"
      : priority === "High"
        ? "bg-amber-500/15 text-amber-700 dark:text-amber-400"
        : priority === "Low"
          ? "bg-slate-500/15 text-slate-700 dark:text-slate-300"
          : "bg-sky-500/15 text-sky-700 dark:text-sky-400";
  return (
    <span className={`inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium ${tone}`}>{priority}</span>
  );
}

function ToneIcon({ tone }: { tone: string }) {
  if (tone === "danger") {
    return <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-red-600 dark:text-red-400" />;
  }
  if (tone === "warning") {
    return <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />;
  }
  if (tone === "success") {
    return <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" />;
  }
  return <Info className="mt-0.5 h-4 w-4 shrink-0 text-sky-600 dark:text-sky-400" />;
}
