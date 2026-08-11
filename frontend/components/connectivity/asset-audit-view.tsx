"use client";

import { Activity, Clock, Download, FileText, RefreshCcw, Search, Shield } from "lucide-react";
import { useCallback, useState } from "react";

import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Spinner } from "@/components/ui/spinner";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";

type AuditLog = {
  id: string;
  timestamp: string;
  userEmail: string;
  userId: string;
  action: string;
  resource: string;
};

const seedLogs: AuditLog[] = [
  {
    id: "log-019a2f3c4d5e",
    timestamp: new Date().toISOString(),
    userEmail: "admin@toweros.internal",
    userId: "usr-88421",
    action: "UPDATE",
    resource: "/api/v1/tenants/domains",
  },
  {
    id: "log-029b3e4d5f60",
    timestamp: new Date(Date.now() - 3600_000).toISOString(),
    userEmail: "viewer@tenant.local",
    userId: "usr-99102",
    action: "CREATE",
    resource: "/api/v1/auth/sessions",
  },
  {
    id: "log-039c4f5e6071",
    timestamp: new Date(Date.now() - 7200_000).toISOString(),
    userEmail: "sso.user@corp.com",
    userId: "usr-44120",
    action: "UNAUTHORIZED_ACCESS",
    resource: "/api/v1/admin/sso/config",
  },
];

function formatTs(iso: string) {
  const d = new Date(iso);
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  const h = String(d.getHours()).padStart(2, "0");
  const min = String(d.getMinutes()).padStart(2, "0");
  const s = String(d.getSeconds()).padStart(2, "0");
  return `${y}.${m}.${day} ${h}:${min}:${s}`;
}

function getActionColor(action: string) {
  switch (action) {
    case "CREATE":
      return "text-emerald-600 bg-emerald-50 border-emerald-100 dark:bg-emerald-950 dark:text-emerald-300";
    case "UPDATE":
      return "text-blue-600 bg-blue-50 border-blue-100 dark:bg-blue-950 dark:text-blue-300";
    case "DELETE":
      return "text-rose-600 bg-rose-50 border-rose-100 dark:bg-rose-950 dark:text-rose-300";
    case "UNAUTHORIZED_ACCESS":
      return "text-red-600 bg-red-50 border-red-200 dark:bg-red-950 dark:text-red-300";
    default:
      return "text-slate-600 bg-slate-50 border-slate-200 dark:bg-slate-900 dark:text-slate-300";
  }
}

export function AssetAuditView() {
  const [logs, setLogs] = useState<AuditLog[]>(seedLogs);
  const [loading, setLoading] = useState(false);

  const refresh = useCallback(() => {
    setLoading(true);
    window.setTimeout(() => {
      setLogs((prev) => [...prev].reverse());
      setLoading(false);
    }, 600);
  }, []);

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div className="flex flex-col gap-1">
          <h1 className="text-2xl font-semibold tracking-tight text-foreground">
            Compliance & Audit
          </h1>
          <p className="max-w-2xl text-sm leading-relaxed text-muted-foreground">
            Immutable ledger of system interactions and asset lifecycle events.
          </p>
        </div>
        <div className="flex gap-2">
          <button
            type="button"
            onClick={refresh}
            className="flex items-center justify-center rounded-md border border-slate-200 bg-white p-2 shadow-sm transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:hover:bg-slate-800"
          >
            {loading ? (
              <Spinner className="size-4 text-blue-600" />
            ) : (
              <RefreshCcw className="h-4 w-4 text-slate-400" aria-hidden />
            )}
          </button>
          <button
            type="button"
            className="flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm transition-opacity hover:opacity-90"
          >
            <Download className="h-3.5 w-3.5" />
            Security Export
          </button>
        </div>
      </div>

      <div className="grid gap-4 md:grid-cols-3">
        <Card className="rounded-xl border-slate-200 shadow-sm dark:border-slate-800">
          <CardContent className="pt-6">
            <div className="flex items-center gap-4">
              <div className="rounded-lg bg-blue-50 p-2.5 dark:bg-blue-950/50">
                <Shield className="h-5 w-5 text-blue-600 dark:text-blue-400" />
              </div>
              <div className="flex flex-col">
                <span className="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                  Integrity Status
                </span>
                <span className="text-sm font-medium uppercase text-emerald-600">Verified</span>
              </div>
            </div>
          </CardContent>
        </Card>
        <Card className="rounded-xl border-slate-200 shadow-sm dark:border-slate-800">
          <CardContent className="pt-6">
            <div className="flex items-center gap-4">
              <div className="rounded-lg bg-blue-50 p-2.5 dark:bg-blue-950/50">
                <Clock className="h-5 w-5 text-blue-600 dark:text-blue-400" />
              </div>
              <div className="flex flex-col">
                <span className="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                  Last Sync
                </span>
                <span className="text-sm font-medium text-slate-700 dark:text-slate-200">
                  {formatTs(new Date().toISOString())} UTC
                </span>
              </div>
            </div>
          </CardContent>
        </Card>
        <Card className="rounded-xl border-slate-200 shadow-sm dark:border-slate-800">
          <CardContent className="pt-6">
            <div className="flex items-center gap-4">
              <div className="rounded-lg bg-blue-50 p-2.5 dark:bg-blue-950/50">
                <Activity className="h-5 w-5 text-blue-600 dark:text-blue-400" />
              </div>
              <div className="flex flex-col">
                <span className="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                  Log Density
                </span>
                <span className="text-sm font-medium text-slate-700 dark:text-slate-200">{logs.length} Events / 24h</span>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <Card className="overflow-hidden rounded-xl border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950">
        <CardHeader className="border-b border-slate-100 bg-slate-50/50 pb-4 dark:border-slate-800 dark:bg-slate-900/40">
          <div className="relative max-w-sm flex-1">
            <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
            <input
              type="search"
              placeholder="Search audit trail..."
              className="h-10 w-full rounded-lg border border-slate-200 bg-white pl-10 text-sm outline-none transition-all focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 dark:border-slate-700 dark:bg-slate-950"
            />
          </div>
        </CardHeader>
        <CardContent className="p-0">
          <Table>
            <TableHeader className="bg-slate-50/30 dark:bg-slate-900/30">
              <TableRow className="border-slate-100 dark:border-slate-800">
                <TableHead className="py-5 text-xs font-medium uppercase tracking-wide text-slate-400">
                  Timestamp
                </TableHead>
                <TableHead className="text-xs font-medium uppercase tracking-wide text-slate-400">Actor</TableHead>
                <TableHead className="text-xs font-medium uppercase tracking-wide text-slate-400">
                  Event Type
                </TableHead>
                <TableHead className="text-xs font-medium uppercase tracking-wide text-slate-400">
                  Resource Path
                </TableHead>
                <TableHead className="pr-8 text-right text-xs font-medium uppercase tracking-wide text-slate-400">
                  Global UUID
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {logs.map((log) => (
                <TableRow
                  key={log.id}
                  className="border-slate-50 transition-colors hover:bg-slate-50/80 dark:border-slate-900 dark:hover:bg-slate-900/50"
                >
                  <TableCell className="whitespace-nowrap font-mono text-[10px] font-medium text-slate-500">
                    {formatTs(log.timestamp)}
                  </TableCell>
                  <TableCell>
                    <div className="flex items-center gap-3">
                      <div className="flex h-7 w-7 items-center justify-center rounded-full border border-border bg-muted text-[10px] font-medium text-muted-foreground">
                        {log.userEmail.slice(0, 2).toUpperCase()}
                      </div>
                      <div className="flex flex-col">
                        <span className="text-xs font-medium text-slate-800 dark:text-slate-100">{log.userEmail}</span>
                        <span className="font-mono text-[9px] tracking-tighter text-slate-400 uppercase">
                          {log.userId}
                        </span>
                      </div>
                    </div>
                  </TableCell>
                  <TableCell>
                    <Badge
                      variant="outline"
                      className={`h-5 border font-mono text-[8px] font-medium tracking-wide ${getActionColor(log.action)}`}
                    >
                      {log.action}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    <div className="flex items-center gap-1.5">
                      <div className="rounded bg-slate-100 p-1 dark:bg-slate-800">
                        <FileText className="h-3.5 w-3.5 text-slate-500" />
                      </div>
                      <span className="text-xs font-medium tracking-tight text-slate-700 dark:text-slate-200">
                        {log.resource}
                      </span>
                    </div>
                  </TableCell>
                  <TableCell className="pr-8 text-right font-mono text-[10px] font-medium text-slate-300 dark:text-slate-600">
                    {log.id.slice(0, 13)}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  );
}
