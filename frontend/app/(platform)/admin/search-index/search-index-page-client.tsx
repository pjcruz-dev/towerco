"use client";

import { useCallback, useEffect, useState } from "react";
import { RefreshCw, Search, Sparkles, Wand2 } from "lucide-react";

import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { getErrorMessage } from "@/lib/api/error";
import {
  fetchSearchIndexStatus,
  runSearchIndexAction,
  type SearchIndexCoverageRow,
  type SearchIndexStatus,
} from "@/lib/api/modules/search-index-api";
import { permissions } from "@/lib/rbac/permissions";
import { adminPageShellClass } from "@/lib/ui/page-shell";
import { cn } from "@/lib/utils";

function formatCount(n: number): string {
  return n.toLocaleString();
}

function statusBadge(row: SearchIndexCoverageRow) {
  if (row.status === "not_indexed") {
    return (
      <span className="ml-2 inline-flex rounded-md bg-amber-100 px-1.5 py-0.5 text-[11px] font-medium text-amber-800 dark:bg-amber-950/50 dark:text-amber-200">
        not indexed
      </span>
    );
  }
  if (row.status === "partial") {
    return (
      <span className="ml-2 inline-flex rounded-md bg-sky-100 px-1.5 py-0.5 text-[11px] font-medium text-sky-800 dark:bg-sky-950/50 dark:text-sky-200">
        partial
      </span>
    );
  }
  return null;
}

export function SearchIndexPageClient() {
  const [status, setStatus] = useState<SearchIndexStatus | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      setStatus(await fetchSearchIndexStatus());
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  async function run(
    action: "repair" | "full_rebuild" | "rebuild_metadata" | "rebuild_entity",
    entitySlug?: string,
  ) {
    setBusy(true);
    setError(null);
    setNotice(null);
    try {
      const result = await runSearchIndexAction({
        action,
        entity_slug: entitySlug,
      });
      setStatus(result.status);
      const rebuilt = result.records_rebuilt;
      setNotice(
        rebuilt != null
          ? `Done — rebuilt ${formatCount(rebuilt)} record index(es).`
          : "Metadata refreshed.",
      );
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  const summary = status?.summary;

  return (
    <PermissionGate requiredPermissions={[permissions.searchIndexManage]}>
      <div className={adminPageShellClass}>
        <header className="space-y-1">
          <div className="flex items-center gap-2 text-muted-foreground">
            <Search className="size-4" />
            <span className="text-xs font-medium">System Core</span>
          </div>
          <h1 className="text-2xl font-semibold text-foreground">Search Index</h1>
          <p className="max-w-3xl text-sm text-muted-foreground">
            Powers Ctrl+K search and Dynamic Entity list filters. Includes records, tables, fields,
            reports, and people.
          </p>
        </header>

        {error ? (
          <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-950/40 dark:text-red-300">
            {error}
          </div>
        ) : null}
        {notice ? (
          <div className="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-100">
            {notice}
          </div>
        ) : null}

        {loading || !status || !summary ? (
          <div className="rounded-xl border bg-card p-8 text-sm text-muted-foreground">Loading…</div>
        ) : (
          <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
            <section className="space-y-4 rounded-xl border bg-card p-5 shadow-sm">
              <div className="flex flex-wrap items-center gap-2">
                <h2 className="text-base font-medium">Search Index</h2>
                <span className="rounded-md bg-muted px-2 py-0.5 text-xs font-medium tabular-nums text-muted-foreground">
                  {formatCount(status.total_indexed)}
                </span>
              </div>
              <p className="text-sm text-muted-foreground">
                The index populates the Ctrl+K search palette and Dynamic Entity filters. It covers
                records, tables, fields, reports, workflows, and people.
              </p>

              {status.drift.detected && status.drift.message ? (
                <div className="rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-900 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-100">
                  {status.drift.message}
                </div>
              ) : (
                <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-100">
                  Index looks healthy — no drift detected across entity tables.
                </div>
              )}

              <ul className="space-y-1.5 text-sm">
                {(
                  [
                    ["Records", summary.records],
                    ["Tables", summary.tables],
                    ["Fields", summary.fields],
                    ["Reports", summary.reports],
                    ["Workflows", summary.workflows],
                    ["Apps", summary.apps],
                    ["Pages", summary.pages],
                    ["People", summary.people],
                  ] as const
                ).map(([label, value]) => (
                  <li key={label} className="flex justify-between gap-4 border-b border-border/60 py-1.5 last:border-0">
                    <span className="text-muted-foreground">{label}</span>
                    <span className="tabular-nums font-medium">{formatCount(value)}</span>
                  </li>
                ))}
              </ul>

              <p className="text-xs text-muted-foreground">
                Last indexed: {status.last_indexed_at ?? "Never"}
                {status.last_action ? ` · ${status.last_action}` : ""}
              </p>

              <div className="flex flex-col gap-2 pt-1">
                <Button
                  type="button"
                  disabled={busy}
                  className="justify-start"
                  onClick={() => void run("repair")}
                >
                  <Wand2 className="size-4" />
                  Repair now
                </Button>
                <Button
                  type="button"
                  variant="secondary"
                  disabled={busy}
                  className="justify-start"
                  onClick={() => void run("full_rebuild")}
                >
                  <RefreshCw className={cn("size-4", busy && "animate-spin")} />
                  Full rebuild
                </Button>
                <Button
                  type="button"
                  variant="ghost"
                  disabled={busy}
                  className="justify-start text-muted-foreground"
                  onClick={() => void run("rebuild_metadata")}
                >
                  <Sparkles className="size-4" />
                  Rebuild metadata only
                </Button>
              </div>
            </section>

            <section className="rounded-xl border bg-card shadow-sm">
              <div className="border-b px-5 py-4">
                <h2 className="text-base font-medium">Coverage by table</h2>
              </div>
              <div className="max-h-[640px] overflow-auto">
                <table className="w-full text-sm">
                  <thead className="sticky top-0 bg-muted/80 text-left text-xs font-medium text-muted-foreground backdrop-blur">
                    <tr>
                      <th className="px-4 py-3">Table</th>
                      <th className="px-4 py-3 text-right">Indexed rows</th>
                      <th className="px-4 py-3 text-right">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    {status.coverage.length === 0 ? (
                      <tr>
                        <td colSpan={3} className="px-4 py-8 text-center text-muted-foreground">
                          No dynamic entities found.
                        </td>
                      </tr>
                    ) : (
                      status.coverage.map((row) => (
                        <tr key={row.id} className="border-t">
                          <td className="px-4 py-2.5">
                            <span className="font-medium">{row.name}</span>
                            {statusBadge(row)}
                            <div className="text-[11px] text-muted-foreground">
                              {row.slug} · {formatCount(row.record_count)} records
                            </div>
                          </td>
                          <td className="px-4 py-2.5 text-right tabular-nums">
                            {formatCount(row.indexed_rows)}
                          </td>
                          <td className="px-4 py-2.5 text-right">
                            <Button
                              type="button"
                              size="sm"
                              variant="outline"
                              disabled={busy || row.record_count === 0}
                              onClick={() => void run("rebuild_entity", row.slug)}
                            >
                              Rebuild
                            </Button>
                          </td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>
            </section>
          </div>
        )}
      </div>
    </PermissionGate>
  );
}
