"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";

import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { fetchDocExtractBatches } from "@/lib/api/modules/doc-extract-api";
import { getErrorMessage } from "@/lib/api/error";
import { permissions } from "@/lib/rbac/permissions";
import { cn } from "@/lib/utils";

const CARDS = [
  {
    id: "processing",
    label: "Running",
    hint: "Scanning or waiting",
    href: "/doc-extract/batches?status=processing",
  },
  {
    id: "ready",
    label: "Ready",
    hint: "Results you can review",
    href: "/doc-extract/batches?status=ready",
  },
  {
    id: "failed",
    label: "Failed",
    hint: "Needs another run",
    href: "/doc-extract/batches?status=failed",
  },
] as const;

function formatWhen(iso: string | null | undefined): string {
  if (!iso) {
    return "";
  }
  try {
    return new Intl.DateTimeFormat(undefined, {
      month: "short",
      day: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    }).format(new Date(iso));
  } catch {
    return iso;
  }
}

export function DocExtractDashboardPageClient() {
  const query = useQuery({
    queryKey: ["doc-extract", "dashboard"],
    queryFn: () => fetchDocExtractBatches({ page: 1, per_page: 5, sort: "created_at:desc" }),
  });

  const counts = query.data?.meta.status_counts;
  const recent = query.data?.data ?? [];

  return (
    <PermissionGate requiredPermissions={[permissions.docExtractView]}>
      <div className="flex flex-col gap-6">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold text-foreground">DocExtract</h1>
            <p className="mt-1 text-sm text-muted-foreground">
              Extractions waiting on review, still running, or failed.
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <Button size="sm" variant="outline" render={<Link href="/doc-extract/batches" />}>
              Batches
            </Button>
            <Button size="sm" render={<Link href="/doc-extract/new" />}>
              New extraction
            </Button>
          </div>
        </div>

        {query.isLoading ? (
          <div className="grid gap-3 sm:grid-cols-3">
            {CARDS.map((card) => (
              <div key={card.id} className="h-24 animate-pulse rounded-xl border border-border bg-card" />
            ))}
          </div>
        ) : null}

        {query.isError ? (
          <div className="rounded-xl border border-destructive/40 bg-destructive/5 p-4">
            <p className="text-sm font-medium text-destructive">Unable to load DocExtract</p>
            <p className="mt-1 text-sm text-muted-foreground">{getErrorMessage(query.error)}</p>
            <Button className="mt-3" size="sm" variant="outline" onClick={() => void query.refetch()}>
              Retry
            </Button>
          </div>
        ) : null}

        {query.isSuccess ? (
          <>
            <ul className="grid gap-3 sm:grid-cols-3">
              {CARDS.map((card) => {
                const value = counts?.[card.id] ?? 0;
                return (
                  <li key={card.id}>
                    <Link
                      href={card.href}
                      className="block rounded-xl border border-border bg-card p-4 shadow-sm outline-none hover:bg-muted/40 focus-visible:ring-2 focus-visible:ring-ring"
                    >
                      <p className="text-xs text-muted-foreground">{card.label}</p>
                      <p
                        className={cn(
                          "mt-1 text-2xl font-semibold tabular-nums text-foreground",
                          card.id === "failed" && value > 0 && "text-destructive",
                        )}
                      >
                        {value}
                      </p>
                      <p className="mt-1 text-xs text-muted-foreground">{card.hint}</p>
                    </Link>
                  </li>
                );
              })}
            </ul>

            <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div className="flex items-center justify-between gap-2">
                <h2 className="text-base font-medium text-foreground">Recent batches</h2>
                <Link
                  href="/doc-extract/batches"
                  className="text-xs font-medium text-foreground underline-offset-4 hover:underline"
                >
                  View all
                </Link>
              </div>
              {recent.length === 0 ? (
                <p className="py-6 text-sm text-muted-foreground">
                  No extractions yet. Start with New extraction.
                </p>
              ) : (
                <ul className="mt-3 divide-y divide-border">
                  {recent.map((batch) => (
                    <li key={batch.id}>
                      <Link
                        href={`/doc-extract/batches/${batch.id}`}
                        className="flex items-center justify-between gap-3 py-3 outline-none hover:bg-muted/40 focus-visible:ring-2 focus-visible:ring-ring"
                      >
                        <span className="min-w-0">
                          <span className="block truncate text-sm font-medium text-foreground">
                            {batch.primary_filename || batch.template_name || "Extraction"}
                          </span>
                          <span className="block text-xs text-muted-foreground">
                            {batch.status}
                            {batch.created_at ? ` · ${formatWhen(batch.created_at)}` : ""}
                          </span>
                        </span>
                        <span className="shrink-0 text-xs text-muted-foreground">Open</span>
                      </Link>
                    </li>
                  ))}
                </ul>
              )}
            </section>
          </>
        ) : null}
      </div>
    </PermissionGate>
  );
}
