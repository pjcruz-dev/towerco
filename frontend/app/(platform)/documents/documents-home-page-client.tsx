"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { useEffect, useMemo, useState } from "react";

import { DashboardBarChart } from "@/components/dashboard/dashboard-bar-chart";
import { DASHBOARD_CHART } from "@/components/dashboard/dashboard-chart-utils";
import { DocumentDetailDrawer } from "@/components/documents/document-detail-drawer";
import { PermissionGate } from "@/components/layout/permission-gate";
import { RefreshingHint } from "@/components/ui/refreshing-hint";
import { usePermission } from "@/hooks/use-permission";
import { fetchExpiringDocuments } from "@/lib/api/modules/documents-api";
import { permissions } from "@/lib/rbac/permissions";

export function DocumentsHomePageClient({
  initialDocumentId = null,
}: {
  initialDocumentId?: string | null;
}) {
  const canManageTemplate = usePermission([permissions.documentsTemplateManage]);
  const [detailDocumentId, setDetailDocumentId] = useState<string | null>(initialDocumentId);

  useEffect(() => {
    if (initialDocumentId) {
      setDetailDocumentId(initialDocumentId);
    }
  }, [initialDocumentId]);
  const query = useQuery({
    queryKey: ["documents", "expiring"],
    queryFn: () => fetchExpiringDocuments(90),
  });

  const summary = query.data?.summary;

  const expirySeries = useMemo(
    () => [
      { key: "30", label: "≤ 30 days", value: summary?.within_30 ?? 0, fill: DASHBOARD_CHART.danger },
      { key: "60", label: "≤ 60 days", value: summary?.within_60 ?? 0, fill: DASHBOARD_CHART.warning },
      { key: "90", label: "≤ 90 days", value: summary?.within_90 ?? 0, fill: DASHBOARD_CHART.brand },
    ],
    [summary],
  );

  return (
    <PermissionGate requiredPermissions={[permissions.documentsView]}>
      <div className="space-y-6">
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight text-foreground">Documents</h1>
            <p className="mt-1 text-sm text-muted-foreground">
              Expiring leases, permits, and contracts across sites.
            </p>
          </div>
          {canManageTemplate ? (
            <Link
              href="/documents/settings"
              className="text-sm font-medium text-primary hover:underline"
            >
              Binder template
            </Link>
          ) : null}
          <Link
            href="/documents/controlled"
            className="text-sm font-medium text-primary hover:underline"
          >
            Document control
          </Link>
        </header>

        <div className="grid gap-3 sm:grid-cols-3">
          <Metric label="Expiring in 30 days" value={summary?.within_30 ?? 0} />
          <Metric label="Expiring in 60 days" value={summary?.within_60 ?? 0} />
          <Metric label="Expiring in 90 days" value={summary?.within_90 ?? 0} />
        </div>

        <DashboardBarChart
          title="Expiry windows"
          description="Documents approaching expiry across sites"
          data={expirySeries}
          emptyMessage="No expiring documents in the next 90 days."
          height={200}
        />

        <section className="rounded-xl border border-border bg-card shadow-sm">
          <div className="border-b border-border px-4 py-3">
            <h2 className="text-base font-medium">Needs attention (90 days)</h2>
          </div>
          <ul className="divide-y divide-border">
            {(query.data?.items ?? []).length === 0 ? (
              <li className="px-4 py-6 text-sm text-muted-foreground">No expiring documents.</li>
            ) : (
              query.data?.items.map((item) => (
                <li key={item.id}>
                  <button
                    type="button"
                    className="flex w-full flex-wrap items-center justify-between gap-2 px-4 py-3 text-left text-sm hover:bg-muted/40"
                    onClick={() => setDetailDocumentId(item.id)}
                  >
                    <div>
                      <p className="font-medium">{item.title}</p>
                      <p className="text-xs text-muted-foreground">
                        {item.site ? (
                          <Link
                            className="text-primary hover:underline"
                            href={`/sites/${item.site.id}`}
                            onClick={(e) => e.stopPropagation()}
                          >
                            {item.site.site_code}
                          </Link>
                        ) : (
                          "—"
                        )}
                        {item.expires_at
                          ? ` · Expires ${new Date(item.expires_at).toLocaleDateString()}`
                          : ""}
                      </p>
                    </div>
                    <span className="text-xs text-muted-foreground">
                      {item.last_touched_by?.name ?? "—"} ·{" "}
                      {item.last_touched_at
                        ? new Date(item.last_touched_at).toLocaleDateString()
                        : "—"}
                    </span>
                  </button>
                </li>
              ))
            )}
          </ul>
        </section>

        <DocumentDetailDrawer
          documentId={detailDocumentId}
          open={detailDocumentId !== null}
          onOpenChange={(open) => {
            if (!open) setDetailDocumentId(null);
          }}
        />
        {query.isFetching && !query.isLoading ? <RefreshingHint label="Updating documents" /> : null}
      </div>
    </PermissionGate>
  );
}

function Metric({ label, value }: { label: string; value: number }) {
  return (
    <div className="rounded-xl border border-border bg-card px-4 py-3 shadow-sm">
      <p className="text-xs text-muted-foreground">{label}</p>
      <p className="mt-1 text-2xl font-medium tabular-nums">{value}</p>
    </div>
  );
}
